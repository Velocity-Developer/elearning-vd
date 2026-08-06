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
        'elvd_quiz_question' => [
            'singular' => __('Quiz Question', 'elearning-vd'),
            'plural' => __('Quiz Questions', 'elearning-vd'),
            'menu_icon' => 'dashicons-format-chat',
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

/**
 * Server-side filter for elvd_quiz REST list.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function elvd_filter_quiz_rest_query(array $args, WP_REST_Request $request): array
{
    $tipe = sanitize_text_field((string) $request->get_param('elvd_filter_tipe'));
    $kelas = absint((int) $request->get_param('elvd_filter_kelas'));

    $meta_query = [];

    if (in_array($tipe, ['pilihan_ganda', 'essay'], true)) {
        $meta_query[] = [
            'key' => 'elvd_quiz_tipe',
            'value' => $tipe,
        ];
    }

    if ($kelas > 0) {
        $meta_query[] = [
            'key' => 'elvd_kelas_id',
            'value' => $kelas,
            'compare' => '=',
        ];
    }

    if ([] !== $meta_query) {
        $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
    }

    return $args;
}
