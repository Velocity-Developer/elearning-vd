<?php

defined('ABSPATH') || exit;

function elvd_rest_list_items(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;

    $resource = elvd_get_resource_from_request($request);
    if (is_wp_error($resource)) {
        return new WP_REST_Response($resource, 404);
    }

    $page = max(1, absint($request->get_param('page') ?: 1));
    $per_page = min(100, max(1, absint($request->get_param('per_page') ?: 20)));
    $offset = ($page - 1) * $per_page;
    $table = elvd_table_name($resource['table']);

    $items = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset),
        ARRAY_A
    );
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

    $response = new WP_REST_Response($items);
    $response->header('X-WP-Total', (string) $total);
    $response->header('X-WP-TotalPages', (string) max(1, (int) ceil($total / $per_page)));

    return $response;
}
