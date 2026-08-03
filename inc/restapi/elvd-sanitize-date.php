<?php

defined('ABSPATH') || exit;

function elvd_sanitize_date(string $value): string
{
    $timestamp = strtotime($value);

    return $timestamp ? gmdate('Y-m-d', $timestamp) : current_time('Y-m-d');
}
