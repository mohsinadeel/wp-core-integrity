<?php


namespace Inceptionsol;


/**
 * Class WP_Core_Integrity
 * @package WP_Core_Integrity
 * @author: Mohsin Adeel <mohsin.adeel@yahoo.com>
 * @license: GPL3
 * @copyright: Mohsin Adeel
 */
class WP_Core_Integrity {

	/**
	 * Check if the plugin's hooks are initiated
	 * @var $initiated
	 */
	private $initiated;
	/**
	 * @var $class 'CSS class for Message i.e. success, warning, error and info.'
	 */
	private $class;
	/**
	 * @var $message 'Message for Admin User'
	 */
	private $message;

	public function __construct() {
		$this->initiated = false;
		$this->class     = 'success';
		$this->message   = '';
	}

	/**
	 * initialize plugins hooks from wordpress
	 */
	public function init() {
		if ( ! $this->initiated ) {
			$this->init_hooks();
		}
	}

	/**
	 * A place for Initializing WordPress hooks
	 */
	private function init_hooks() {
		$this->initiated = true;
		add_action( 'admin_menu', [ $this, 'register_plugin_menu' ] );
	}

	function plugin_admin_styles() {
		$handle = 'wpCoreIntegrityStyle';
		$src    = plugins_url( 'css/style.css', __FILE__ );
		wp_register_style( $handle, $src );
		wp_enqueue_style( $handle, $src, array(), false, false );
	}

	public function register_plugin_menu() {
		add_menu_page( 'Check WP Core Integrity',
			'WP Core Integrity',
			'manage_options',
			'wp-core-integrity',
			[ $this, 'set_plugin_options' ],
			plugins_url( 'images/icon.png', __FILE__ ) );
		$page = add_submenu_page( 'wp-core-integrity',
			'Scan WP Core Integrity',
			'Scan WP Core',
			'manage_options',
			'wp-core-integrity-check',
			[ $this, 'check_wp_integrity' ] );

		add_action( "admin_enqueue_scripts", [ $this, 'plugin_admin_styles' ] );
	}

	public function set_plugin_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		echo '<div id="wp-core-integrity">';
		echo '<p>Here is where the form would go if I actually had options.</p>';
		echo '</div>';
	}

	public function check_wp_integrity() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		echo '<div id="wp-core-integrity">';
		echo '<h3 class="padding-left">Checking WordPress Core Integrity</h3>';

		$start_time          = time();
		$total_files_checked = $this->check_files_changes_in_core_files();
		$end_time            = time();

		echo '<div class="integrity-results">';
		echo '<h3>Details:</h3>';
		echo '<p><strong>Total Files Checked: </strong>' . $total_files_checked . '<p>';
		echo '<p><strong>Total Time Taken: </strong>' . ( $end_time - $start_time ) . ' seconds<p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Action executed at plugin activation
	 */
	public function plugin_activation() {
		global $wp_version;
		if ( version_compare( $wp_version, WP_CORE_INTEGRITY_MINIMUM_WP_VERSION, '<' ) ) {
			$message = 'WordPress version ' . WP_CORE_INTEGRITY_MINIMUM_WP_VERSION . ' or above required';
			$class   = "error";
			add_action( 'admin_notices',
				function () use ( $class, $message ) {
					printf( '<div class="notice is-dismissible notice-%1$s"><p>%2$s</p></div>',
						esc_attr( $class ),
						esc_html( __( $message, 'wp-core-integrity' ) ) );
				} );
		}
	}

	public function setNotice( $message, $class = 'success' ) {
		$this->message = $message;
		$this->class   = $class;
		$this->print_admin_notice_message();
//		add_action( 'admin_notices', [ $this, 'print_admin_notice_message' ] );
	}

	/**
	 * Serve custom message at WordPress Admin Dashboard
	 */
	public function print_admin_notice_message() {
		printf( '<div class="notice is-dismissible notice-%1$s"><p>%2$s</p></div>',
			esc_attr( $this->class ),
			__( $this->message, 'wp-core-integrity' ) );
	}

	/**
	 * Check the core files via WordPress Official Release Checksum
	 * excluding wp-content folder
	 *
	 * @global $wp_version , $wp_local_package, $wp_locale
	 * @return bool
	 */
	function check_files_changes_in_core_files() {
		global $wp_version, $wp_local_package, $wp_locale;
		$wp_locale = isset( $wp_local_package ) ? $wp_local_package : 'en_US';
		$apiurl    = 'https://api.wordpress.org/core/checksums/1.0/?version=' . $wp_version . '&locale=' . $wp_locale;
		$json      = json_decode( file_get_contents( $apiurl ), true );
		$checksums = array_intersect_key( $json['checksums'],
			array_flip( preg_grep( '/^wp-content.+/', array_keys( $json['checksums'] ), PREG_GREP_INVERT ) ) );

		$hasNoErrors = true;
		$errors      = array();
		if ( ! $checksums ) {
			$this->setNotice( 'Unable to connect to WordPress for official checksum. Please check your internet connectivity.',
				'warning' );

			return count( $checksums );
		}
		foreach ( $checksums as $file => $checksum ) {
			$file_path = ABSPATH . $file;
			if ( file_exists( $file_path ) ) {
				if ( md5_file( $file_path ) !== $checksum ) {
					// do something when a checksum doesn't match
					$errors[]    = '<strong>HELP!</strong> Checksum for : ' . $file_path . ' does not match!';
					$hasNoErrors = false;
				}
			} else {
				$errors[]    = 'File: ' . $file_path . ' is missing';
				$hasNoErrors = false;
			}
		}
		if ( $hasNoErrors ) {
			$this->setNotice( '<strong>Congratulations!</strong>,WordPress integrity test passed.' );

			return count( $checksums );
		} else {
			if ( $errors ) {
				foreach ( $errors as $error ) {
					$this->setNotice( $error, 'warning' );
				}
			} else {
				$this->setNotice( '<strong>Warning!</strong> Your WordPress integrity is compromised.', 'warning' );
			}

			return count( $checksums );
		}

	}
}