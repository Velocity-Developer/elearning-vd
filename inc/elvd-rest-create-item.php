<?php

defined('ABSPATH') || exit;

function elvd_rest_create_item(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;

    $resource = elvd_get_resource_from_request($request);
    if (is_wp_error($resource)) {
        return new WP_REST_Response($resource, 404);
    }

    $data = elvd_sanitize_payload($request, $resource['fields']);
    if (empty($data)) {
        return new WP_REST_Response(new WP_Error('elvd_empty_payload', __('Data tidak boleh kosong.', 'elearning-vd')), 400);
    }

    $data['created_at'] = current_time('mysql');
    $data['updated_at'] = current_time('mysql');
    $formats = elvd_db_formats($data);
    $inserted = $wpdb->insert(elvd_table_name($resource['table']), $data, $formats);

    if (! $inserted) {
        return new WP_REST_Response(new WP_Error('elvd_insert_failed', __('Gagal menyimpan data.', 'elearning-vd')), 500);
    }

    $request->set_param('id', (int) $wpdb->insert_id);

    return elvd_rest_get_item($request);
}
