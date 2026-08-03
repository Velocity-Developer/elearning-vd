<?php

defined('ABSPATH') || exit;

/**
 * @param array<string, string> $post_templates
 * @return array<string, string>
 */
function elvd_register_page_template(array $post_templates, WP_Theme $theme, ?WP_Post $post, string $post_type): array
{
    if ('page' !== $post_type) {
        return $post_templates;
    }

    $post_templates[ELVD::PAGE_TEMPLATE] = __('Elearning VD', ELVD::TEXT_DOMAIN);

    return $post_templates;
}
