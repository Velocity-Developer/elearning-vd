<?php

defined('ABSPATH') || exit;

/**
 * @param array<string, string> $fields
 * @return array<string, mixed>
 */
function elvd_sanitize_payload(WP_REST_Request $request, array $fields): array
{
    $data = [];

    foreach ($fields as $field => $type) {
        if (! $request->has_param($field)) {
            continue;
        }

        $value = $request->get_param($field);

        switch ($type) {
            case 'int':
                $data[$field] = absint($value);
                break;
            case 'decimal':
                $data[$field] = is_numeric($value) ? (float) $value : null;
                break;
            case 'textarea':
                $data[$field] = sanitize_textarea_field((string) $value);
                break;
            case 'key':
                $data[$field] = sanitize_key((string) $value);
                break;
            case 'date':
                $data[$field] = elvd_sanitize_date((string) $value);
                break;
            case 'time':
                $data[$field] = elvd_sanitize_time((string) $value);
                break;
            case 'datetime':
                $data[$field] = elvd_sanitize_datetime((string) $value);
                break;
            case 'json':
                $data[$field] = wp_json_encode($value);
                break;
            case 'text':
            default:
                $data[$field] = sanitize_text_field((string) $value);
                break;
        }
    }

    return $data;
}
