<?php

defined('ABSPATH') || exit;

use Dompdf\Dompdf;
use Dompdf\Options;

function elvd_maybe_download_jadwal_siswa_pdf(): void
{
    if (! isset($_GET['elvd_download_jadwal'])) {
        return;
    }

    if ('pdf' !== sanitize_key(wp_unslash((string) $_GET['elvd_download_jadwal']))) {
        return;
    }

    if (! is_user_logged_in()) {
        auth_redirect();
    }

    $elvd_current_user = wp_get_current_user();
    $elvd_current_role = (string) current(
        array_intersect(
            ['administrator', 'guru', 'siswa'],
            (array) $elvd_current_user->roles
        )
    );

    if ('siswa' !== $elvd_current_role) {
        wp_die(esc_html__('Halaman ini hanya untuk siswa.', 'elearning-vd'));
    }

    global $wpdb;

    $elvd_kelas_meta = (string) get_user_meta($elvd_current_user->ID, 'elvd_kelas', true);
    $elvd_siswa_kelas_id = 0;

    if ('' !== $elvd_kelas_meta) {
        $elvd_kelas_row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, nama FROM `%1$s` WHERE id = %2$d OR nama = %3$s LIMIT 1',
                elvd_table_name('elvd_kelas'),
                absint($elvd_kelas_meta),
                $elvd_kelas_meta
            )
        );

        if ($elvd_kelas_row) {
            $elvd_siswa_kelas_id = (int) $elvd_kelas_row->id;
        }
    }

    if ($elvd_siswa_kelas_id < 1) {
        wp_die(esc_html__('Kelas siswa belum diatur.', 'elearning-vd'));
    }

    $elvd_kelas_nama = (string) $wpdb->get_var(
        $wpdb->prepare(
            'SELECT nama FROM ' . elvd_table_name('elvd_kelas') . ' WHERE id = %d LIMIT 1',
            $elvd_siswa_kelas_id
        )
    );

    $elvd_tahun_aktif = (string) $wpdb->get_var(
        $wpdb->prepare(
            'SELECT nama FROM ' . elvd_table_name('elvd_tahun_ajaran') . ' WHERE status = %s ORDER BY mulai DESC, id DESC LIMIT 1',
            'aktif'
        )
    );

    $elvd_schedule_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT j.hari, j.jam_mulai, j.jam_selesai, mp.nama AS mata_pelajaran
             FROM %1\$s j
             LEFT JOIN %2\$s mp ON mp.id = j.mata_pelajaran_id
             WHERE j.kelas_id = %3\$d
             ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai",
            elvd_table_name('elvd_jadwal_pelajaran'),
            elvd_table_name('elvd_mata_pelajaran'),
            $elvd_siswa_kelas_id
        )
    );

    $elvd_days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $elvd_schedule_map = [];
    $elvd_time_slots = [];

    foreach ($elvd_schedule_rows as $elvd_schedule_row) {
        $elvd_slot_key = (string) $elvd_schedule_row->jam_mulai . '-' . (string) $elvd_schedule_row->jam_selesai;

        if (! isset($elvd_time_slots[$elvd_slot_key])) {
            $elvd_time_slots[$elvd_slot_key] = [
                'jam_mulai' => (string) $elvd_schedule_row->jam_mulai,
                'jam_selesai' => (string) $elvd_schedule_row->jam_selesai,
            ];
        }

        $elvd_schedule_map[(string) $elvd_schedule_row->hari][$elvd_slot_key] = (string) $elvd_schedule_row->mata_pelajaran;
    }

    uasort(
        $elvd_time_slots,
        static function (array $a, array $b): int {
            return strcmp((string) $a['jam_mulai'], (string) $b['jam_mulai']);
        }
    );

    $elvd_school_name = trim((string) get_option(ELVD::OPTION_SCHOOL_NAME, get_bloginfo('name')));
    $elvd_school_name = '' !== $elvd_school_name ? $elvd_school_name : (string) get_bloginfo('name');
    $elvd_period = '' !== $elvd_tahun_aktif ? $elvd_tahun_aktif : '-';
    $elvd_student_name = '' !== trim($elvd_current_user->display_name) ? $elvd_current_user->display_name : $elvd_current_user->user_login;

    $elvd_format_time = static function (string $value): string {
        return '' !== $value ? substr($value, 0, 5) : '-';
    };

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 11px;
                color: #111827;
            }
            .header {
                text-align: center;
                margin-bottom: 16px;
            }
            .header h1 {
                font-size: 18px;
                margin: 0 0 6px;
            }
            .header p {
                margin: 2px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th,
            td {
                border: 1px solid #cbd5e1;
                padding: 8px;
                vertical-align: top;
            }
            th {
                background: #e2e8f0;
                text-align: center;
            }
            .time-cell {
                width: 90px;
                white-space: nowrap;
            }
            .empty {
                color: #64748b;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo esc_html__('Jadwal Pelajaran Siswa', 'elearning-vd'); ?></h1>
            <p><?php echo esc_html($elvd_school_name); ?></p>
            <p><?php echo esc_html__('Nama Siswa:', 'elearning-vd') . ' ' . esc_html($elvd_student_name); ?></p>
            <p><?php echo esc_html__('Kelas:', 'elearning-vd') . ' ' . esc_html($elvd_kelas_nama ?: '-'); ?></p>
            <p><?php echo esc_html__('Tahun Ajaran:', 'elearning-vd') . ' ' . esc_html($elvd_period); ?></p>
        </div>
        <table>
            <thead>
                <tr>
                    <th><?php echo esc_html__('Waktu', 'elearning-vd'); ?></th>
                    <?php foreach ($elvd_days as $elvd_day) : ?>
                        <th><?php echo esc_html($elvd_day); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ([] === $elvd_time_slots) : ?>
                    <tr>
                        <td colspan="8" class="empty"><?php echo esc_html__('Belum ada jadwal pelajaran.', 'elearning-vd'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($elvd_time_slots as $elvd_slot_key => $elvd_slot) : ?>
                        <tr>
                            <td class="time-cell"><?php echo esc_html($elvd_format_time((string) $elvd_slot['jam_mulai']) . ' - ' . $elvd_format_time((string) $elvd_slot['jam_selesai'])); ?></td>
                            <?php foreach ($elvd_days as $elvd_day) : ?>
                                <td><?php echo esc_html($elvd_schedule_map[$elvd_day][$elvd_slot_key] ?? '-'); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    $elvd_html = (string) ob_get_clean();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $elvd_options = new Options();
    $elvd_options->set('isRemoteEnabled', false);
    $elvd_dompdf = new Dompdf($elvd_options);
    $elvd_dompdf->loadHtml($elvd_html, 'UTF-8');
    $elvd_dompdf->setPaper('A4', 'landscape');
    $elvd_dompdf->render();

    $elvd_filename = 'jadwal-pelajaran-' . sanitize_title($elvd_kelas_nama ?: 'siswa') . '.pdf';

    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $elvd_filename . '"');

    echo $elvd_dompdf->output();
    exit;
}
add_action('template_redirect', 'elvd_maybe_download_jadwal_siswa_pdf', 0);

function elvd_app_shortcode(): string
{
    ob_start();
    require ELVD_PLUGIN_DIR . 'templates/app/app.php';

    return (string) ob_get_clean();
}
