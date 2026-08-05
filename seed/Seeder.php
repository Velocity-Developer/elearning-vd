<?php

namespace ElearningVD {

defined('ABSPATH') || exit;

final class Seeder
{
    /**
     * Seed demo learning data. Safe to run repeatedly.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function seed(array $options = []): array
    {
        $options = array_merge(
            [
                'create_users' => true,
                'create_posts' => true,
            ],
            $options
        );

        if (function_exists('elvd_create_tables')) {
            \elvd_create_tables();
        }

        $result = [
            'users' => [],
            'tahun_ajaran' => [],
            'mata_pelajaran' => [],
            'kelas' => [],
            'jadwal_pelajaran' => [],
            'posts' => [],
            'pengerjaan_quiz' => [],
        ];

        if (! empty($options['create_users'])) {
            $result['users'] = self::seedUsers();
        }

        $result['tahun_ajaran'] = self::seedTahunAjaran();
        $result['mata_pelajaran'] = self::seedMataPelajaran();
        $result['kelas'] = self::seedKelas((int) $result['tahun_ajaran']['2026/2027']);
        $result['jadwal_pelajaran'] = self::seedJadwalPelajaran(
            (int) $result['kelas']['6A'],
            (int) $result['mata_pelajaran']['MAT'],
            self::findUserIdByRole('guru')
        );

        if (! empty($options['create_posts'])) {
            $result['posts'] = self::seedPosts(
                (int) $result['kelas']['6A'],
                (int) $result['mata_pelajaran']['MAT']
            );
            $result['pengerjaan_quiz'] = self::seedPengerjaanQuiz(
                (int) $result['posts']['quiz'],
                self::findUserIdByRole('siswa')
            );
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private static function seedUsers(): array
    {
        if (function_exists('elvd_register_roles')) {
            \elvd_register_roles();
        }

        return [
            'guru' => self::seedUser('elvd_guru_demo', 'guru.demo@example.test', 'Guru Demo', 'guru'),
            'siswa' => self::seedUser('elvd_siswa_demo', 'siswa.demo@example.test', 'Siswa Demo', 'siswa'),
        ];
    }

    private static function seedUser(string $login, string $email, string $displayName, string $role): int
    {
        $user = get_user_by('login', $login);
        if ($user instanceof \WP_User) {
            return (int) $user->ID;
        }

        $userId = wp_insert_user(
            [
                'user_login' => $login,
                'user_email' => $email,
                'display_name' => $displayName,
                'user_pass' => wp_generate_password(24, true),
                'role' => $role,
            ]
        );

        if (is_wp_error($userId)) {
            return 0;
        }

        return (int) $userId;
    }

    /**
     * @return array<string, int>
     */
    private static function seedTahunAjaran(): array
    {
        $rows = [
            '2025/2026' => [
                'nama' => '2025/2026',
                'mulai' => '2025-07-01',
                'selesai' => '2026-06-30',
                'status' => 'arsip',
            ],
            '2026/2027' => [
                'nama' => '2026/2027',
                'mulai' => '2026-07-01',
                'selesai' => '2027-06-30',
                'status' => 'aktif',
            ],
        ];

        return self::seedRows('elvd_tahun_ajaran', 'nama', $rows);
    }

    /**
     * @return array<string, int>
     */
    private static function seedMataPelajaran(): array
    {
        $rows = [
            'MAT' => [
                'nama' => 'Matematika',
                'kode' => 'MAT',
                'deskripsi' => 'Bilangan, geometri, dan pemecahan masalah.',
            ],
            'BIN' => [
                'nama' => 'Bahasa Indonesia',
                'kode' => 'BIN',
                'deskripsi' => 'Membaca, menulis, dan memahami teks.',
            ],
            'IPA' => [
                'nama' => 'Ilmu Pengetahuan Alam',
                'kode' => 'IPA',
                'deskripsi' => 'Makhluk hidup, energi, bumi, dan lingkungan.',
            ],
        ];

        return self::seedRows('elvd_mata_pelajaran', 'kode', $rows);
    }

    /**
     * @return array<string, int>
     */
    private static function seedKelas(int $tahunAjaranId): array
    {
        $rows = [
            '6A' => [
                'nama' => 'Kelas 6A',
                'tingkat' => 'SD',
                'wali_guru_id' => self::findUserIdByRole('guru'),
                'tahun_ajaran_id' => $tahunAjaranId,
            ],
            '7A' => [
                'nama' => 'Kelas 7A',
                'tingkat' => 'SMP',
                'wali_guru_id' => self::findUserIdByRole('guru'),
                'tahun_ajaran_id' => $tahunAjaranId,
            ],
            '10A' => [
                'nama' => 'Kelas 10A',
                'tingkat' => 'SMA',
                'wali_guru_id' => self::findUserIdByRole('guru'),
                'tahun_ajaran_id' => $tahunAjaranId,
            ],
        ];

        return self::seedRows('elvd_kelas', 'nama', $rows);
    }

    private static function seedJadwalPelajaran(int $kelasId, int $mataPelajaranId, int $guruId): int
    {
        if ($kelasId <= 0 || $mataPelajaranId <= 0 || $guruId <= 0) {
            return 0;
        }

        return self::insertIfMissing(
            'elvd_jadwal_pelajaran',
            [
                'kelas_id' => $kelasId,
                'mata_pelajaran_id' => $mataPelajaranId,
                'hari' => 'Senin',
                'jam_mulai' => '08:00:00',
            ],
            [
                'kelas_id' => $kelasId,
                'mata_pelajaran_id' => $mataPelajaranId,
                'guru_id' => $guruId,
                'hari' => 'Senin',
                'jam_mulai' => '08:00:00',
                'jam_selesai' => '09:30:00',
            ]
        );
    }

    /**
     * @return array<string, int>
     */
    private static function seedPosts(int $kelasId, int $mataPelajaranId): array
    {
        if (function_exists('elvd_register_post_types')) {
            \elvd_register_post_types();
        }

        return [
            'materi' => self::seedPost(
                'elvd_materi',
                'Materi Demo: Pecahan',
                'Ringkasan materi tentang pecahan dan contoh penerapannya.',
                [
                    'elvd_kelas_id' => $kelasId,
                    'elvd_mata_pelajaran_id' => $mataPelajaranId,
                    'elvd_file_url' => '',
                ]
            ),
            'tugas' => self::seedPost(
                'elvd_tugas',
                'Tugas Demo: Latihan Pecahan',
                'Kerjakan latihan pecahan pada buku paket halaman 24.',
                [
                    'elvd_kelas_id' => $kelasId,
                    'elvd_mata_pelajaran_id' => $mataPelajaranId,
                    'elvd_deadline' => '2026-08-10 23:59:00',
                    'elvd_instruksi' => 'Tuliskan cara pengerjaan untuk setiap nomor.',
                ]
            ),
            'quiz' => self::seedPost(
                'elvd_quiz',
                'Quiz Demo: Pecahan Dasar',
                'Quiz singkat untuk menguji pemahaman pecahan.',
                [
                    'elvd_kelas_id' => $kelasId,
                    'elvd_mata_pelajaran_id' => $mataPelajaranId,
                    'elvd_quiz_tipe' => 'pilihan_ganda',
                    'elvd_durasi_menit' => 30,
                    'elvd_pertanyaan' => wp_json_encode(
                        [
                            [
                                'pertanyaan' => 'Berapakah hasil dari 1/2 + 1/4?',
                                'pilihan' => ['1/4', '2/4', '3/4', '4/4'],
                                'jawaban' => '3/4',
                            ],
                        ]
                    ),
                ]
            ),
        ];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function seedPost(string $postType, string $title, string $content, array $meta): int
    {
        if (! function_exists('post_exists')) {
            require_once ABSPATH . 'wp-admin/includes/post.php';
        }

        $postId = (int) post_exists($title, '', '', $postType);
        if ($postId <= 0) {
            $insertedPostId = wp_insert_post(
                [
                    'post_type' => $postType,
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'publish',
                    'post_author' => self::findUserIdByRole('guru'),
                ],
                true
            );

            if (is_wp_error($insertedPostId)) {
                return 0;
            }

            $postId = (int) $insertedPostId;
            if ($postId <= 0) {
                return 0;
            }
        }

        foreach ($meta as $key => $value) {
            update_post_meta($postId, $key, $value);
        }

        return $postId;
    }

    private static function seedPengerjaanQuiz(int $quizId, int $siswaId): int
    {
        if ($quizId <= 0 || $siswaId <= 0) {
            return 0;
        }

        return self::insertIfMissing(
            'elvd_pengerjaan_quiz',
            [
                'quiz_id' => $quizId,
                'siswa_id' => $siswaId,
            ],
            [
                'quiz_id' => $quizId,
                'siswa_id' => $siswaId,
                'jawaban' => wp_json_encode(['1' => '3/4']),
                'nilai' => 100.00,
                'status' => 'selesai',
                'mulai_pada' => '2026-08-03 08:00:00',
                'selesai_pada' => '2026-08-03 08:20:00',
            ]
        );
    }

    /**
     * @param array<string, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private static function seedRows(string $table, string $uniqueColumn, array $rows): array
    {
        $ids = [];

        foreach ($rows as $key => $row) {
            $ids[$key] = self::insertIfMissing(
                $table,
                [$uniqueColumn => $row[$uniqueColumn]],
                $row
            );
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $where
     * @param array<string, mixed> $data
     */
    private static function insertIfMissing(string $table, array $where, array $data): int
    {
        global $wpdb;

        $existingId = self::findRowId($table, $where);
        if ($existingId > 0) {
            return $existingId;
        }

        $now = current_time('mysql');
        $data = array_merge(
            [
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $data
        );

        $inserted = $wpdb->insert(\elvd_table_name($table), $data, \elvd_db_formats($data));
        if (! $inserted) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $where
     */
    private static function findRowId(string $table, array $where): int
    {
        global $wpdb;

        $clauses = [];
        $values = [];

        foreach ($where as $column => $value) {
            $clauses[] = "`{$column}` = " . self::placeholder($value);
            $values[] = $value;
        }

        $sql = sprintf(
            'SELECT id FROM %s WHERE %s LIMIT 1',
            \elvd_table_name($table),
            implode(' AND ', $clauses)
        );

        return (int) $wpdb->get_var($wpdb->prepare($sql, $values));
    }

    private static function placeholder($value): string
    {
        if (is_int($value)) {
            return '%d';
        }

        if (is_float($value)) {
            return '%f';
        }

        return '%s';
    }

    private static function findUserIdByRole(string $role): int
    {
        $users = get_users(
            [
                'role' => $role,
                'number' => 1,
                'fields' => 'ID',
            ]
        );

        if (! empty($users)) {
            return (int) $users[0];
        }

        if ('guru' !== $role) {
            return 0;
        }

        $admins = get_users(
            [
                'role' => 'administrator',
                'number' => 1,
                'fields' => 'ID',
            ]
        );

        return empty($admins) ? 0 : (int) $admins[0];
    }
}

}

namespace {
    /**
     * Run Elearning VD demo seed data.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    function elvd_seed_data(array $options = []): array
    {
        return \ElearningVD\Seeder::seed($options);
    }
}
