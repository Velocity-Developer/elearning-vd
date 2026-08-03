<?php

defined('ABSPATH') || exit;

function elvd_handle_create_app_page(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Anda tidak memiliki izin untuk melakukan aksi ini.', ELVD::TEXT_DOMAIN));
    }

    check_admin_referer('elvd_create_app_page');

    $page_id = elvd_create_default_app_page();
    $status = $page_id > 0 ? 'created' : 'failed';

    wp_safe_redirect(
        add_query_arg(
            [
                'page' => ELVD::SETTINGS_MENU_SLUG,
                'elvd_app_page' => $status,
            ],
            admin_url('admin.php')
        )
    );
    exit;
}
