<?php

/**
 * @package: WP_Core_Integrity
 * @author: Mohsin Adeel <mohsin.adeel@yahoo.com>
 * @license: GPL3
 * @copyright: Mohsin Adeel
 *
 * Plugin Name: WordPress Core Integrity Checker
 * Plugin Url: https://github.com/mohsinadeel/wp-core-integrity
 * Description: A plugin to scan WordPress core directories to check the files integrity
 * Version: 1.0.7
 * Author: Mohsin Adeel
 * Author URI: http://www.inceptionsol.com/mohsin , https://github.com/mohsinadeel
 * License: GPL3
 * Text Domain: wp-core-integrity
 */

defined('ABSPATH') or die('No script kiddies please!');


define('WCI_VERSION', '1.0.7');
define('WCI_MINIMUM_WP_VERSION', '4.0');

require_once 'init.php';
