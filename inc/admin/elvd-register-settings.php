<?php

defined('ABSPATH') || exit;

function elvd_register_settings(): void
{
    register_setting(
        ELVD::OPTION_GROUP,
        ELVD::OPTION_SCHOOL_NAME,
        [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => get_bloginfo('name'),
        ]
    );

    register_setting(
        ELVD::OPTION_GROUP,
        ELVD::OPTION_SCHOOL_LOGO_ID,
        [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]
    );

    register_setting(
        ELVD::OPTION_GROUP,
        ELVD::OPTION_ELEARNING_PAGE_ID,
        [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]
    );
}
