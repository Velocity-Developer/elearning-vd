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

function elvd_handle_seed_data(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Anda tidak memiliki izin untuk melakukan aksi ini.', ELVD::TEXT_DOMAIN));
    }

    check_admin_referer('elvd_seed_data');

    $status = 'success';
    $allowed_items = [
        'users',
        'tahun_ajaran',
        'kelas',
        'mata_pelajaran',
        'jadwal_pelajaran',
        'materi',
        'tugas',
        'quiz',
    ];
    $raw_items = isset($_POST['elvd_seed_items']) ? (array) wp_unslash($_POST['elvd_seed_items']) : [];
    $selected_items = array_values(
        array_intersect(
            $allowed_items,
            array_map('sanitize_key', $raw_items)
        )
    );

    if (! function_exists('elvd_seed_data')) {
        $status = 'failed';
    } elseif (empty($selected_items)) {
        $status = 'empty';
    } else {
        $options = array_fill_keys($allowed_items, false);
        foreach ($selected_items as $item) {
            $options[$item] = true;
        }

        $result = elvd_seed_data($options);
        $has_error = false;

        array_walk_recursive(
            $result,
            static function ($value) use (&$has_error): void {
                if (0 === (int) $value) {
                    $has_error = true;
                }
            }
        );

        if ($has_error) {
            $status = 'partial';
        }
    }

    wp_safe_redirect(
        add_query_arg(
            [
                'page' => ELVD::SETTINGS_MENU_SLUG,
                'tab' => 'seeder',
                'elvd_seed' => $status,
            ],
            admin_url('admin.php')
        )
    );
    exit;
}
