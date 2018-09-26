<?php

namespace Inceptionsol\Coreintegrity\core;

/**
 * Class WP_Core_Integrity.
 *
 * @author   : Mohsin Adeel <mohsin.adeel@yahoo.com>
 * @license  : GPL3
 * @copyright: Mohsin Adeel
 */
class WP_Core_Integrity
{

    /**
     * Check if the plugin's hooks are initiated.
     *
     * @var
     */
    private $initiated;
    /**
     * @var 'CSS class for Message i.e. success, warning, error and info.'
     */
    private $css_class;
    /**
     * @var 'Message for Admin User'
     */
    private $message;

    private $checksum;

    public function __construct()
    {
        $this->initiated = false;
        $this->css_class = 'success';
        $this->message   = '';
    }

    /**
     * initialize plugins hooks from wordpress.
     */
    public function init()
    {
        if (! $this->initiated) {
            $this->init_hooks();
        }
    }

    public function get_initiated()
    {
        return $this->initiated;
    }

    /**
     * A place for Initializing WordPress hooks.
     */
    private function init_hooks()
    {
        $this->initiated = true;
        add_action('admin_init', [$this, 'register_plugin_settings']);
        add_action('admin_menu', [$this, 'register_plugin_menu']);

        add_filter('cron_schedules', [$this, 'my_cron_schedules']);
        if (! wp_next_scheduled('verify_daily_wp_core_integrity')) {
            wp_schedule_event(time(), 'twicedaily', 'verify_daily_wp_core_integrity');
        }

        add_action('verify_daily_wp_core_integrity', [$this, 'cron_scan_job']);
    }


    public function register_plugin_settings()
    {
        register_setting('wp_core_integrity_core_group', 'include_wp_content',
            ['default' => 0]);
        register_setting('wp_core_integrity_core_group', 'check_newly_added_files',
            ['default' => 1]);
    }

    public function register_plugin_menu()
    {
        add_menu_page('Check WP Core Integrity', 'WP Core Integrity',
            'manage_options', 'wp_core_integrity',
            [$this, 'set_plugin_options'],
            'dashicons-lock');
        add_submenu_page('wp_core_integrity', 'WP Core Integrity Settings',
            'Settings', 'manage_options', 'wp_core_integrity',
            [$this, 'set_plugin_options']);
        add_submenu_page('wp_core_integrity', 'Scan WP Core Integrity',
            'Scan WP Core', 'manage_options', 'wp_core_integrity_check',
            [$this, 'check_wp_integrity']);

        add_action('admin_enqueue_scripts', [$this, 'plugin_admin_styles']);
    }

    public function plugin_admin_styles()
    {
        $handle = 'wpCoreIntegrityStyle';
        $src    = plugins_url('css/style.css', WCI_BASE_FILE);
        wp_register_style($handle, $src);
        wp_enqueue_style($handle, $src, [], false, false);
    }

    public function set_plugin_options()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        require WCI_VIEW_PATH . 'settings.php';
    }

    public function check_wp_integrity()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }


        echo '<div class="wrap" id="wp-core-integrity">';
        echo '<h3>WordPress Core Integrity: Scan </h3>';

        $start_scan = filter_input(INPUT_GET, 'start', FILTER_SANITIZE_NUMBER_INT);
        if (isset($start_scan) && $start_scan == 1) {
            $this->checksum = $this->getChecksums();
            if (! $this->checksum) {
                $this->setNotice('Unable to connect to WordPress for official checksum. Please check your internet connectivity.',
                    'warning');

                return false;
            }
            $hasNoError                       = false;
            $check_newly_added_files_settings = get_option('check_newly_added_files', 1);
            $count_check_new_files_in_core    = null;

            $start_time = time();
            list($count_check_files_changes_in_core_files, $hasNoError) = $this->check_files_changes_in_core_files($this->checksum);

            if ($hasNoError) {
                if ($check_newly_added_files_settings) {
                    list($count_check_new_files_in_core, $hasNoError) = $this->check_new_files_in_core($this->checksum);
                }

                if ($hasNoError) {
                    $this->setNotice('<strong>Congratulations!</strong>,WordPress integrity test passed.');
                }
            }

            $end_time = time();

            echo '<div class="integrity-results">';
            echo '<h3>Scan Details:</h3>';

            echo '<p><strong>Total files scanned: </strong>' . $count_check_files_changes_in_core_files . '<p>';

            echo '<p><strong>Total new malicious files found: </strong>' . ($count_check_new_files_in_core ?: 0) . '<p>';


            echo '<p><strong>Total time taken: </strong>' . ($end_time - $start_time) . ' seconds<p>';
            echo '<h3>Selected Scan Options:</h3>';
            echo '<p>Include "wp_content" to scan: <strong>' . (get_option('include_wp_content') ? "Yes" : "No") . '</strong></p>';
            echo '<p>Search for malicious files addition in the core: <strong>' . (get_option('check_newly_added_files') ? "Yes" : "No") . '</strong></p>';
            echo '</div>';
        } else {
            echo '<a class="page-title-action" href="javascript:void(0);" onclick="window.location = window.location+\'&start=1\'">Start Scan</a>';
        }
        echo '</div>';
    }

    /**
     * Action executed at plugin activation.
     */
    public function plugin_activation()
    {
        global $wp_version;
        if (version_compare($wp_version, WCI_MINIMUM_WP_VERSION,
            '<')
        ) {
            $message   = 'WordPress version '
                         . WCI_MINIMUM_WP_VERSION
                         . ' or above required';
            $css_class = 'error';
            add_action('admin_notices', function () use ($css_class, $message) {
                printf('<div class="notice is-dismissible notice-%1$s"><p>%2$s</p></div>',
                    esc_attr($css_class),
                    esc_html(__($message, 'wp-core-integrity')));
            });
        }
    }

    public function plugin_deactivation()
    {
        wp_clear_scheduled_hook('verify_daily_wp_core_integrity');
    }

    public function setNotice($message, $class = 'success')
    {
        $this->message   = $message;
        $this->css_class = $class;
        $this->print_admin_notice_message();
    }

    /**
     * Serve custom message at WordPress Admin Dashboard.
     */
    public function print_admin_notice_message()
    {
        printf('<div class="notice notice-%1$s"><p>%2$s</p></div>',
            $this->css_class, $this->message);
    }

    /**
     * Check the core files via WordPress Official Release Checksum
     * excluding wp-content folder.
     *
     * @param $checksum
     * @param bool $throwException
     *
     * @return array|bool
     * @throws \Exception
     * @global $wp_version , $wp_local_package, $wp_locale
     *
     */
    public function check_files_changes_in_core_files($checksum, $throwException = false)
    {
        $errors = $this->verifyChecksums($checksum);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                if ($throwException) {
                    throw new \Exception($error);
                }
                $this->setNotice($error, 'warning');
            }
        }

        return [count($checksum), ((count($errors) > 0) ? false : true), $errors];
    }

    /**
     * Filter Checksum, from WordPress Official, for WordPress content folder inclusion
     *
     * @param bool $include_wp_content
     *
     * @return array
     * @internal param array $json response from serve with checksum
     */
    public function getChecksums($include_wp_content = false)
    {
        global $wp_version, $wp_local_package, $wp_locale;

        $wp_locale                  = isset($wp_local_package) ? $wp_local_package : 'en_US';
        $api_url                    = 'https://api.wordpress.org/core/checksums/1.0/?version=' . $wp_version . '&locale=' . $wp_locale;
        $json                       = json_decode(file_get_contents($api_url), true);
        $checksum                   = $json['checksums'];
        $include_wp_content_setting = ($include_wp_content) ?: get_option('include_wp_content', 0);

        if (empty($checksum)) {
            return [];
        }

        if (! $include_wp_content_setting) {
            $checksum = array_intersect_key($checksum,
                array_flip(preg_grep('/^wp-content.+/',
                    array_keys($checksum), PREG_GREP_INVERT)));
        }

        return $checksum;
    }

    /**
     * Verify against existing checksum
     *
     * @param $checksums
     *
     * @return array Errors (if any)
     */
    public function verifyChecksums($checksums)
    {
        $errors = [];
        if (count($checksums)) {
            foreach ($checksums as $file => $checksum) {
                $file_path = ABSPATH . $file;
                if (file_exists($file_path)) {
                    if (md5_file($file_path) !== $checksum) {
                        $errors[] = '<strong>Warning!</strong> Checksum for : ' . $file_path . ' does not match!';
                    }
                } else {
                    $errors[] = 'File: ' . $file_path . ' is missing';
                }
            }
        }

        return $errors;
    }

    public function check_new_files_in_core($checksums, $throwException = false)
    {
        $directories_to_scan = [];
        $extra_files         = [];
        $key_checksums       = array_keys($checksums);
        foreach ($key_checksums as $checksum) {
            if (strpos($checksum, '/')) {
                $dir = substr($checksum, 0, strrpos($checksum, '/'));
                if (! in_array($dir, $directories_to_scan)) {
                    $directories_to_scan[] = $dir;
                    $files[$dir]           = $this->transverse_folders(ABSPATH . $dir, false);
                    foreach ($files[$dir] as $file) {
                        if (is_dir(ABSPATH . $file)) {
                            continue;
                        }
                        if (! in_array($file, $key_checksums)) {
                            $extra_files[] = $file;
                            if ($throwException) {
                                throw new \Exception($file);
                            }
                            $this->setNotice('<strong>Error!</strong> New file added: ' . $file, 'error');
                        }
                    }
                }
            }
        }
        $result = count($extra_files);

        return [$result, (($result > 0) ? false : true), $extra_files];
    }

    public function transverse_folders($dir, $recursive = true)
    {
        $result = [];
        $root   = scandir($dir);
        foreach ($root as $value) {
            if ($value === '.' || $value === '..') {
                continue;
            }
            if (is_file("$dir/$value")) {
                $result[] = substr("$dir/$value", strlen(ABSPATH));
                continue;
            }
            if ($recursive) {
                foreach ($this->transverse_folders("$dir/$value") as $value) {
                    $result[] = substr($value, strlen(ABSPATH));
                }
            }
        }

        return $result;
    }

    public function my_cron_schedules($schedules)
    {
        if (! isset($schedules["1min"])) {
            $schedules["1min"] = array(
                'interval' => 1 * 60,
                'display'  => __('Once every 1 minute'),
            );
        }
        if (! isset($schedules["5min"])) {
            $schedules["5min"] = array(
                'interval' => 5 * 60,
                'display'  => __('Once every 5 minutes'),
            );
        }
        if (! isset($schedules["30min"])) {
            $schedules["30min"] = array(
                'interval' => 30 * 60,
                'display'  => __('Once every 30 minutes'),
            );
        }

        return $schedules;
    }

    public function cron_scan_job()
    {
        $checksum = $this->getChecksums(true);
        if (! $checksum) {
            return false;
        }
        $hasNoError                    = false;
        $errors                        = [];
        $count_check_new_files_in_core = null;
        $start_time                    = time();
        $message                       = '';
        try {
            list($count_check_files_changes_in_core_files, $hasNoError, $errors) = $this->check_files_changes_in_core_files($checksum);

            if ($hasNoError) {
                list($count_check_new_files_in_core, $hasNoError, $errors) = $this->check_new_files_in_core($checksum);

                if ($hasNoError) {
                    //ALL IS WELL
                    $message = 'No Vulnerability found: WordPress integrity test passed.';
                }
            }
        } catch (\Exception $exception) {
            $errors[] = 'Vulnerability found: ' . $exception->getMessage();
        }

        $end_time   = time();
        $email_body = '<h3>Scan Details:</h3>';
        if (count($errors) > 0) {
            $email_body .= '<p>Vulnerability found: </p>' . implode('<br/>', $errors);
        }
        if ($message) {
            $email_body .= '<p>' . $message . '</p>';
        }
        $email_body .= '<p><strong>Total files scanned: </strong>' . $count_check_files_changes_in_core_files . '<p>';
        $email_body .= '<p><strong>Total new malicious files found: </strong>' . ($count_check_new_files_in_core ?: 0) . '<p>';
        $email_body .= '<p><strong>Total time taken: </strong>' . ($end_time - $start_time) . ' seconds<p>';
        $email_body .= '<h3>Selected Scan Options:</h3>';
        $email_body .= '<p>Include "wp_content" to scan: <strong>Yes</strong></p>';
        $email_body .= '<p>Search for malicious files addition in the core: <strong>Yes</strong></p>';

        wp_mail(get_option('admin_email'), 'WordPress Core Integrity: Scan Report', $email_body,
            ['Content-Type: text/html; charset=UTF-8']);
    }
}
