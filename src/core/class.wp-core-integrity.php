<?php

namespace mohsinadeel\wpcoreintegrity\core;

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

    /**
     * A place for Initializing WordPress hooks.
     */
    private function init_hooks()
    {
        $this->initiated = true;
        add_action('admin_menu', [$this, 'register_plugin_menu']);
    }

    public function get_initiated()
    {
        return $this->initiated;
    }

    public function plugin_admin_styles()
    {
        $handle = 'wpCoreIntegrityStyle';
        $src    = plugins_url('css/style.css', __FILE__);
        wp_register_style($handle, $src);
        wp_enqueue_style($handle, $src, [], false, false);
    }

    public function register_plugin_menu()
    {
        add_menu_page('Check WP Core Integrity', 'WP Core Integrity',
            'manage_options', 'wp-core-integrity',
            [$this, 'set_plugin_options'],
            'dashicons-lock');
        $page = add_submenu_page('wp-core-integrity', 'Scan WP Core Integrity',
            'Scan WP Core', 'manage_options', 'wp-core-integrity-check',
            [$this, 'check_wp_integrity']);

        add_action('admin_enqueue_scripts', [$this, 'plugin_admin_styles']);
    }

    public function set_plugin_options()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        echo '<div id="wp-core-integrity">';
        echo '<p>Here is where the form would go if I actually had options.</p>';
        echo '</div>';
    }

    public function check_wp_integrity()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        $this->checksum = $this->getChecksums();
        if (! $this->checksum) {
            $this->setNotice('Unable to connect to WordPress for official checksum. Please check your internet connectivity.',
                'warning');

            return false;
        }
        $hasNoError                    = false;
        $check_newly_added_files       = true;
        $count_check_new_files_in_core = null;

        echo '<div id="wp-core-integrity">';
        echo '<h3 class="padding-left">Checking WordPress Core Integrity</h3>';

        $start_time = time();
        list($count_check_files_changes_in_core_files, $hasNoError) = $this->check_files_changes_in_core_files($this->checksum);

        if ($hasNoError) {
            if ($check_newly_added_files) {
                list($count_check_new_files_in_core, $hasNoError) = $this->check_new_files_in_core($this->checksum);
            }

            if ($hasNoError) {
                $this->setNotice('<strong>Congratulations!</strong>,WordPress integrity test passed.');
            }
        }

        $end_time = time();

        echo '<div class="integrity-results">';
        echo '<h3>Scan Details:</h3>';

        echo '<p><strong>Total Files Checked: </strong>' . $count_check_files_changes_in_core_files . '<p>';

        if ($count_check_new_files_in_core) {
            echo '<p><strong>Total New Files Added: </strong>' . $count_check_new_files_in_core . '<p>';
        }

        echo '<p><strong>Total Time Taken: </strong>' . ($end_time - $start_time) . ' seconds<p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Action executed at plugin activation.
     */
    public function plugin_activation()
    {
        global $wp_version;
        if (version_compare($wp_version, WP_CORE_INTEGRITY_MINIMUM_WP_VERSION,
            '<')
        ) {
            $message   = 'WordPress version '
                         . WP_CORE_INTEGRITY_MINIMUM_WP_VERSION
                         . ' or above required';
            $css_class = 'error';
            add_action('admin_notices', function () use ($css_class, $message) {
                printf('<div class="notice is-dismissible notice-%1$s"><p>%2$s</p></div>',
                    esc_attr($css_class),
                    esc_html(__($message, 'wp-core-integrity')));
            });
        }
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
     * @global $wp_version , $wp_local_package, $wp_locale
     *
     * @return bool
     */
    public function check_files_changes_in_core_files($checksum)
    {
        $errors = $this->verifyChecksums($checksum);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->setNotice($error, 'warning');
            }
        }

        return [count($checksum), ((count($errors) > 0) ? false : true)];
    }

    /**
     * Filter Checksum, from WordPress Official, for WordPress content folder inclusion
     *
     * @return array
     * @internal param array $json response from serve with checksum     *
     */
    public function getChecksums()
    {
        global $wp_version, $wp_local_package, $wp_locale;

        $wp_locale          = isset($wp_local_package) ? $wp_local_package : 'en_US';
        $api_url            = 'https://api.wordpress.org/core/checksums/1.0/?version=' . $wp_version . '&locale=' . $wp_locale;
        $json               = json_decode(file_get_contents($api_url), true);
        $checksum           = $json['checksums'];
        $exclude_wp_content = true;

        if (empty($checksum)) {
            return [];
        }

        if ($exclude_wp_content) {
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

    public function check_new_files_in_core($checksums)
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
                            $this->setNotice('<strong>Error!</strong> New file added: ' . $file, 'error');
                        }
                    }

                }
            }
        }
        $result = count($extra_files);

        return [$result, (($result > 0) ? false : true)];
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
}
