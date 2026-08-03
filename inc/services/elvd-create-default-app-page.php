<?php

defined('ABSPATH') || exit;

function elvd_create_default_app_page(): int
{
    $page_id = absint(get_option(ELVD::OPTION_ELEARNING_PAGE_ID, 0));

    if ($page_id > 0 && elvd_is_valid_app_page($page_id)) {
        elvd_prepare_app_page($page_id);

        return $page_id;
    }

    $page = get_page_by_path(ELVD::APP_PAGE_SLUG);

    if ($page instanceof WP_Post && 'trash' !== $page->post_status) {
        elvd_prepare_app_page($page->ID);
        update_option(ELVD::OPTION_ELEARNING_PAGE_ID, $page->ID);

        return $page->ID;
    }

    $created_page_id = wp_insert_post(
        [
            'post_title' => ELVD::APP_PAGE_TITLE,
            'post_name' => ELVD::APP_PAGE_SLUG,
            'post_content' => ELVD::APP_SHORTCODE,
            'post_status' => 'publish',
            'post_type' => 'page',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ],
        true
    );

    if (is_wp_error($created_page_id)) {
        return 0;
    }

    $created_page_id = absint($created_page_id);

    elvd_prepare_app_page($created_page_id);
    update_option(ELVD::OPTION_ELEARNING_PAGE_ID, $created_page_id);

    return $created_page_id;
}

function elvd_is_valid_app_page(int $page_id): bool
{
    return 'page' === get_post_type($page_id) && 'trash' !== get_post_status($page_id);
}

function elvd_prepare_app_page(int $page_id): void
{
    update_post_meta($page_id, '_wp_page_template', ELVD::PAGE_TEMPLATE);

    $page = get_post($page_id);

    if (! $page instanceof WP_Post || has_shortcode($page->post_content, 'elvd_app')) {
        return;
    }

    wp_update_post(
        [
            'ID' => $page_id,
            'post_content' => ELVD::APP_SHORTCODE,
        ]
    );
}
