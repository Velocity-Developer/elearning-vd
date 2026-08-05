<?php

defined('ABSPATH') || exit;

global $wpdb;

$elvd_tahun_ajaran_table = elvd_table_name('elvd_tahun_ajaran');
$elvd_kelas_table = elvd_table_name('elvd_kelas');

$elvd_years = [];
$elvd_classes = [];

if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $elvd_tahun_ajaran_table)) === $elvd_tahun_ajaran_table) {
    $elvd_years = $wpdb->get_results(
        "SELECT id, nama, status FROM {$elvd_tahun_ajaran_table} ORDER BY mulai DESC, id DESC",
        ARRAY_A
    );
}

if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $elvd_kelas_table)) === $elvd_kelas_table) {
    $elvd_classes = $wpdb->get_results(
        "SELECT id, nama, tingkat, tahun_ajaran_id FROM {$elvd_kelas_table} ORDER BY nama ASC",
        ARRAY_A
    );
}

$elvd_class_lookup = [];

foreach ($elvd_classes as $elvd_class) {
    $elvd_class_id = (int) ($elvd_class['id'] ?? 0);

    if (0 < $elvd_class_id) {
        $elvd_class_lookup[(string) $elvd_class_id] = $elvd_class;
    }

    if (! empty($elvd_class['nama'])) {
        $elvd_class_lookup[(string) $elvd_class['nama']] = $elvd_class;
    }
}

$elvd_siswa_items = array_map(
    static function (WP_User $user) use ($elvd_class_lookup): array {
        $kelas_meta = (string) get_user_meta($user->ID, 'elvd_kelas', true);
        $matched_class = $elvd_class_lookup[$kelas_meta] ?? null;

        return [
            'id' => (int) $user->ID,
            'nama' => '' !== trim($user->display_name) ? $user->display_name : $user->user_login,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'nis' => (string) get_user_meta($user->ID, 'elvd_nis', true),
            'kelas' => $matched_class ? (string) ($matched_class['nama'] ?? '') : $kelas_meta,
            'kelas_id' => $matched_class ? (int) ($matched_class['id'] ?? 0) : 0,
            'tahun_ajaran_id' => $matched_class ? (int) ($matched_class['tahun_ajaran_id'] ?? 0) : 0,
            'tanggal_lahir' => (string) get_user_meta($user->ID, 'elvd_tanggal_lahir', true),
            'telepon' => (string) get_user_meta($user->ID, 'elvd_telepon', true),
        ];
    },
    get_users(
        [
            'role' => 'siswa',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]
    )
);
?>

<div
    x-show="active === 'siswa'"
    x-data="{
        students: <?php echo esc_attr(wp_json_encode($elvd_siswa_items)); ?>,
        years: <?php echo esc_attr(wp_json_encode($elvd_years)); ?>,
        classes: <?php echo esc_attr(wp_json_encode($elvd_classes)); ?>,
        filters: {
            tahun_ajaran_id: '',
            kelas_id: ''
        },
        init() {
            this.syncItems();

            this.$watch('filters.tahun_ajaran_id', () => {
                if (!this.isSelectedClassAvailable()) {
                    this.filters.kelas_id = '';
                }

                this.syncItems();
            });

            this.$watch('filters.kelas_id', () => this.syncItems());
        },
        filteredClasses() {
            if (!this.filters.tahun_ajaran_id) {
                return this.classes;
            }

            return this.classes.filter((item) => Number(item.tahun_ajaran_id) === Number(this.filters.tahun_ajaran_id));
        },
        filteredStudents() {
            return this.students.filter((item) => {
                const matchesYear = !this.filters.tahun_ajaran_id || Number(item.tahun_ajaran_id) === Number(this.filters.tahun_ajaran_id);
                const matchesClass = !this.filters.kelas_id || Number(item.kelas_id) === Number(this.filters.kelas_id);

                return matchesYear && matchesClass;
            });
        },
        isSelectedClassAvailable() {
            if (!this.filters.kelas_id) {
                return true;
            }

            return this.filteredClasses().some((item) => Number(item.id) === Number(this.filters.kelas_id));
        },
        yearName(id) {
            const year = this.years.find((item) => Number(item.id) === Number(id));

            return year ? year.nama : '-';
        },
        formatDate(value) {
            if (!value) {
                return '-';
            }

            return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        },
        syncItems() {
            this.$dispatch('elvd-items-updated', { items: this.filteredStudents() });
        }
    }">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <div class="row g-3 flex-grow-1">
                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="elvd-siswa-filter-tahun-ajaran"><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-siswa-filter-tahun-ajaran" x-model="filters.tahun_ajaran_id">
                        <option value=""><?php echo esc_html__('Semua tahun ajaran', 'elearning-vd'); ?></option>
                        <template x-for="year in years" :key="year.id">
                            <option :value="String(year.id)" x-text="year.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="elvd-siswa-filter-kelas"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-siswa-filter-kelas" x-model="filters.kelas_id">
                        <option value=""><?php echo esc_html__('Semua kelas', 'elearning-vd'); ?></option>
                        <template x-for="kelas in filteredClasses()" :key="kelas.id">
                            <option :value="String(kelas.id)" x-text="kelas.nama"></option>
                        </template>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Nama', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Email', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('NIS', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Tanggal Lahir', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Telepon', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="item in filteredStudents()" :key="item.id">
                        <tr>
                            <td>
                                <strong x-text="item.nama || '-'"></strong>
                                <div class="text-muted small" x-text="item.username || '-'"></div>
                            </td>
                            <td x-text="item.email || '-'"></td>
                            <td x-text="item.nis || '-'"></td>
                            <td x-text="item.kelas || '-'"></td>
                            <td x-text="yearName(item.tahun_ajaran_id)"></td>
                            <td x-text="formatDate(item.tanggal_lahir)"></td>
                            <td x-text="item.telepon || '-'"></td>
                        </tr>
                    </template>
                    <tr x-show="filteredStudents().length === 0">
                        <td colspan="7"><?php echo esc_html__('Belum ada siswa sesuai filter.', 'elearning-vd'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
