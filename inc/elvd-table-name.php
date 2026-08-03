<?php

defined('ABSPATH') || exit;

function elvd_table_name(string $table): string
{
    global $wpdb;

    return $wpdb->prefix . $table;
}
