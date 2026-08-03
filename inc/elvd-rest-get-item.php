<?php

defined('ABSPATH') || exit;

function elvd_rest_get_item(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;

    $resource = elvd_get_resource_from_request($request);
    if (is_wp_error($resource)) {
        return new WP_REST_Response($resource, 404);
    }

    $table = elvd_table_name($resource['table']);
    $item = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", absint($request['id'])),
        ARRAY_A
    );

    if (! $item) {
        return new WP_REST_Response(new WP_Error('elvd_not_found', __('Data tidak ditemukan.', 'elearning-vd')), 404);
    }

    return new WP_REST_Response($item);
}
