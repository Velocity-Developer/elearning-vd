<?php

declare(strict_types=1);

namespace ElearningVD;

defined('ABSPATH') || exit;

final class Siswa extends UserProfile
{
    public static function role(): string
    {
        return 'siswa';
    }

    public static function role_label(): string
    {
        return __('Siswa', 'elearning-vd');
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
            'nis' => [
                'label' => __('NIS', 'elearning-vd'),
                'type' => 'text',
                'required' => true,
            ],
            'kelas' => [
                'label' => __('Kelas', 'elearning-vd'),
                'type' => 'text',
            ],
            'tanggal_lahir' => [
                'label' => __('Tanggal Lahir', 'elearning-vd'),
                'type' => 'date',
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
