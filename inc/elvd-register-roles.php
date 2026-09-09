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
        ]
    );

    $guru = get_role('guru');

    if ($guru) {
        foreach (
            [
                'read',
                'upload_files',
                'edit_posts',
                'delete_posts',
                'publish_posts',
                'edit_published_posts',
                'delete_published_posts',
            ] as $capability
        ) {
            $guru->add_cap($capability);
        }
    }
}
