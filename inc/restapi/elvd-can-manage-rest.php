<?php

defined('ABSPATH') || exit;

function elvd_can_manage_rest(): bool
{
    $user = wp_get_current_user();

    return current_user_can('manage_options') || in_array('guru', (array) $user->roles, true);
}
