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
                    'users' => true,
                    'tahun_ajaran' => true,
                    'kelas' => true,
                    'mata_pelajaran' => true,
                    'jadwal_pelajaran' => true,
                    'materi' => true,
                    'tugas' => true,
                    'quiz' => true,
                ],
                $options
            );

            if (array_key_exists('create_users', $options)) {
                $options['users'] = ! empty($options['create_users']);
            }

            if (array_key_exists('create_posts', $options)) {
                $options['materi'] = ! empty($options['create_posts']);
                $options['tugas'] = ! empty($options['create_posts']);
                $options['quiz'] = ! empty($options['create_posts']);
            }

            if (function_exists('elvd_create_tables')) {
                \elvd_create_tables();
            }

            $result = [];

            if (! empty($options['users'])) {
                $result['users'] = self::seedUsers();
            }

            if (! empty($options['tahun_ajaran'])) {
                $result['tahun_ajaran'] = self::seedTahunAjaran();
            }

            if (! empty($options['mata_pelajaran'])) {
                $result['mata_pelajaran'] = self::seedMataPelajaran();
            }

            $tahunAjaranId = ! empty($result['tahun_ajaran']['2026/2027'])
                ? (int) $result['tahun_ajaran']['2026/2027']
                : self::findRowId('elvd_tahun_ajaran', ['nama' => '2026/2027']);

            if (! empty($options['kelas'])) {
                $result['kelas'] = self::seedKelas($tahunAjaranId);
            }

            $kelasIds = ! empty($result['kelas']) ? $result['kelas'] : self::findSeededKelasIds();
            $mataPelajaranIds = ! empty($result['mata_pelajaran']) ? $result['mata_pelajaran'] : self::findSeededMataPelajaranIds();
            $kelasId = ! empty($kelasIds['6A']) ? (int) $kelasIds['6A'] : 0;
            $mataPelajaranId = ! empty($mataPelajaranIds['MAT']) ? (int) $mataPelajaranIds['MAT'] : 0;

            if (! empty($options['jadwal_pelajaran'])) {
                $result['jadwal_pelajaran'] = self::seedJadwalPelajaran(
                    $kelasIds,
                    $mataPelajaranIds,
                    self::findUserIdsByRole('guru', 20)
                );
            }

            if (! empty($options['materi'])) {
                $result['materi'] = self::seedMateri($kelasId, $mataPelajaranId);
            }

            if (! empty($options['tugas'])) {
                $result['tugas'] = self::seedTugas($kelasId, $mataPelajaranId);
            }

            if (! empty($options['quiz'])) {
                $result['quiz'] = self::seedQuiz($kelasId, $mataPelajaranId);

                $siswaIds = self::findUserIdsByRole('siswa', 1);
                $siswaId = ! empty($siswaIds) ? (int) $siswaIds[0] : 0;
                $pilihanGandaId = ! empty($result['quiz']['pilihan_ganda']) ? (int) $result['quiz']['pilihan_ganda'] : 0;
                $pertanyaanPg = ! empty($result['quiz']['pertanyaan']['pilihan_ganda'])
                    ? $result['quiz']['pertanyaan']['pilihan_ganda']
                    : [];

                if ($pilihanGandaId > 0 && $siswaId > 0 && ! empty($pertanyaanPg)) {
                    $result['pengerjaan_quiz'] = self::seedPengerjaanQuiz($pilihanGandaId, $siswaId, $pertanyaanPg);
                }
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

            $users = [];

            for ($i = 1; $i <= 20; $i++) {
                $number = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $users['guru_' . $number] = self::seedUser(
                    'elvd_guru_' . $number,
                    'guru' . $number . '.demo@example.test',
                    'Guru Demo ' . $number,
                    'guru'
                );
                $users['siswa_' . $number] = self::seedUser(
                    'elvd_siswa_' . $number,
                    'siswa' . $number . '.demo@example.test',
                    'Siswa Demo ' . $number,
                    'siswa'
                );
            }

            return $users;
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
                'IPS' => [
                    'nama' => 'Ilmu Pengetahuan Sosial',
                    'kode' => 'IPS',
                    'deskripsi' => 'Masyarakat, ekonomi, sejarah, dan geografi.',
                ],
                'BIG' => [
                    'nama' => 'Bahasa Inggris',
                    'kode' => 'BIG',
                    'deskripsi' => 'Kosakata, tata bahasa, membaca, dan percakapan.',
                ],
                'PKN' => [
                    'nama' => 'Pendidikan Pancasila',
                    'kode' => 'PKN',
                    'deskripsi' => 'Nilai Pancasila, kewarganegaraan, dan konstitusi.',
                ],
                'PAI' => [
                    'nama' => 'Pendidikan Agama Islam',
                    'kode' => 'PAI',
                    'deskripsi' => 'Akidah, ibadah, akhlak, dan sejarah Islam.',
                ],
                'SBD' => [
                    'nama' => 'Seni Budaya',
                    'kode' => 'SBD',
                    'deskripsi' => 'Seni rupa, musik, tari, dan apresiasi budaya.',
                ],
                'PJO' => [
                    'nama' => 'PJOK',
                    'kode' => 'PJO',
                    'deskripsi' => 'Pendidikan jasmani, olahraga, dan kesehatan.',
                ],
                'TIK' => [
                    'nama' => 'Informatika',
                    'kode' => 'TIK',
                    'deskripsi' => 'Komputasi, perangkat lunak, data, dan literasi digital.',
                ],
                'BIO' => [
                    'nama' => 'Biologi',
                    'kode' => 'BIO',
                    'deskripsi' => 'Sel, organisme, ekosistem, dan proses kehidupan.',
                ],
                'FIS' => [
                    'nama' => 'Fisika',
                    'kode' => 'FIS',
                    'deskripsi' => 'Gerak, energi, gaya, gelombang, dan listrik.',
                ],
                'KIM' => [
                    'nama' => 'Kimia',
                    'kode' => 'KIM',
                    'deskripsi' => 'Materi, reaksi, atom, molekul, dan larutan.',
                ],
                'SEJ' => [
                    'nama' => 'Sejarah',
                    'kode' => 'SEJ',
                    'deskripsi' => 'Peristiwa, tokoh, dan perubahan masyarakat.',
                ],
                'GEO' => [
                    'nama' => 'Geografi',
                    'kode' => 'GEO',
                    'deskripsi' => 'Ruang, wilayah, lingkungan, dan peta.',
                ],
                'EKO' => [
                    'nama' => 'Ekonomi',
                    'kode' => 'EKO',
                    'deskripsi' => 'Kebutuhan, produksi, distribusi, pasar, dan keuangan.',
                ],
                'SOS' => [
                    'nama' => 'Sosiologi',
                    'kode' => 'SOS',
                    'deskripsi' => 'Interaksi sosial, kelompok, budaya, dan perubahan sosial.',
                ],
                'ANT' => [
                    'nama' => 'Antropologi',
                    'kode' => 'ANT',
                    'deskripsi' => 'Manusia, kebudayaan, tradisi, dan keragaman.',
                ],
                'PRA' => [
                    'nama' => 'Prakarya',
                    'kode' => 'PRA',
                    'deskripsi' => 'Keterampilan, kerajinan, pengolahan, dan kewirausahaan.',
                ],
                'BJD' => [
                    'nama' => 'Bahasa Jawa',
                    'kode' => 'BJD',
                    'deskripsi' => 'Bahasa, aksara, sastra, dan budaya Jawa.',
                ],
            ];

            return self::seedRows('elvd_mata_pelajaran', 'kode', $rows);
        }

        /**
         * @return array<string, int>
         */
        private static function seedKelas(int $tahunAjaranId): array
        {
            if ($tahunAjaranId <= 0) {
                return [
                    '6A' => 0,
                    '7A' => 0,
                    '10A' => 0,
                    '6B' => 0,
                    '7B' => 0,
                    '8A' => 0,
                    '8B' => 0,
                    '9A' => 0,
                    '9B' => 0,
                    '10B' => 0,
                ];
            }

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
                '6B' => [
                    'nama' => 'Kelas 6B',
                    'tingkat' => 'SD',
                    'wali_guru_id' => self::findUserIdByRole('guru'),
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
                '7B' => [
                    'nama' => 'Kelas 7B',
                    'tingkat' => 'SMP',
                    'wali_guru_id' => self::findUserIdByRole('guru'),
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
                '8A' => [
                    'nama' => 'Kelas 8A',
                    'tingkat' => 'SMP',
                    'wali_guru_id' => self::findUserIdByRole('guru'),
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
                '8B' => [
                    'nama' => 'Kelas 8B',
                    'tingkat' => 'SMP',
                    'wali_guru_id' => self::findUserIdByRole('guru'),
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
                '9A' => [
                    'nama' => 'Kelas 9A',
                    'tingkat' => 'SMP',
                    'wali_guru_id' => self::findUserIdByRole('guru'),
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
                '9B' => [
                    'nama' => 'Kelas 9B',
                    'tingkat' => 'SMP',
                    'wali_guru_id' => self::findUserIdByRole('guru'),
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
                '10B' => [
                    'nama' => 'Kelas 10B',
                    'tingkat' => 'SMA',
                    'wali_guru_id' => self::findUserIdByRole('guru'),
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
            ];

            return self::seedRows('elvd_kelas', 'nama', $rows);
        }

        /**
         * @param array<string, int> $kelasIds
         * @param array<string, int> $mataPelajaranIds
         * @param array<int, int> $guruIds
         * @return array<string, int>
         */
        private static function seedJadwalPelajaran(array $kelasIds, array $mataPelajaranIds, array $guruIds): array
        {
            $kelasIds = array_filter(array_map('absint', $kelasIds));
            $mataPelajaranIds = array_filter(array_map('absint', $mataPelajaranIds));
            $guruIds = array_values(array_filter(array_map('absint', $guruIds)));

            if (empty($kelasIds) || empty($mataPelajaranIds) || empty($guruIds)) {
                return [
                    'jadwal_01' => 0,
                ];
            }

            $hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
            $jam = [
                ['07:30:00', '08:30:00'],
                ['08:30:00', '09:30:00'],
                ['10:00:00', '11:00:00'],
                ['11:00:00', '12:00:00'],
            ];
            $mataPelajaranValues = array_values($mataPelajaranIds);
            $result = [];
            $index = 0;

            foreach ($kelasIds as $kelasKey => $kelasId) {
                foreach ($hari as $hariIndex => $namaHari) {
                    foreach ($jam as $jamIndex => $rentangJam) {
                        $mataPelajaranId = $mataPelajaranValues[$index % count($mataPelajaranValues)];
                        $guruId = $guruIds[$index % count($guruIds)];
                        $result[$kelasKey . '_' . $namaHari . '_' . ($jamIndex + 1)] = self::insertIfMissing(
                            'elvd_jadwal_pelajaran',
                            [
                                'kelas_id' => $kelasId,
                                'hari' => $namaHari,
                                'jam_mulai' => $rentangJam[0],
                            ],
                            [
                                'kelas_id' => $kelasId,
                                'mata_pelajaran_id' => $mataPelajaranId,
                                'guru_id' => $guruId,
                                'hari' => $namaHari,
                                'jam_mulai' => $rentangJam[0],
                                'jam_selesai' => $rentangJam[1],
                            ]
                        );
                        $index++;
                    }

                    $index += $hariIndex;
                }
            }

            return $result;
        }

        private static function seedMateri(int $kelasId, int $mataPelajaranId): int
        {
            if ($kelasId <= 0 || $mataPelajaranId <= 0) {
                return 0;
            }

            return self::seedPost(
                'elvd_materi',
                'Materi Demo: Pecahan',
                'Ringkasan materi tentang pecahan dan contoh penerapannya.',
                [
                    'elvd_kelas_id' => $kelasId,
                    'elvd_mata_pelajaran_id' => $mataPelajaranId,
                    'elvd_file_url' => '',
                ]
            );
        }

        private static function seedTugas(int $kelasId, int $mataPelajaranId): int
        {
            if ($kelasId <= 0 || $mataPelajaranId <= 0) {
                return 0;
            }

            return self::seedPost(
                'elvd_tugas',
                'Tugas Demo: Latihan Pecahan',
                'Kerjakan latihan pecahan pada buku paket halaman 24.',
                [
                    'elvd_kelas_id' => $kelasId,
                    'elvd_mata_pelajaran_id' => $mataPelajaranId,
                    'elvd_deadline' => '2026-08-10 23:59:00',
                    'elvd_instruksi' => 'Tuliskan cara pengerjaan untuk setiap nomor.',
                ]
            );
        }

        /**
         * Seed demo quiz (pilihan ganda + essay) with their questions.
         *
         * @return array<string, mixed>
         */
        private static function seedQuiz(int $kelasId, int $mataPelajaranId): array
        {
            if ($kelasId <= 0 || $mataPelajaranId <= 0) {
                return [
                    'pilihan_ganda' => 0,
                    'essay' => 0,
                    'pertanyaan' => [
                        'pilihan_ganda' => [],
                        'essay' => [],
                    ],
                ];
            }

            $quizPgId = self::seedPost(
                'elvd_quiz',
                'Quiz Demo: Pecahan Dasar',
                'Pilih satu jawaban paling tepat untuk setiap soal. Waktu pengerjaan terbatas.',
                [
                    'elvd_kelas_id' => $kelasId,
                    'elvd_mata_pelajaran_id' => $mataPelajaranId,
                    'elvd_quiz_tipe' => 'pilihan_ganda',
                    'elvd_durasi_menit' => 30,
                ]
            );

            $pertanyaanPg = [];

            if ($quizPgId > 0) {
                $pertanyaanPg = [
                    self::seedQuizQuestion($quizPgId, 'Berapakah hasil dari 1/2 + 1/4?', 'pilihan_ganda', ['1/4', '2/3', '3/4', '4/4'], 2),
                    self::seedQuizQuestion($quizPgId, 'Pecahan senilai dari 2/5 adalah ...', 'pilihan_ganda', ['4/10', '2/10', '4/5', '5/2'], 0),
                    self::seedQuizQuestion($quizPgId, 'Bentuk desimal dari 3/4 adalah ...', 'pilihan_ganda', ['0,25', '0,50', '0,75', '1,25'], 2),
                    self::seedQuizQuestion($quizPgId, 'Urutan pecahan dari nilai terkecil adalah ...', 'pilihan_ganda', ['3/8, 1/2, 5/6', '1/2, 2/3, 3/8', '5/6, 2/3, 1/2', '1/2, 1/3, 2/3'], 0),
                ];
            }

            $quizEssayId = self::seedPost(
                'elvd_quiz',
                'Quiz Demo: Uraian Pecahan',
                'Jawab setiap pertanyaan dengan uraian singkat dan lengkap.',
                [
                    'elvd_kelas_id' => $kelasId,
                    'elvd_mata_pelajaran_id' => $mataPelajaranId,
                    'elvd_quiz_tipe' => 'essay',
                    'elvd_durasi_menit' => 15,
                ]
            );

            $pertanyaanEssay = [];

            if ($quizEssayId > 0) {
                $pertanyaanEssay = [
                    self::seedQuizQuestion($quizEssayId, 'Jelaskan cara menjumlahkan pecahan yang penyebutnya berbeda, lalu berikan satu contoh!', 'essay', [], 6),
                    self::seedQuizQuestion($quizEssayId, 'Urutkan pecahan 1/4, 3/8, dan 1/2 dari yang terkecil. Sertakan langkah pengerjaanmu.', 'essay', [], 4),
                ];
            }

            return [
                'pilihan_ganda' => $quizPgId,
                'essay' => $quizEssayId,
                'pertanyaan' => [
                    'pilihan_ganda' => $pertanyaanPg,
                    'essay' => $pertanyaanEssay,
                ],
            ];
        }

        /**
         * Seed a single quiz question as an elvd_quiz_question post linked to the quiz.
         *
         * @param array<int, string> $opsi
         * @param int|string $jawaban
         * @return array<string, mixed>
         */
        private static function seedQuizQuestion(int $quizId, string $pertanyaan, string $tipe, array $opsi, $jawaban): array
        {
            $postId = self::seedPost(
                'elvd_quiz_question',
                $pertanyaan,
                '',
                [
                    'elvd_quiz_id' => $quizId,
                    'elvd_pertanyaan_tipe' => $tipe,
                    'elvd_poin' => 'essay' === $tipe ? absint($jawaban) : 0,
                    'elvd_opsi' => 'pilihan_ganda' === $tipe ? wp_json_encode($opsi) : '',
                    'elvd_jawaban_benar' => 'pilihan_ganda' === $tipe ? (string) $jawaban : '',
                ]
            );

            return [
                'id' => $postId,
                'jawaban' => (string) $jawaban,
            ];
        }

        /**
         * @param array<string, mixed> $meta
         */
        private static function seedPost(string $postType, string $title, string $content, array $meta): int
        {
            if (function_exists('elvd_register_post_types')) {
                \elvd_register_post_types();
            }

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

        /**
         * @param array<int, array<string, mixed>> $questions
         */
        private static function seedPengerjaanQuiz(int $quizId, int $siswaId, array $questions): int
        {
            if ($quizId <= 0 || $siswaId <= 0 || empty($questions)) {
                return 0;
            }

            $jawaban = [];

            foreach ($questions as $question) {
                $questionId = absint($question['id'] ?? 0);

                if ($questionId > 0) {
                    $jawaban[(string) $questionId] = (string) ($question['jawaban'] ?? '');
                }
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
                    'jawaban' => wp_json_encode($jawaban),
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

        /**
         * @return array<string, int>
         */
        private static function findSeededKelasIds(): array
        {
            $kelasNames = [
                '6A' => 'Kelas 6A',
                '7A' => 'Kelas 7A',
                '10A' => 'Kelas 10A',
                '6B' => 'Kelas 6B',
                '7B' => 'Kelas 7B',
                '8A' => 'Kelas 8A',
                '8B' => 'Kelas 8B',
                '9A' => 'Kelas 9A',
                '9B' => 'Kelas 9B',
                '10B' => 'Kelas 10B',
            ];
            $ids = [];

            foreach ($kelasNames as $key => $name) {
                $ids[$key] = self::findRowId('elvd_kelas', ['nama' => $name]);
            }

            return $ids;
        }

        /**
         * @return array<string, int>
         */
        private static function findSeededMataPelajaranIds(): array
        {
            $codes = [
                'MAT',
                'BIN',
                'IPA',
                'IPS',
                'BIG',
                'PKN',
                'PAI',
                'SBD',
                'PJO',
                'TIK',
                'BIO',
                'FIS',
                'KIM',
                'SEJ',
                'GEO',
                'EKO',
                'SOS',
                'ANT',
                'PRA',
                'BJD',
            ];
            $ids = [];

            foreach ($codes as $code) {
                $ids[$code] = self::findRowId('elvd_mata_pelajaran', ['kode' => $code]);
            }

            return $ids;
        }

        /**
         * @return array<int, int>
         */
        private static function findUserIdsByRole(string $role, int $number = 20): array
        {
            $users = get_users(
                [
                    'role' => $role,
                    'number' => $number,
                    'fields' => 'ID',
                    'orderby' => 'ID',
                    'order' => 'ASC',
                ]
            );

            return array_values(array_map('absint', $users));
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
