<?php

defined('ABSPATH') || exit;

/**
 * @return array<string, mixed>|WP_Error
 */
function elvd_get_resource_from_request(WP_REST_Request $request)
{
    $resources = elvd_rest_resources();
    $resource = sanitize_key((string) $request->get_param('resource'));

    if (! isset($resources[$resource])) {
        return new WP_Error('elvd_invalid_resource', __('Resource tidak valid.', 'elearning-vd'));
    }

    return $resources[$resource];
}
