<?php

declare(strict_types=1);

namespace ElearningVD;

defined('ABSPATH') || exit;

final class JadwalPelajaran
{
    /**
     * Dapatkan jadwal pelajaran berdasarkan guru.
     *
     * @param int $guru_id ID user guru. Jika 0, gunakan user yang sedang login.
     * @return array<int, array<string, mixed>>
     */
    public static function dapatkan_oleh_guru(int $guru_id = 0): array
    {
        global $wpdb;

        $guru_id = 0 < $guru_id ? (int) $guru_id : (int) get_current_user_id();

        if (0 >= $guru_id) {
            return [];
        }

        $table_jadwal = elvd_table_name('elvd_jadwal_pelajaran');
        $table_kelas = elvd_table_name('elvd_kelas');
        $table_mapel = elvd_table_name('elvd_mata_pelajaran');

        $sql = $wpdb->prepare(
            "SELECT j.id, j.kelas_id, j.mata_pelajaran_id, j.guru_id, j.tahun_ajaran_id, j.hari, j.jam_mulai, j.jam_selesai, j.created_at, j.updated_at,
                    k.nama AS kelas_nama, k.tingkat AS kelas_tingkat,
                    mp.nama AS mata_pelajaran_nama, mp.kode AS mata_pelajaran_kode,
                    u.display_name AS guru_nama
             FROM {$table_jadwal} j
             LEFT JOIN {$table_kelas} k ON k.id = j.kelas_id
             LEFT JOIN {$table_mapel} mp ON mp.id = j.mata_pelajaran_id
             LEFT JOIN {$wpdb->users} u ON u.ID = j.guru_id
             WHERE j.guru_id = %d
             ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai ASC",
            $guru_id
        );

        $results = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($results)) {
            return [];
        }

        return array_values(array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'kelas_id' => (int) $row['kelas_id'],
                'mata_pelajaran_id' => (int) $row['mata_pelajaran_id'],
                'guru_id' => (int) $row['guru_id'],
                'tahun_ajaran_id' => isset($row['tahun_ajaran_id']) ? (int) $row['tahun_ajaran_id'] : 0,
                'hari' => (string) $row['hari'],
                'jam_mulai' => (string) $row['jam_mulai'],
                'jam_selesai' => (string) $row['jam_selesai'],
                'kelas_nama' => isset($row['kelas_nama']) ? (string) $row['kelas_nama'] : '',
                'kelas_tingkat' => isset($row['kelas_tingkat']) ? (string) $row['kelas_tingkat'] : '',
                'mata_pelajaran_nama' => isset($row['mata_pelajaran_nama']) ? (string) $row['mata_pelajaran_nama'] : '',
                'mata_pelajaran_kode' => $row['mata_pelajaran_kode'] !== null ? (string) $row['mata_pelajaran_kode'] : null,
                'guru_nama' => isset($row['guru_nama']) ? (string) $row['guru_nama'] : '',
                'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
                'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            ];
        }, $results));
    }

    /**
     * Dapatkan list kelas yang diajar guru sesuai jadwal.
     *
     * @param int $guru_id ID user guru. Jika 0, gunakan user yang sedang login.
     * @return array<int, array<string, mixed>>
     */
    public static function dapatkan_kelas_oleh_guru(int $guru_id = 0): array
    {
        global $wpdb;

        $guru_id = 0 < $guru_id ? (int) $guru_id : (int) get_current_user_id();

        if (0 >= $guru_id) {
            return [];
        }

        $table_jadwal = elvd_table_name('elvd_jadwal_pelajaran');
        $table_kelas = elvd_table_name('elvd_kelas');

        $sql = $wpdb->prepare(
            "SELECT DISTINCT k.id, k.nama, k.tingkat, k.wali_guru_id, k.tahun_ajaran_id, k.created_at, k.updated_at
             FROM {$table_jadwal} j
             INNER JOIN {$table_kelas} k ON k.id = j.kelas_id
             WHERE j.guru_id = %d
             ORDER BY k.nama ASC",
            $guru_id
        );

        $results = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($results)) {
            return [];
        }

        return array_values(array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'nama' => (string) $row['nama'],
                'tingkat' => (string) $row['tingkat'],
                'wali_guru_id' => isset($row['wali_guru_id']) ? (int) $row['wali_guru_id'] : 0,
                'tahun_ajaran_id' => isset($row['tahun_ajaran_id']) ? (int) $row['tahun_ajaran_id'] : 0,
                'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
                'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            ];
        }, $results));
    }
}
