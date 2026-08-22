<?php

/**
 * Plugin Name: Elearning VD
 * Plugin URI: https://velocitydeveloper.com/
 * Description: Plugin elearning untuk sekolah SD, SMP, dan SMA.
 * Version: 1.2.2
 * Author: Velocity Developer Team
 * Author URI: https://velocitydeveloper.com/
 * Text Domain: elearning-vd
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

define('ELVD_PLUGIN_FILE', __FILE__);
define('ELVD_PLUGIN_URL', plugins_url('', __FILE__));
define('ELVD_PLUGIN_VERSION', '1.2.2');

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';

define('ELVD_VERSION', ELVD::VERSION);
define('ELVD_PLUGIN_DIR', ELVD::plugin_dir());
define('ELVD_REST_NAMESPACE', ELVD::REST_NAMESPACE);

register_activation_hook(__FILE__, [ElearningVD\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [ElearningVD\Plugin::class, 'deactivate']);

ElearningVD\Plugin::boot();
