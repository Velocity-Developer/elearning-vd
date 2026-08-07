<?php

defined('ABSPATH') || exit;

/**
 * Register REST routes for custom table resources.
 */
function elvd_register_rest_routes(): void
{
    foreach (elvd_rest_resources() as $resource => $config) {
        register_rest_route(
            ELVD_REST_NAMESPACE,
            '/' . $resource,
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => 'elvd_rest_list_items',
                    'permission_callback' => 'elvd_can_read_rest',
                    'args' => [
                        'resource' => [
                            'default' => $resource,
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => 'elvd_rest_create_item',
                    'permission_callback' => 'elvd_can_manage_rest',
                    'args' => [
                        'resource' => [
                            'default' => $resource,
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            ELVD_REST_NAMESPACE,
            '/' . $resource . '/(?P<id>[\d]+)',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => 'elvd_rest_get_item',
                    'permission_callback' => 'elvd_can_read_rest',
                    'args' => [
                        'id' => [
                            'required' => true,
                            'sanitize_callback' => 'absint',
                        ],
                        'resource' => [
                            'default' => $resource,
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => 'elvd_rest_update_item',
                    'permission_callback' => 'elvd_can_manage_rest',
                    'args' => [
                        'id' => [
                            'required' => true,
                            'sanitize_callback' => 'absint',
                        ],
                        'resource' => [
                            'default' => $resource,
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
                [
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => 'elvd_rest_delete_item',
                    'permission_callback' => 'elvd_can_manage_rest',
                    'args' => [
                        'id' => [
                            'required' => true,
                            'sanitize_callback' => 'absint',
                        ],
                        'resource' => [
                            'default' => $resource,
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
            ]
        );
    }

    register_rest_route(
        ELVD_REST_NAMESPACE,
        '/pengerjaan-quiz/kerjakan',
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'elvd_rest_submit_pengerjaan',
            'permission_callback' => 'elvd_can_submit_pengerjaan',
            'args' => [
                'quiz_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]
    );
}

/**
 * Only siswa may submit a quiz attempt; admin/guru only preview.
 */
function elvd_can_submit_pengerjaan(): bool
{
    if (! is_user_logged_in() || elvd_can_manage_rest()) {
        return false;
    }

    return in_array('siswa', (array) wp_get_current_user()->roles, true);
}

function elvd_rest_submit_pengerjaan(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;

    $quiz_id = absint($request->get_param('quiz_id'));
    $jawaban = $request->get_param('jawaban');

    if ($quiz_id < 1 || ! is_array($jawaban)) {
        return new WP_REST_Response(new WP_Error('elvd_invalid_payload', __('Data pengiriman tidak valid.', 'elearning-vd')), 400);
    }

    $mulai_pada = elvd_sanitize_datetime((string) $request->get_param('mulai_pada', ''));

    $nilai = $request->get_param('nilai');

    $data = [
        'quiz_id' => $quiz_id,
        'siswa_id' => get_current_user_id(),
        'jawaban' => wp_json_encode($jawaban),
        'nilai' => is_numeric($nilai) ? (float) $nilai : null,
        'status' => 'selesai',
        'mulai_pada' => $mulai_pada ?: current_time('mysql'),
        'selesai_pada' => current_time('mysql'),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ];

    $inserted = $wpdb->insert(elvd_table_name('elvd_pengerjaan_quiz'), $data, elvd_db_formats($data));

    if (! $inserted) {
        return new WP_REST_Response(new WP_Error('elvd_insert_failed', __('Gagal menyimpan hasil quiz.', 'elearning-vd')), 500);
    }

    return new WP_REST_Response(['id' => (int) $wpdb->insert_id]);
}
