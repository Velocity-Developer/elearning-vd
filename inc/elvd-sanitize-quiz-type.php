<?php

defined('ABSPATH') || exit;

function elvd_sanitize_quiz_type(string $value): string
{
    return in_array($value, ['pilihan_ganda', 'essay'], true) ? $value : 'pilihan_ganda';
}
