<?php

defined('ABSPATH') || exit;

function elvd_rest_update_item(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;

    $resource = elvd_get_resource_from_request($request);
    if (is_wp_error($resource)) {
        return new WP_REST_Response($resource, 404);
    }

    $id = absint($request['id']);
    $data = elvd_sanitize_payload($request, $resource['fields']);
    if (empty($data)) {
        return new WP_REST_Response(new WP_Error('elvd_empty_payload', __('Data tidak boleh kosong.', 'elearning-vd')), 400);
    }

    $data['updated_at'] = current_time('mysql');
    $updated = $wpdb->update(
        elvd_table_name($resource['table']),
        $data,
        ['id' => $id],
        elvd_db_formats($data),
        ['%d']
    );

    if (false === $updated) {
        return new WP_REST_Response(new WP_Error('elvd_update_failed', __('Gagal memperbarui data.', 'elearning-vd')), 500);
    }

    return elvd_rest_get_item($request);
}
