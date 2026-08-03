<?php

namespace ElearningVD;

defined('ABSPATH') || exit;

final class Plugin
{
    public static function boot(): void
    {
        add_action('init', 'elvd_register_roles');
        add_action('init', 'elvd_register_post_types');
        add_action('init', 'elvd_register_post_meta');
        add_action('rest_api_init', 'elvd_register_rest_routes');
        add_action('wp_enqueue_scripts', 'elvd_register_frontend_assets');
        add_action('admin_init', 'elvd_register_settings');
        add_action('admin_menu', 'elvd_register_admin_menu');
        add_action('admin_enqueue_scripts', 'elvd_register_admin_assets');
        add_action('admin_post_elvd_create_app_page', 'elvd_handle_create_app_page');

        add_filter('theme_page_templates', 'elvd_register_page_template', 10, 4);
        add_filter('template_include', 'elvd_load_page_template');

        add_shortcode('elvd_app', 'elvd_app_shortcode');
    }

    public static function activate(): void
    {
        elvd_activate();
    }

    public static function deactivate(): void
    {
        elvd_deactivate();
    }
}
