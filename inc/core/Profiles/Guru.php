<?php

declare(strict_types=1);

namespace ElearningVD\Profiles;

defined('ABSPATH') || exit;

final class Guru extends UserProfile
{
    public static function role(): string
    {
        return 'guru';
    }

    public static function role_label(): string
    {
        return __('Guru', 'elearning-vd');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function default_fields(): array
    {
        return [
            'nama' => [
                'label' => __('Nama Lengkap', 'elearning-vd'),
                'type' => 'text',
                'target' => 'display_name',
                'required' => true,
            ],
            'email' => [
                'label' => __('Email', 'elearning-vd'),
                'type' => 'email',
                'target' => 'user_email',
                'required' => true,
            ],
            'nip' => [
                'label' => __('NIP', 'elearning-vd'),
                'type' => 'text',
            ],
            'mata_pelajaran' => [
                'label' => __('Mata Pelajaran', 'elearning-vd'),
                'type' => 'text',
            ],
            'telepon' => [
                'label' => __('No. Telepon', 'elearning-vd'),
                'type' => 'tel',
            ],
            'alamat' => [
                'label' => __('Alamat', 'elearning-vd'),
                'type' => 'textarea',
                'wrapper_class' => 'col-12',
            ],
        ];
    }
}
