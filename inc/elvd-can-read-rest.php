<?php

defined('ABSPATH') || exit;

function elvd_can_read_rest(): bool
{
    return is_user_logged_in();
}
