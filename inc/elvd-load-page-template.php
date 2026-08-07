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
        '^' . preg_quote($app_route_path, '#') . '/siswa-profil/([0-9]+)(?:/([^/]+))?/?$',
        'index.php?' . $query_target . '&' . ELVD::APP_PAGE_QUERY_VAR . '=siswa-profil&elvd_siswa_id=$matches[1]&elvd_siswa_tab=$matches[2]',
        'top'
    );

    add_rewrite_rule(
        '^' . preg_quote($app_route_path, '#') . '/quiz-workspace/([0-9]+)/?$',
        'index.php?' . $query_target . '&' . ELVD::APP_PAGE_QUERY_VAR . '=quiz-workspace&elvd_quiz_id=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        '^' . preg_quote($app_route_path, '#') . '/quiz-answer/([0-9]+)/?$',
        'index.php?' . $query_target . '&' . ELVD::APP_PAGE_QUERY_VAR . '=quiz-answer&elvd_quiz_id=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        '^' . preg_quote($app_route_path, '#') . '/quiz-form/([0-9]+)/?$',
        'index.php?' . $query_target . '&' . ELVD::APP_PAGE_QUERY_VAR . '=quiz-form&elvd_quiz_id=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        '^' . preg_quote($app_route_path, '#') . '/tugas-answer/([0-9]+)/?$',
        'index.php?' . $query_target . '&' . ELVD::APP_PAGE_QUERY_VAR . '=tugas-answer&elvd_tugas_id=$matches[1]',
        'top'
    );

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
    $query_vars[] = 'elvd_siswa_id';
    $query_vars[] = 'elvd_siswa_tab';
    $query_vars[] = 'elvd_quiz_id';
    $query_vars[] = 'elvd_tugas_id';

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
