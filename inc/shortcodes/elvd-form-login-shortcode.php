<?php

defined('ABSPATH') || exit;

function elvd_form_login_shortcode(): string
{
    if (is_user_logged_in()) {
        return '';
    }

    ob_start();
    echo '<div class="elvd-login-form-container border border-primary p-4 rounded-md shadow-md">';
    wp_login_form([
        'redirect' => home_url(),
        'form_id' => 'elvd-login-form',
        'label_username' => __('Username', 'elearning-vd'),
        'label_password' => __('Password', 'elearning-vd'),
        'label_remember' => __('Remember Me', 'elearning-vd'),
        'label_log_in' => __('Log In', 'elearning-vd'),
        'remember' => true,
    ]);
    echo '</div>';
    return (string) ob_get_clean();
}
