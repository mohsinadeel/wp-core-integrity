<?php

defined('ABSPATH') or die('No script kiddies please!');

/*
 * @package: WP_Core_Integrity
 * @author: Mohsin Adeel <mohsin.adeel@yahoo.com>
 * @license: GPL3
 * @copyright: Mohsin Adeel
 *
 * Plugin Name: WordPress Core Integrity Checker
 * Plugin Url: https://github.com/mohsinadeel/wp-core-integrity
 * Description: A plugin to scan WordPress core directories to check the files integrity
 * Version: 1.0.4
 * Author: Mohsin Adeel
 * Author URI: http://www.inceptionsol.com/mohsin
 * License: GPL3
 * Text Domain: wp-core-integrity
 */
define('WP_CORE_INTEGRITY_VERSION', '1.0.4');
define('WP_CORE_INTEGRITY_MINIMUM_WP_VERSION', '4.0');
define('WP_CORE_INTEGRITY_PLUGIN_DIR', plugin_dir_path(__FILE__));

register_activation_hook(__FILE__,
    ['Inceptionsol\WP_Core_Integrity', 'plugin_activation']);

require_once WP_CORE_INTEGRITY_PLUGIN_DIR . 'class.wp-core-integrity.php';

$wp_core_integrity = new \Inceptionsol\WP_Core_Integrity();

add_action('init', [$wp_core_integrity, 'init']);
