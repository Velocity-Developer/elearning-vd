<?php

defined('ABSPATH') || exit;

function elvd_can_manage_meta(): bool
{
    return elvd_can_manage_rest();
}
