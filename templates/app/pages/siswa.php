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
$elvd_siswa_notice = '';
$elvd_can_manage_siswa = current_user_can('create_users') || current_user_can('edit_users');

if (
    'POST' === strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''))
    && isset($_POST['elvd_siswa_action'], $_POST['elvd_siswa_nonce'])
    && 'save' === sanitize_key((string) wp_unslash($_POST['elvd_siswa_action']))
) {
    $nonce = sanitize_text_field(wp_unslash((string) $_POST['elvd_siswa_nonce']));

    if (! wp_verify_nonce($nonce, 'elvd_save_siswa')) {
        $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Sesi form tidak valid. Silakan coba lagi.', 'elearning-vd') . '</div>';
    } elseif (! $elvd_can_manage_siswa) {
        $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin untuk menyimpan data siswa.', 'elearning-vd') . '</div>';
    } else {
        $posted = isset($_POST['elvd_siswa']) && is_array($_POST['elvd_siswa'])
            ? wp_unslash($_POST['elvd_siswa'])
            : [];
        $siswa_id = absint($posted['id'] ?? 0);
        $is_edit = 0 < $siswa_id;
        $target_user = $is_edit ? get_userdata($siswa_id) : null;

        if ($is_edit && (! $target_user instanceof WP_User || ! in_array('siswa', (array) $target_user->roles, true))) {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Data siswa tidak ditemukan.', 'elearning-vd') . '</div>';
        } elseif ($is_edit && ! current_user_can('edit_user', $siswa_id)) {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin mengubah siswa ini.', 'elearning-vd') . '</div>';
        } elseif (! $is_edit && ! current_user_can('create_users')) {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin menambah siswa.', 'elearning-vd') . '</div>';
        } else {
            $nama = sanitize_text_field((string) ($posted['nama'] ?? ''));
            $email = sanitize_email((string) ($posted['email'] ?? ''));
            $password = (string) ($posted['password'] ?? '');
            $user_data = [
                'display_name' => $nama,
                'user_email' => $email,
            ];

            if ($is_edit) {
                $user_data['ID'] = $siswa_id;

                if ('' !== $password) {
                    $user_data['user_pass'] = $password;
                }

                $saved = wp_update_user($user_data);
            } else {
                $username = sanitize_user((string) ($posted['username'] ?? ''), true);
                $email_parts = explode('@', $email);
                $fallback_username = sanitize_user((string) ($email_parts[0] ?? ''), true);
                $user_data['user_login'] = '' !== $username ? $username : $fallback_username;
                $user_data['user_pass'] = '' !== $password ? $password : wp_generate_password(12, true);
                $user_data['role'] = 'siswa';

                $saved = wp_insert_user($user_data);
            }

            if ($saved instanceof WP_Error) {
                $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html($saved->get_error_message()) . '</div>';
            } else {
                $saved_id = absint($saved);

                $elvd_siswa_meta = [
                    'elvd_nis' => sanitize_text_field((string) ($posted['nis'] ?? '')),
                    'elvd_kelas' => sanitize_text_field((string) ($posted['kelas'] ?? '')),
                    'elvd_tanggal_lahir' => sanitize_text_field((string) ($posted['tanggal_lahir'] ?? '')),
                    'elvd_telepon' => sanitize_text_field((string) ($posted['telepon'] ?? '')),
                ];

                foreach ($elvd_siswa_meta as $meta_key => $meta_value) {
                    update_user_meta($saved_id, $meta_key, $meta_value);
                }

                $elvd_siswa_notice = '<div class="alert alert-success">' . esc_html__('Data siswa berhasil disimpan.', 'elearning-vd') . '</div>';
            }
        }
    }
}

if (
    'POST' === strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''))
    && isset($_POST['elvd_siswa_action'], $_POST['elvd_siswa_nonce'])
    && 'delete' === sanitize_key((string) wp_unslash($_POST['elvd_siswa_action']))
) {
    $nonce = sanitize_text_field(wp_unslash((string) $_POST['elvd_siswa_nonce']));

    if (! wp_verify_nonce($nonce, 'elvd_delete_siswa')) {
        $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Sesi form tidak valid. Silakan coba lagi.', 'elearning-vd') . '</div>';
    } elseif (! current_user_can('delete_users')) {
        $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin menghapus siswa.', 'elearning-vd') . '</div>';
    } else {
        $delete_siswa_id = absint($_POST['elvd_siswa_id'] ?? 0);
        $delete_target = 0 < $delete_siswa_id ? get_userdata($delete_siswa_id) : null;

        if ($delete_siswa_id === get_current_user_id()) {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak dapat menghapus akun sendiri.', 'elearning-vd') . '</div>';
        } elseif (! $delete_target instanceof WP_User || ! in_array('siswa', (array) $delete_target->roles, true)) {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Data siswa tidak ditemukan.', 'elearning-vd') . '</div>';
        } elseif (wp_delete_user($delete_siswa_id)) {
            $elvd_siswa_notice = '<div class="alert alert-success">' . esc_html__('Data siswa berhasil dihapus.', 'elearning-vd') . '</div>';
        } else {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Gagal menghapus data siswa.', 'elearning-vd') . '</div>';
        }
    }
}
?>

<div
    x-show="active === 'siswa'"
    x-data="{
        siswaProfilUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(ELVD::app_route()) . '/siswa-profil/')); ?>,
        siswaProfilTab: 'profil',
        students: <?php echo esc_attr(wp_json_encode($elvd_siswa_items)); ?>,
        years: <?php echo esc_attr(wp_json_encode($elvd_years)); ?>,
        classes: <?php echo esc_attr(wp_json_encode($elvd_classes)); ?>,
        currentPage: 1,
        perPage: 15,
        modalOpen: false,
        saving: false,
        canManageSiswa: <?php echo $elvd_can_manage_siswa ? 'true' : 'false'; ?>,
        filters: {
            tahun_ajaran_id: '',
            kelas_id: ''
        },
        form: {
            id: null,
            username: '',
            nama: '',
            email: '',
            password: '',
            nis: '',
            kelas: '',
            tanggal_lahir: '',
            telepon: ''
        },
        init() {
            this.syncItems();

            this.$watch('filters.tahun_ajaran_id', () => {
                if (!this.isSelectedClassAvailable()) {
                    this.filters.kelas_id = '';
                }

                this.currentPage = 1;
                this.syncItems();
            });

            this.$watch('filters.kelas_id', () => {
                this.currentPage = 1;
                this.syncItems();
            });
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
        totalPages() {
            return Math.max(1, Math.ceil(this.filteredStudents().length / this.perPage));
        },
        paginatedStudents() {
            const start = (this.currentPage - 1) * this.perPage;

            return this.filteredStudents().slice(start, start + this.perPage);
        },
        paginationPages() {
            return Array.from({ length: this.totalPages() }, (_, index) => index + 1);
        },
        goToPage(page) {
            this.currentPage = Math.min(Math.max(Number(page), 1), this.totalPages());
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
        },
        canDeleteSiswa: <?php echo current_user_can('delete_users') ? 'true' : 'false'; ?>,
        resetForm() {
            this.form = {
                id: null,
                username: '',
                nama: '',
                email: '',
                password: '',
                nis: '',
                kelas: '',
                tanggal_lahir: '',
                telepon: ''
            };
        },
        openCreate() {
            this.resetForm();
            this.modalOpen = true;
        },
        closeModal() {
            if (this.saving) {
                return;
            }

            this.modalOpen = false;
        },
        submitForm() {
            this.saving = true;
            this.$nextTick(() => this.$refs.siswaForm.submit());
        },
        deleteSiswa(item) {
            if (!this.canDeleteSiswa || !confirm('Hapus siswa ini?')) {
                return;
            }

            this.$refs.siswaDeleteId.value = item.id;
            this.$refs.siswaDeleteForm.submit();
        }
    }"
    @keydown.escape.window="closeModal()">
    <div class="elvd-table-panel">
        <?php echo $elvd_siswa_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
        ?>
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
                <div class="col-md-6 col-xl-4">
                    <button
                        type="button"
                        class="btn btn-primary elvd-action-button"
                        x-show="canManageSiswa"
                        @click="openCreate()">
                        <?php echo esc_html__('Tambah Siswa', 'elearning-vd'); ?>
                    </button>
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
                        <th scope="col"><?php echo esc_html__('Tanggal Lahir', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="item in paginatedStudents()" :key="item.id">
                        <tr>
                            <td>
                                <strong x-text="item.nama || '-'"></strong>
                                <div class="text-muted small" x-text="item.username || '-'"></div>
                            </td>
                            <td x-text="item.email || '-'"></td>
                            <td x-text="item.nis || '-'"></td>
                            <td>
                                <span x-text="item.kelas || '-'"></span>
                                <span class="text-muted small" x-text="item.tahun_ajaran || ''"></span>
                            </td>
                            <td x-text="formatDate(item.tanggal_lahir)"></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary elvd-row-action" :href="`${siswaProfilUrl}${item.id}/${siswaProfilTab}`" :aria-label="'Profil Siswa: ' + item.nama">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-info elvd-row-action" :href="`${siswaProfilUrl}${item.id}/edit`" :aria-label="'Edit Siswa: ' + item.nama">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </a>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger elvd-row-action"
                                    x-show="canDeleteSiswa"
                                    @click="deleteSiswa(item)"
                                    :aria-label="'Hapus Siswa: ' + item.nama">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredStudents().length === 0">
                        <td colspan="8"><?php echo esc_html__('Belum ada siswa sesuai filter.', 'elearning-vd'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-3 border-top" x-show="filteredStudents().length > perPage">
            <div class="text-muted small">
                <span x-text="((currentPage - 1) * perPage) + 1"></span>
                -
                <span x-text="Math.min(currentPage * perPage, filteredStudents().length)"></span>
                <?php echo esc_html__('dari', 'elearning-vd'); ?>
                <span x-text="filteredStudents().length"></span>
            </div>
            <nav aria-label="<?php echo esc_attr__('Pagination siswa', 'elearning-vd'); ?>">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" :class="{ disabled: currentPage === 1 }">
                        <button type="button" class="page-link" @click="goToPage(currentPage - 1)" :disabled="currentPage === 1">
                            <?php echo esc_html__('Sebelumnya', 'elearning-vd'); ?>
                        </button>
                    </li>
                    <template x-for="page in paginationPages()" :key="page">
                        <li class="page-item" :class="{ active: page === currentPage }">
                            <button type="button" class="page-link" @click="goToPage(page)" x-text="page"></button>
                        </li>
                    </template>
                    <li class="page-item" :class="{ disabled: currentPage === totalPages() }">
                        <button type="button" class="page-link" @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages()">
                            <?php echo esc_html__('Berikutnya', 'elearning-vd'); ?>
                        </button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="modal-backdrop fade show" x-show="modalOpen" x-cloak></div>
    <div
        class="modal fade show elvd-modal"
        tabindex="-1"
        role="dialog"
        aria-modal="true"
        x-show="modalOpen"
        x-cloak>
        <div class="modal-dialog modal-dialog-centered">

            <form class="modal-content" method="post" x-ref="siswaForm" @submit.prevent="submitForm()">
                <input type="hidden" name="elvd_siswa_action" value="save">
                <input type="hidden" name="elvd_siswa_nonce" value="<?php echo esc_attr(wp_create_nonce('elvd_save_siswa')); ?>">
                <input type="hidden" name="elvd_siswa[id]" :value="form.id || ''">

                <div class="modal-header">
                    <h2 class="modal-title" x-text="form.id ? 'Edit Siswa' : 'Tambah Siswa'"></h2>
                    <button
                        type="button"
                        class="btn-close"
                        aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>"
                        @click="closeModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-siswa-username"><?php echo esc_html__('Username', 'elearning-vd'); ?></label>
                            <input
                                type="text"
                                class="form-control"
                                id="elvd-siswa-username"
                                name="elvd_siswa[username]"
                                x-model="form.username"
                                :required="!form.id"
                                :readonly="Boolean(form.id)"
                                maxlength="60"
                                placeholder="<?php echo esc_attr__('Contoh: siswa.1234', 'elearning-vd'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-siswa-nama"><?php echo esc_html__('Nama Lengkap', 'elearning-vd'); ?></label>
                            <input
                                type="text"
                                class="form-control"
                                id="elvd-siswa-nama"
                                name="elvd_siswa[nama]"
                                x-model="form.nama"
                                required
                                maxlength="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-siswa-email"><?php echo esc_html__('Email', 'elearning-vd'); ?></label>
                            <input
                                type="email"
                                class="form-control"
                                id="elvd-siswa-email"
                                name="elvd_siswa[email]"
                                x-model="form.email"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-siswa-password"><?php echo esc_html__('Password', 'elearning-vd'); ?></label>
                            <input
                                type="password"
                                class="form-control"
                                id="elvd-siswa-password"
                                name="elvd_siswa[password]"
                                x-model="form.password"
                                :required="!form.id"
                                autocomplete="new-password"
                                placeholder="<?php echo esc_attr__('Kosongkan jika tidak diubah', 'elearning-vd'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-siswa-nis"><?php echo esc_html__('NIS', 'elearning-vd'); ?></label>
                            <input
                                type="text"
                                class="form-control"
                                id="elvd-siswa-nis"
                                name="elvd_siswa[nis]"
                                x-model="form.nis"
                                maxlength="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-siswa-kelas"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-siswa-kelas" name="elvd_siswa[kelas]" x-model="form.kelas">
                                <option value=""><?php echo esc_html__('Pilih kelas', 'elearning-vd'); ?></option>
                                <template x-for="kelas in filteredClasses()" :key="kelas.id">
                                    <option :value="String(kelas.id)" x-text="kelas.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-siswa-tanggal-lahir"><?php echo esc_html__('Tanggal Lahir', 'elearning-vd'); ?></label>
                            <input
                                type="date"
                                class="form-control"
                                id="elvd-siswa-tanggal-lahir"
                                name="elvd_siswa[tanggal_lahir]"
                                x-model="form.tanggal_lahir">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-siswa-telepon"><?php echo esc_html__('No. Telepon', 'elearning-vd'); ?></label>
                            <input
                                type="tel"
                                class="form-control"
                                id="elvd-siswa-telepon"
                                name="elvd_siswa[telepon]"
                                x-model="form.telepon">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeModal()">
                        <?php echo esc_html__('Batal', 'elearning-vd'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary elvd-action-button" :disabled="saving">
                        <span x-show="!saving"><?php echo esc_html__('Simpan', 'elearning-vd'); ?></span>
                        <span x-show="saving"><?php echo esc_html__('Menyimpan...', 'elearning-vd'); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <form method="post" x-ref="siswaDeleteForm" class="d-none" aria-hidden="true">
        <input type="hidden" name="elvd_siswa_action" value="delete">
        <input type="hidden" name="elvd_siswa_nonce" value="<?php echo esc_attr(wp_create_nonce('elvd_delete_siswa')); ?>">
        <input type="hidden" name="elvd_siswa_id" value="" x-ref="siswaDeleteId">
    </form>
</div>