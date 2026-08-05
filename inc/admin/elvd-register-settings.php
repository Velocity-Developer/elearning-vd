<?php

defined('ABSPATH') || exit;

function elvd_register_settings(): void
{
    register_setting(
        ELVD::OPTION_GROUP,
        ELVD::OPTION_SCHOOL_NAME,
        [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );

    register_setting(
        ELVD::OPTION_GROUP,
        ELVD::OPTION_SCHOOL_LOGO_ID,
        [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        ]
    );

    register_setting(
        ELVD::OPTION_GROUP,
        ELVD::OPTION_ELEARNING_PAGE_ID,
        [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        ]
    );

    register_setting(
        ELVD::OPTION_GROUP_SISWA_PROFILE,
        ELVD::OPTION_SISWA_PROFILE_FIELDS,
        [
            'type' => 'array',
            'sanitize_callback' => 'elvd_sanitize_siswa_profile_fields',
        ]
    );

    register_setting(
        ELVD::OPTION_GROUP_GURU_PROFILE,
        ELVD::OPTION_GURU_PROFILE_FIELDS,
        [
            'type' => 'array',
            'sanitize_callback' => 'elvd_sanitize_guru_profile_fields',
        ]
    );
}

/**
 * @return array<string, array<string, mixed>>
 */
function elvd_default_siswa_profile_fields(): array
{
    return [
        'nama' => [
            'label' => __('Nama Lengkap', ELVD::TEXT_DOMAIN),
            'type' => 'text',
            'target' => 'display_name',
            'required' => true,
        ],
        'email' => [
            'label' => __('Email', ELVD::TEXT_DOMAIN),
            'type' => 'email',
            'target' => 'user_email',
            'required' => true,
        ],
        'nis' => [
            'label' => __('NIS', ELVD::TEXT_DOMAIN),
            'type' => 'text',
            'required' => true,
        ],
        'kelas' => [
            'label' => __('Kelas', ELVD::TEXT_DOMAIN),
            'type' => 'text',
        ],
        'tanggal_lahir' => [
            'label' => __('Tanggal Lahir', ELVD::TEXT_DOMAIN),
            'type' => 'date',
        ],
        'telepon' => [
            'label' => __('No. Telepon', ELVD::TEXT_DOMAIN),
            'type' => 'tel',
        ],
        'alamat' => [
            'label' => __('Alamat', ELVD::TEXT_DOMAIN),
            'type' => 'textarea',
            'wrapper_class' => 'col-12',
        ],
    ];
}

/**
 * @return array<string, array<string, mixed>>
 */
function elvd_default_guru_profile_fields(): array
{
    return [
        'nama' => [
            'label' => __('Nama Lengkap', ELVD::TEXT_DOMAIN),
            'type' => 'text',
            'target' => 'display_name',
            'required' => true,
        ],
        'email' => [
            'label' => __('Email', ELVD::TEXT_DOMAIN),
            'type' => 'email',
            'target' => 'user_email',
            'required' => true,
        ],
        'nip' => [
            'label' => __('NIP', ELVD::TEXT_DOMAIN),
            'type' => 'text',
        ],
        'mata_pelajaran' => [
            'label' => __('Mata Pelajaran', ELVD::TEXT_DOMAIN),
            'type' => 'text',
        ],
        'telepon' => [
            'label' => __('No. Telepon', ELVD::TEXT_DOMAIN),
            'type' => 'tel',
        ],
        'alamat' => [
            'label' => __('Alamat', ELVD::TEXT_DOMAIN),
            'type' => 'textarea',
            'wrapper_class' => 'col-12',
        ],
    ];
}

/**
 * @param mixed $fields
 * @return array<string, array<string, mixed>>
 */
function elvd_sanitize_siswa_profile_fields($fields): array
{
    return elvd_sanitize_profile_fields($fields, elvd_default_siswa_profile_fields());
}

/**
 * @param mixed $fields
 * @return array<string, array<string, mixed>>
 */
function elvd_sanitize_guru_profile_fields($fields): array
{
    return elvd_sanitize_profile_fields($fields, elvd_default_guru_profile_fields());
}

/**
 * @param mixed $fields
 * @param array<string, array<string, mixed>> $fallback
 * @return array<string, array<string, mixed>>
 */
function elvd_sanitize_profile_fields($fields, array $fallback): array
{
    if (! is_array($fields)) {
        return $fallback;
    }

    $allowed_types = ['text', 'email', 'number', 'date', 'tel', 'textarea'];
    $allowed_targets = ['meta', 'display_name', 'user_email'];
    $sanitized = [];

    foreach ($fields as $field) {
        if (! is_array($field)) {
            continue;
        }

        $key = sanitize_key((string) ($field['key'] ?? ''));

        if ('' === $key || isset($sanitized[$key])) {
            continue;
        }

        $label = sanitize_text_field((string) ($field['label'] ?? $key));
        $type = sanitize_key((string) ($field['type'] ?? 'text'));
        $target = sanitize_key((string) ($field['target'] ?? 'meta'));

        if (! in_array($type, $allowed_types, true)) {
            $type = 'text';
        }

        if (! in_array($target, $allowed_targets, true)) {
            $target = 'meta';
        }

        $sanitized[$key] = [
            'label' => '' !== $label ? $label : $key,
            'type' => $type,
            'required' => ! empty($field['required']),
        ];

        if ('meta' !== $target) {
            $sanitized[$key]['target'] = $target;
        }

        if ('textarea' === $type) {
            $sanitized[$key]['wrapper_class'] = 'col-12';
        }
    }

    return [] !== $sanitized ? $sanitized : $fallback;
}
