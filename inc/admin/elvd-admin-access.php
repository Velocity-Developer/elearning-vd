<?php

defined('ABSPATH') || exit;

/**
 * Hide admin bar for non-administrators on frontend.
 */
function elvd_show_admin_bar(bool $show): bool
{
    if (!is_user_logged_in()) {
        return false;
    }

    $user = wp_get_current_user();
    $is_admin = in_array('administrator', (array) $user->roles, true);

    return $is_admin ? $show : false;
}

/**
 * Redirect non-administrators away from wp-admin to the elearning app.
 */
function elvd_restrict_admin_access(): void
{
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    $user = wp_get_current_user();
    $is_admin = in_array('administrator', (array) $user->roles, true);

    if (!$is_admin) {
        $app_page_id = absint(get_option(\ELVD::OPTION_ELEARNING_PAGE_ID, 0));
        $redirect_url = $app_page_id ? get_permalink($app_page_id) : home_url('/');

        wp_safe_redirect($redirect_url);
        exit;
    }
}