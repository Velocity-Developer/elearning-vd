<?php

defined('ABSPATH') || exit;

function elvd_sanitize_datetime(string $value): ?string
{
    if ('' === trim($value)) {
        return null;
    }

    $timestamp = strtotime($value);

    return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
}
