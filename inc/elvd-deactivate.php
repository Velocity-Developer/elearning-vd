<?php

defined('ABSPATH') || exit;

/**
 * Keep data intact, only refresh permalinks.
 */
function elvd_deactivate(): void
{
    flush_rewrite_rules();
}
