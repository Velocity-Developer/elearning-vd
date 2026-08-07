<?php

defined('ABSPATH') || exit;

/**
 * Create plugin tables using WordPress table prefix plus elvd_ table names.
 */
function elvd_create_tables(): void
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $tables = [];

    $tables[] = "CREATE TABLE {$wpdb->prefix}elvd_tahun_ajaran (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nama VARCHAR(120) NOT NULL,
        mulai DATE NOT NULL,
        selesai DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY status (status)
    ) {$charset_collate};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}elvd_kelas (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nama VARCHAR(120) NOT NULL,
        tingkat VARCHAR(50) NOT NULL,
        wali_guru_id BIGINT UNSIGNED NULL,
        tahun_ajaran_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY tingkat (tingkat),
        KEY tahun_ajaran_id (tahun_ajaran_id)
    ) {$charset_collate};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}elvd_mata_pelajaran (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nama VARCHAR(120) NOT NULL,
        kode VARCHAR(40) NULL,
        deskripsi TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY kode (kode)
    ) {$charset_collate};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}elvd_jadwal_pelajaran (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        kelas_id BIGINT UNSIGNED NOT NULL,
        mata_pelajaran_id BIGINT UNSIGNED NOT NULL,
        guru_id BIGINT UNSIGNED NOT NULL,
        tahun_ajaran_id BIGINT UNSIGNED NULL,
        hari VARCHAR(20) NOT NULL,
        jam_mulai TIME NOT NULL,
        jam_selesai TIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY kelas_id (kelas_id),
        KEY mata_pelajaran_id (mata_pelajaran_id),
        KEY guru_id (guru_id),
        KEY tahun_ajaran_id (tahun_ajaran_id)
    ) {$charset_collate};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}elvd_pengerjaan_quiz (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        quiz_id BIGINT UNSIGNED NOT NULL,
        siswa_id BIGINT UNSIGNED NOT NULL,
        jawaban LONGTEXT NULL,
        nilai DECIMAL(5,2) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        mulai_pada DATETIME NULL,
        selesai_pada DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY quiz_id (quiz_id),
        KEY siswa_id (siswa_id),
        KEY status (status)
    ) {$charset_collate};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}elvd_pengerjaan_tugas (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tugas_id BIGINT UNSIGNED NOT NULL,
        nama VARCHAR(200) NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        file VARCHAR(255) NULL,
        catatan TEXT NULL,
        tanggal DATETIME NULL,
        nilai DECIMAL(5,2) NULL,
        tanggal_nilai DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY tugas_id (tugas_id),
        KEY user_id (user_id)
    ) {$charset_collate};";

    foreach ($tables as $table) {
        dbDelta($table);
    }

    elvd_maybe_add_jadwal_tahun_ajaran_column($wpdb);
}

/**
 * Add tahun_ajaran_id to jadwal_pelajaran on existing installs (dbDelta does not add columns).
 */
function elvd_maybe_add_jadwal_tahun_ajaran_column($wpdb): void
{
    $table = elvd_table_name('elvd_jadwal_pelajaran');

    $found = $wpdb->get_results(
        "SHOW COLUMNS FROM `{$table}` LIKE 'tahun_ajaran_id'",
        ARRAY_A
    );

    if (! $found) {
        $wpdb->query(
            "ALTER TABLE `{$table}` ADD COLUMN tahun_ajaran_id BIGINT UNSIGNED NULL AFTER guru_id, ADD KEY tahun_ajaran_id (tahun_ajaran_id)"
        );
    }
}
