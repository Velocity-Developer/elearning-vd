<?php

defined('ABSPATH') || exit;

/**
 * @param array<string, mixed> $data
 * @return array<int, string>
 */
function elvd_db_formats(array $data): array
{
    $formats = [];

    foreach ($data as $value) {
        if (is_int($value)) {
            $formats[] = '%d';
        } elseif (is_float($value)) {
            $formats[] = '%f';
        } else {
            $formats[] = '%s';
        }
    }

    return $formats;
}
