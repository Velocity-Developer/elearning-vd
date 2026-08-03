<?php

/**
 * Plugin Name: Elearning VD
 * Plugin URI: https://velocitydeveloper.com/
 * Description: Plugin elearning untuk sekolah SD, SMP, dan SMA.
 * Version: 1.0.0
 * Author: Velocity Developer
 * Author URI: https://velocitydeveloper.com/
 * Text Domain: elearning-vd
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('ELVD_VERSION', '1.0.0');
define('ELVD_PLUGIN_FILE', __FILE__);
define('ELVD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ELVD_REST_NAMESPACE', 'elvd/v1');

require_once ELVD_PLUGIN_DIR . 'vendor/autoload.php';

register_activation_hook(__FILE__, [ElearningVD\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [ElearningVD\Plugin::class, 'deactivate']);

ElearningVD\Plugin::boot();
