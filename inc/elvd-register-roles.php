<?php

defined('ABSPATH') || exit;

/**
 * Register custom user roles for school users.
 */
function elvd_register_roles(): void
{
    add_role(
        'siswa',
        __('Siswa', 'elearning-vd'),
        [
            'read' => true,
        ]
    );

    add_role(
        'guru',
        __('Guru', 'elearning-vd'),
        [
            'read' => true,
            'upload_files' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'publish_posts' => true,
        ]
    );
}
