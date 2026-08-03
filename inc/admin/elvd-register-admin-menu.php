<?php

defined('ABSPATH') || exit;

function elvd_register_admin_menu(): void
{
    add_menu_page(
        __('Pengaturan Elearning', ELVD::TEXT_DOMAIN),
        __('Elearning', ELVD::TEXT_DOMAIN),
        'manage_options',
        ELVD::ADMIN_MENU_SLUG,
        'elvd_render_settings_page',
        'dashicons-welcome-learn-more',
        56
    );
}
