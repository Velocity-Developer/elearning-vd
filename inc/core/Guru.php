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

    /**
     * Dapatkan daftar mata pelajaran yang diampu oleh guru.
     *
     * @param int $user_id ID user guru. Jika 0, gunakan user yang sedang login.
     * @return array<int, array<string, mixed>>
     */
    public static function dapatkan_mapel(int $user_id = 0): array
    {
        global $wpdb;

        $user_id = 0 < $user_id ? (int) $user_id : (int) get_current_user_id();

        if (0 >= $user_id) {
            return [];
        }

        $table_pivot = elvd_table_name('elvd_mata_pelajaran_guru');
        $table_mapel = elvd_table_name('elvd_mata_pelajaran');

        $sql = $wpdb->prepare(
            "SELECT m.id, m.nama, m.kode, m.created_at, m.updated_at
             FROM {$table_mapel} m
             INNER JOIN {$table_pivot} p ON p.mapel_id = m.id
             WHERE p.user_id = %d
             ORDER BY m.nama ASC",
            $user_id
        );

        $results = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($results)) {
            return [];
        }

        return array_values(array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'nama' => (string) $row['nama'],
                'kode' => $row['kode'] !== null ? (string) $row['kode'] : null,
                'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
                'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            ];
        }, $results));
    }
}
