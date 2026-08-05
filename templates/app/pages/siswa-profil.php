<?php

defined('ABSPATH') || exit;

global $wpdb;

$elvd_siswa_id = absint((int) ($_GET['id'] ?? 0));
$elvd_siswa_back_url = untrailingslashit(ELVD::app_route()) . '/siswa/';
$elvd_siswa = $elvd_siswa_id > 0 ? get_userdata($elvd_siswa_id) : null;

$elvd_siswa_notice = '';

if ($elvd_siswa_id <= 0) {
    $elvd_siswa_notice = '<div class="alert alert-warning">' . esc_html__('ID siswa tidak ditemukan.', 'elearning-vd') . '</div>';
} elseif (! $elvd_siswa instanceof WP_User || ! in_array('siswa', (array) $elvd_siswa->roles, true)) {
    $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Data siswa tidak ditemukan.', 'elearning-vd') . '</div>';
}

$elvd_siswa_data = [];

if ($elvd_siswa instanceof WP_User) {
    $elvd_kelas_table = elvd_table_name('elvd_kelas');
    $elvd_kelas_meta = (string) get_user_meta($elvd_siswa->ID, 'elvd_kelas', true);
    $elvd_kelas_info = null;

    if ('' !== $elvd_kelas_meta && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $elvd_kelas_table)) === $elvd_kelas_table) {
        $elvd_kelas_info = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT k.id, k.nama, k.tingkat, k.tahun_ajaran_id, ta.nama AS tahun_ajaran FROM ' . $elvd_kelas_table . ' k LEFT JOIN ' . elvd_table_name('elvd_tahun_ajaran') . ' ta ON ta.id = k.tahun_ajaran_id WHERE k.id = %d OR k.nama = %s LIMIT 1',
                absint($elvd_kelas_meta),
                $elvd_kelas_meta
            ),
            ARRAY_A
        );
    }

    $elvd_siswa_data = [
        'id' => (int) $elvd_siswa->ID,
        'nama' => '' !== trim((string) $elvd_siswa->display_name) ? $elvd_siswa->display_name : $elvd_siswa->user_login,
        'username' => $elvd_siswa->user_login,
        'email' => $elvd_siswa->user_email,
        'nis' => (string) get_user_meta($elvd_siswa->ID, 'elvd_nis', true),
        'kelas' => $elvd_kelas_info ? (string) ($elvd_kelas_info['nama'] ?? '') : $elvd_kelas_meta,
        'tingkat' => $elvd_kelas_info ? (string) ($elvd_kelas_info['tingkat'] ?? '') : '',
        'tahun_ajaran' => $elvd_kelas_info ? (string) ($elvd_kelas_info['tahun_ajaran'] ?? '') : '',
        'tanggal_lahir' => (string) get_user_meta($elvd_siswa->ID, 'elvd_tanggal_lahir', true),
        'telepon' => (string) get_user_meta($elvd_siswa->ID, 'elvd_telepon', true),
        'alamat' => (string) get_user_meta($elvd_siswa->ID, 'elvd_alamat', true),
    ];
}
?>

<div x-show="active === 'siswa-profil'" x-data="{
    siswa: <?php echo esc_attr(wp_json_encode($elvd_siswa_data)); ?>,
    formatDate(value) {
        if (!value) {
            return '-';
        }

        return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }
}">
    <?php
    if ('' !== $elvd_siswa_notice) {
        echo $elvd_siswa_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    ?>

    <template x-if="siswa.id">
        <div class="elvd-table-panel">
            <div class="elvd-resource-toolbar">
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url($elvd_siswa_back_url); ?>">
                    &larr; <?php echo esc_html__('Kembali ke Daftar Siswa', 'elearning-vd'); ?>
                </a>
            </div>

            <div class="p-4">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3 mb-4">
                    <div class="elvd-avatar" aria-hidden="true">
                        <span x-text="siswa.nama ? siswa.nama.charAt(0).toUpperCase() : '?'"></span>
                    </div>
                    <div>
                        <h2 class="h4 mb-1" x-text="siswa.nama || '-'"></h2>
                        <div class="text-muted" x-text="siswa.email || '-'"></div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <div class="elvd-profile-field">
                            <span><?php echo esc_html__('NIS', 'elearning-vd'); ?></span>
                            <strong x-text="siswa.nis || '-'"></strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="elvd-profile-field">
                            <span><?php echo esc_html__('Kelas', 'elearning-vd'); ?></span>
                            <strong x-text="siswa.kelas || '-'"></strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="elvd-profile-field">
                            <span><?php echo esc_html__('Tingkat', 'elearning-vd'); ?></span>
                            <strong x-text="siswa.tingkat || '-'"></strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="elvd-profile-field">
                            <span><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></span>
                            <strong x-text="siswa.tahun_ajaran || '-'"></strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="elvd-profile-field">
                            <span><?php echo esc_html__('Tanggal Lahir', 'elearning-vd'); ?></span>
                            <strong x-text="formatDate(siswa.tanggal_lahir)"></strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="elvd-profile-field">
                            <span><?php echo esc_html__('Telepon', 'elearning-vd'); ?></span>
                            <strong x-text="siswa.telepon || '-'"></strong>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="elvd-profile-field">
                            <span><?php echo esc_html__('Alamat', 'elearning-vd'); ?></span>
                            <strong x-text="siswa.alamat || '-'"></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>