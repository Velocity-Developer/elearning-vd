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

foreach (
    [
        ELVD_PLUGIN_DIR . 'inc/*.php',
        ELVD_PLUGIN_DIR . 'inc/restapi/*.php',
        ELVD_PLUGIN_DIR . 'inc/shortcodes/*.php',
    ] as $elvd_pattern
) {
    foreach (glob($elvd_pattern) as $elvd_file) {
        require_once $elvd_file;
    }
}

register_activation_hook(__FILE__, 'elvd_activate');
register_deactivation_hook(__FILE__, 'elvd_deactivate');

add_action('init', 'elvd_register_roles');
add_action('init', 'elvd_register_post_types');
add_action('init', 'elvd_register_post_meta');
add_action('rest_api_init', 'elvd_register_rest_routes');
add_action('wp_enqueue_scripts', 'elvd_register_frontend_assets');

add_filter('theme_page_templates', 'elvd_register_page_template', 10, 4);
add_filter('template_include', 'elvd_load_page_template');

add_shortcode('elvd_app', 'elvd_app_shortcode');
