<?php

defined('ABSPATH') || exit;

/**
 * Register prefixed post meta used by learning content.
 */
function elvd_register_post_meta(): void
{
    $shared_meta = [
        'elvd_kelas_id' => [
            'type' => 'integer',
            'single' => true,
            'sanitize_callback' => 'absint',
        ],
        'elvd_mata_pelajaran_id' => [
            'type' => 'integer',
            'single' => true,
            'sanitize_callback' => 'absint',
        ],
    ];

    $post_type_meta = [
        'elvd_tugas' => array_merge(
            $shared_meta,
            [
                'elvd_deadline' => [
                    'type' => 'string',
                    'single' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'elvd_instruksi' => [
                    'type' => 'string',
                    'single' => true,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ]
        ),
        'elvd_materi' => array_merge(
            $shared_meta,
            [
                'elvd_tahun_ajaran_id' => [
                    'type' => 'integer',
                    'single' => true,
                    'sanitize_callback' => 'absint',
                ],
                'elvd_file_url' => [
                    'type' => 'string',
                    'single' => true,
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ]
        ),
        'elvd_quiz' => array_merge(
            $shared_meta,
            [
                'elvd_quiz_tipe' => [
                    'type' => 'string',
                    'single' => true,
                    'sanitize_callback' => 'elvd_sanitize_quiz_type',
                ],
                'elvd_durasi_menit' => [
                    'type' => 'integer',
                    'single' => true,
                    'sanitize_callback' => 'absint',
                ],
                'elvd_pertanyaan' => [
                    'type' => 'string',
                    'single' => true,
                    'sanitize_callback' => 'wp_kses_post',
                ],
            ]
        ),
    ];

    foreach ($post_type_meta as $post_type => $meta_fields) {
        foreach ($meta_fields as $meta_key => $args) {
            register_post_meta(
                $post_type,
                $meta_key,
                array_merge(
                    [
                        'show_in_rest' => true,
                        'auth_callback' => 'elvd_can_manage_meta',
                    ],
                    $args
                )
            );
        }
    }
}
