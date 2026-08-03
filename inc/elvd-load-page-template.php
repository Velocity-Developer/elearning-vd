<?php

defined('ABSPATH') || exit;

function elvd_register_app_rewrite_rules(): void
{
    $app_route_path = ELVD::app_route_path();

    if ('' === $app_route_path) {
        return;
    }

    $page_id = absint(get_option(ELVD::OPTION_ELEARNING_PAGE_ID, 0));
    $query_target = $page_id > 0 ? 'page_id=' . $page_id : 'pagename=' . $app_route_path;

    add_rewrite_rule(
        '^' . preg_quote($app_route_path, '#') . '/([^/]+)/?$',
        'index.php?' . $query_target . '&' . ELVD::APP_PAGE_QUERY_VAR . '=$matches[1]',
        'top'
    );
}

/**
 * @param array<int, string> $query_vars
 * @return array<int, string>
 */
function elvd_register_app_query_vars(array $query_vars): array
{
    $query_vars[] = ELVD::APP_PAGE_QUERY_VAR;

    return $query_vars;
}

function elvd_flush_app_rewrite_rules($old_value, $value, string $option): void
{
    unset($old_value, $value, $option);

    elvd_register_app_rewrite_rules();
    flush_rewrite_rules();
}

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
