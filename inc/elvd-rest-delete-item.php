<?php

defined('ABSPATH') || exit;

function elvd_rest_delete_item(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;

    $resource = elvd_get_resource_from_request($request);
    if (is_wp_error($resource)) {
        return new WP_REST_Response($resource, 404);
    }

    $deleted = $wpdb->delete(
        elvd_table_name($resource['table']),
        ['id' => absint($request['id'])],
        ['%d']
    );

    return new WP_REST_Response([
        'deleted' => (bool) $deleted,
    ]);
}
