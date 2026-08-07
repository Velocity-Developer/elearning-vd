<?php

defined('ABSPATH') || exit;

function elvd_form_login_shortcode(): string
{
    if (is_user_logged_in()) {
        return '';
    }

    $page_id = absint(get_option(ELVD::OPTION_ELEARNING_PAGE_ID, 0));
    $redirect = $page_id ? get_permalink($page_id) : home_url('/');

    ob_start();
    echo '<div class="elvd-login-form-container border border-secondary p-4 rounded shadow-md">';
    wp_login_form([
        'redirect' => $redirect,
        'form_id' => 'elvd-login-form',
        'label_username' => __('Username', 'elearning-vd'),
        'label_password' => __('Password', 'elearning-vd'),
        'label_remember' => __('Remember Me', 'elearning-vd'),
        'label_log_in' => __('Log In', 'elearning-vd'),
        'remember' => true,
    ]);
    echo '</div>';
    $form = (string) ob_get_clean();

    return strtr($form, [
        '<p class="' => '<p class="d-block ',
        '<label for="' => '<label class="form-label" for="',
        'class="input"' => 'class="input form-control"',
        'class="form-control"' => 'class="form-control form-control-sm"',
        'class="button button-primary"' => 'class="button button-primary btn-primary btn w-100 py-3 px-4 rounded"',
    ]);
}
