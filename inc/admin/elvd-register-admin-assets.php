<?php

defined('ABSPATH') || exit;

function elvd_register_admin_assets(string $hook_suffix): void
{
    unset($hook_suffix);

    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';

    if (ELVD::SETTINGS_MENU_SLUG !== $page) {
        return;
    }

    wp_enqueue_media();

    wp_register_style(
        'elvd-admin',
        ELVD_PLUGIN_URL . '/assets/admin.css',
        [],
        ELVD::VERSION,
        'all'
    );

    wp_enqueue_style('elvd-admin');

    wp_register_script(
        'elvd-admin',
        ELVD_PLUGIN_URL . '/assets/admin.js',
        ['jquery', 'media-editor'],
        ELVD::VERSION,
        true
    );

    wp_enqueue_script('elvd-admin');
}
