<?php

defined('ABSPATH') || exit;

function elvd_app_shortcode(): string
{

    ob_start();
    require ELVD_PLUGIN_DIR . 'templates/app/app.php';

    return (string) ob_get_clean();
}
