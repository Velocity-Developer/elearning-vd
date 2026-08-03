<?php

defined('ABSPATH') || exit;

function elvd_load_page_template(string $template): string
{
    if (! is_page()) {
        return $template;
    }

    $page_template = get_page_template_slug();

    if (ELVD::PAGE_TEMPLATE !== $page_template) {
        return $template;
    }

    $plugin_template = ELVD_PLUGIN_DIR . ELVD::PAGE_TEMPLATE;

    return file_exists($plugin_template) ? $plugin_template : $template;
}
