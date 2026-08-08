<?php

namespace ElearningVD;

defined('ABSPATH') || exit;

final class Plugin
{
    public static function boot(): void
    {
        class_exists(Siswa::class);
        class_exists(Guru::class);

        require_once ELVD_PLUGIN_DIR . 'inc/admin/elvd-admin-access.php';
        require_once ELVD_PLUGIN_DIR . 'inc/core/class-elvd-updater.php';

        if (is_admin()) {
            \ELVD_Updater::register();
        }

        add_action('init', 'elvd_register_roles');
        add_action('init', 'elvd_register_post_types');
        add_action('init', 'elvd_register_post_meta');
        add_action('init', 'elvd_register_app_rewrite_rules');
        add_action('rest_api_init', 'elvd_register_rest_routes');
        add_filter('rest_elvd_quiz_query', 'elvd_filter_quiz_rest_query', 10, 2);
        add_action('wp_enqueue_scripts', 'elvd_register_frontend_assets');
        add_action('admin_init', 'elvd_register_settings');
        add_action('admin_menu', 'elvd_register_admin_menu');
        add_action('admin_enqueue_scripts', 'elvd_register_admin_assets');
        add_action('admin_post_elvd_create_app_page', 'elvd_handle_create_app_page');
        add_action('admin_post_elvd_seed_data', 'elvd_handle_seed_data');

        // Admin bar & wp-admin access restriction for non-admins
        add_filter('show_admin_bar', 'elvd_show_admin_bar');
        add_action('admin_init', 'elvd_restrict_admin_access', 1);
        add_action('update_option_' . \ELVD::OPTION_ELEARNING_PAGE_ID, 'elvd_flush_app_rewrite_rules', 10, 3);

        add_filter('theme_page_templates', 'elvd_register_page_template', 10, 4);
        add_filter('template_include', 'elvd_load_page_template');
        add_filter('query_vars', 'elvd_register_app_query_vars');

        add_shortcode('elvd_app', 'elvd_app_shortcode');
        add_shortcode('elvd-form-login', 'elvd_form_login_shortcode');
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
