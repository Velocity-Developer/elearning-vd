<?php

declare(strict_types=1);

namespace ElearningVD;

defined('ABSPATH') || exit;

final class Mapel
{
    public static function role(): string
    {
        return 'mapel';
    }

    public static function role_label(): string
    {
        return __('Mata Pelajaran', 'elearning-vd');
    }

    /**
     * Dapatkan semua mata pelajaran.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function dapatkan_semua(): array
    {
        global $wpdb;

        $table = elvd_table_name('elvd_mata_pelajaran');

        $sql = $wpdb->prepare(
            "SELECT id, nama, kode, deskripsi, created_at, updated_at
             FROM {$table}
             ORDER BY nama ASC",
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
                'deskripsi' => $row['deskripsi'] !== null ? (string) $row['deskripsi'] : null,
                'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
                'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            ];
        }, $results));
    }

    /**
     * Dapatkan satu mata pelajaran berdasarkan ID.
     *
     * @param int $id ID mata pelajaran
     * @return array<string, mixed>|null
     */
    public static function dapatkan_berdasarkan_id(int $id): ?array
    {
        global $wpdb;

        $table = elvd_table_name('elvd_mata_pelajaran');

        $sql = $wpdb->prepare(
            "SELECT id, nama, kode, deskripsi, created_at, updated_at
             FROM {$table}
             WHERE id = %d
             LIMIT 1",
            $id,
        );

        $result = $wpdb->get_row($sql, ARRAY_A);

        if (! $result) {
            return null;
        }

        return [
            'id' => (int) $result['id'],
            'nama' => (string) $result['nama'],
            'kode' => $result['kode'] !== null ? (string) $result['kode'] : null,
            'deskripsi' => $result['deskripsi'] !== null ? (string) $result['deskripsi'] : null,
            'created_at' => isset($result['created_at']) ? (string) $result['created_at'] : null,
            'updated_at' => isset($result['updated_at']) ? (string) $result['updated_at'] : null,
        ];
    }

    /**
     * Cek apakah mata pelajaran sudah ada berdasarkan kode.
     *
     * @param string $kode Kode mata pelajaran
     * @return bool
     */
    public static function sudah_ada(string $kode): bool
    {
        global $wpdb;

        $table = elvd_table_name('elvd_mata_pelajaran');

        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE kode = %s",
                $kode,
            ),
        );

        return 0 < (int) $found;
    }

    /**
     * Dapatkan mata pelajaran yang diampu oleh guru.
     *
     * @param int $user_id ID user guru. Jika 0, gunakan user yang sedang login.
     * @return array<int, array<string, mixed>>
     */
    public static function dapatkan_oleh_guru(int $user_id = 0): array
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
            $user_id,
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