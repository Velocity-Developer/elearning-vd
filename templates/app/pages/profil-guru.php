<?php

defined('ABSPATH') || exit;

$elvd_current_user = wp_get_current_user();

if (! in_array('guru', (array) $elvd_current_user->roles, true)) {
    echo '<div class="alert alert-danger">' . esc_html__('Halaman ini hanya dapat diakses oleh guru.', 'elearning-vd') . '</div>';
    return;
}

set_query_var('elvd_guru_id', (int) $elvd_current_user->ID);
set_query_var('elvd_guru_tab', 'profil');

include ELVD_PLUGIN_DIR . 'templates/app/pages/guru-profil.php';
