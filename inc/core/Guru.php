<?php

declare(strict_types=1);

namespace ElearningVD;

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
        $fields = get_option(\ELVD::OPTION_GURU_PROFILE_FIELDS, []);

        if (is_array($fields) && [] !== $fields) {
            return $fields;
        }

        return \elvd_default_guru_profile_fields();
    }
}
