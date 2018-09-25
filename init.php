<?php

use Inceptionsol\Coreintegrity\core\WP_Core_Integrity;

define('WCI_BASE_FILE', __FILE__);
define('WCI_PATH', plugin_dir_path(__FILE__));
define('WCI_SOURCE_PATH', WCI_PATH . 'src' . DIRECTORY_SEPARATOR);
define('WCI_VIEW_PATH', WCI_PATH . 'src' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR);

require_once WCI_SOURCE_PATH . 'core' . DIRECTORY_SEPARATOR . 'class.wp-core-integrity.php';


register_activation_hook(__FILE__, ['Inceptionsol\Coreintegrity\core\WP_Core_Integrity', 'plugin_activation']);


$wp_core_integrity = new WP_Core_Integrity();

add_action('init', [$wp_core_integrity, 'init']);
