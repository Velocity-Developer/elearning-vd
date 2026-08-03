<?php

defined('ABSPATH') || exit;

function elvd_sanitize_time(string $value): string
{
    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    return '00:00:00';
}
