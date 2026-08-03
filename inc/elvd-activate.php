<?php

defined('ABSPATH') || exit;

/**
 * Prepare roles and custom database tables.
 */
function elvd_activate(): void
{
    elvd_register_roles();
    elvd_register_post_types();
    elvd_register_post_meta();
    elvd_create_tables();
    elvd_create_default_app_page();
    flush_rewrite_rules();
}
