<?php

defined('ABSPATH') || exit;

function elvd_register_frontend_assets(): void
{
    wp_register_style(
        'elvd-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        [],
        '5.3.3'
    );

    wp_register_script(
        'elvd-alpine',
        'https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js',
        [],
        '3.14.8',
        true
    );
}
