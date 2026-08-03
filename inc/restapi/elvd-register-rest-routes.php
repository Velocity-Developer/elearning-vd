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
}
