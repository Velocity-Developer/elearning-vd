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
        $fields = get_option(\ELVD::OPTION_SISWA_PROFILE_FIELDS, []);

        if (is_array($fields) && [] !== $fields) {
            return $fields;
        }

        return \elvd_default_siswa_profile_fields();
    }
}
