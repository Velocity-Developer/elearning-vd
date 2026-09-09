<?php

defined('ABSPATH') || exit;

/**
 * Register extra REST fields for learning content post types.
 */
function elvd_register_rest_fields(): void
{
    foreach (['elvd_quiz', 'elvd_tugas', 'elvd_materi'] as $post_type) {
        register_rest_field(
            $post_type,
            'author_name',
            [
                'get_callback' => static function (array $object): string {
                    $author_id = absint($object['author'] ?? 0);
                    $author = $author_id > 0 ? get_userdata($author_id) : null;

                    if (! $author) {
                        return '';
                    }

                    return '' !== trim((string) $author->display_name)
                        ? (string) $author->display_name
                        : (string) $author->user_login;
                },
                'schema' => [
                    'type' => 'string',
                    'context' => ['view', 'edit'],
                ],
            ]
        );
    }
}
