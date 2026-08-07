<?php

defined('ABSPATH') || exit;

/**
 * Resource map for custom tables and sanitizers.
 *
 * @return array<string, array<string, mixed>>
 */
function elvd_rest_resources(): array
{
    return [
        'tahun-ajaran' => [
            'table' => 'elvd_tahun_ajaran',
            'fields' => [
                'nama' => 'text',
                'mulai' => 'date',
                'selesai' => 'date',
                'status' => 'key',
            ],
        ],
        'kelas' => [
            'table' => 'elvd_kelas',
            'fields' => [
                'nama' => 'text',
                'tingkat' => 'text',
                'wali_guru_id' => 'int',
                'tahun_ajaran_id' => 'int',
            ],
        ],
        'mata-pelajaran' => [
            'table' => 'elvd_mata_pelajaran',
            'fields' => [
                'nama' => 'text',
                'kode' => 'key',
                'deskripsi' => 'textarea',
            ],
        ],
        'jadwal-pelajaran' => [
            'table' => 'elvd_jadwal_pelajaran',
            'fields' => [
                'kelas_id' => 'int',
                'mata_pelajaran_id' => 'int',
                'guru_id' => 'int',
                'tahun_ajaran_id' => 'int',
                'hari' => 'text',
                'jam_mulai' => 'time',
                'jam_selesai' => 'time',
            ],
        ],
        'pengerjaan-quiz' => [
            'table' => 'elvd_pengerjaan_quiz',
            'fields' => [
                'quiz_id' => 'int',
                'siswa_id' => 'int',
                'jawaban' => 'json',
                'nilai' => 'decimal',
                'status' => 'key',
                'mulai_pada' => 'datetime',
                'selesai_pada' => 'datetime',
            ],
        ],
        'pengerjaan-tugas' => [
            'table' => 'elvd_pengerjaan_tugas',
            'fields' => [
                'tugas_id' => 'int',
                'nama' => 'text',
                'user_id' => 'int',
                'file' => 'text',
                'catatan' => 'textarea',
                'tanggal' => 'datetime',
                'nilai' => 'decimal',
                'tanggal_nilai' => 'datetime',
            ],
        ],
    ];
}
