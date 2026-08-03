<?php

defined('ABSPATH') || exit;

/**
 * Register learning content post types.
 */
function elvd_register_post_types(): void
{
    $types = [
        'elvd_tugas' => [
            'singular' => __('Tugas', 'elearning-vd'),
            'plural' => __('Tugas', 'elearning-vd'),
            'menu_icon' => 'dashicons-welcome-write-blog',
        ],
        'elvd_materi' => [
            'singular' => __('Materi', 'elearning-vd'),
            'plural' => __('Materi', 'elearning-vd'),
            'menu_icon' => 'dashicons-book-alt',
        ],
        'elvd_quiz' => [
            'singular' => __('Quiz', 'elearning-vd'),
            'plural' => __('Quiz', 'elearning-vd'),
            'menu_icon' => 'dashicons-forms',
        ],
    ];

    foreach ($types as $post_type => $config) {
        register_post_type(
            $post_type,
            [
                'labels' => [
                    'name' => $config['plural'],
                    'singular_name' => $config['singular'],
                    'add_new_item' => sprintf(__('Tambah %s', 'elearning-vd'), $config['singular']),
                    'edit_item' => sprintf(__('Edit %s', 'elearning-vd'), $config['singular']),
                    'new_item' => sprintf(__('Baru %s', 'elearning-vd'), $config['singular']),
                    'view_item' => sprintf(__('Lihat %s', 'elearning-vd'), $config['singular']),
                    'search_items' => sprintf(__('Cari %s', 'elearning-vd'), $config['plural']),
                    'not_found' => __('Tidak ada data ditemukan.', 'elearning-vd'),
                ],
                'public' => true,
                'show_in_menu' => ELVD::ADMIN_MENU_SLUG,
                'show_in_rest' => true,
                'menu_icon' => $config['menu_icon'],
                'supports' => ['title', 'editor', 'excerpt', 'author', 'thumbnail', 'custom-fields'],
                'has_archive' => true,
                'rewrite' => ['slug' => str_replace('elvd_', '', $post_type)],
            ]
        );
    }
}
