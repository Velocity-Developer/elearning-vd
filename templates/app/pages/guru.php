<?php

defined('ABSPATH') || exit;

$elvd_can_manage_guru = current_user_can('create_users') || current_user_can('edit_users');
$elvd_guru_notice = '';

$elvd_guru_meta_keys = [
    'nip' => 'elvd_nip',
    'mata_pelajaran' => 'elvd_mata_pelajaran',
    'telepon' => 'elvd_telepon',
    'alamat' => 'elvd_alamat',
];

if (
    'POST' === strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''))
    && isset($_POST['elvd_guru_action'], $_POST['elvd_guru_nonce'])
    && 'save' === sanitize_key((string) wp_unslash($_POST['elvd_guru_action']))
) {
    $nonce = sanitize_text_field(wp_unslash((string) $_POST['elvd_guru_nonce']));

    if (! wp_verify_nonce($nonce, 'elvd_save_guru')) {
        $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Sesi form tidak valid. Silakan coba lagi.', 'elearning-vd') . '</div>';
    } elseif (! $elvd_can_manage_guru) {
        $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin untuk menyimpan data guru.', 'elearning-vd') . '</div>';
    } else {
        $posted = isset($_POST['elvd_guru']) && is_array($_POST['elvd_guru'])
            ? wp_unslash($_POST['elvd_guru'])
            : [];
        $guru_id = absint($posted['id'] ?? 0);
        $is_edit = 0 < $guru_id;
        $target_user = $is_edit ? get_userdata($guru_id) : null;

        if ($is_edit && (! $target_user instanceof WP_User || ! in_array('guru', (array) $target_user->roles, true))) {
            $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Data guru tidak ditemukan.', 'elearning-vd') . '</div>';
        } elseif ($is_edit && ! current_user_can('edit_user', $guru_id)) {
            $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin mengubah guru ini.', 'elearning-vd') . '</div>';
        } elseif (! $is_edit && ! current_user_can('create_users')) {
            $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin menambah guru.', 'elearning-vd') . '</div>';
        } else {
            $nama = sanitize_text_field((string) ($posted['nama'] ?? ''));
            $email = sanitize_email((string) ($posted['email'] ?? ''));
            $password = (string) ($posted['password'] ?? '');
            $user_data = [
                'display_name' => $nama,
                'user_email' => $email,
            ];

            if ($is_edit) {
                $user_data['ID'] = $guru_id;

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
                $user_data['role'] = 'guru';

                $saved = wp_insert_user($user_data);
            }

            if ($saved instanceof WP_Error) {
                $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html($saved->get_error_message()) . '</div>';
            } else {
                $saved_id = absint($saved);

                foreach ($elvd_guru_meta_keys as $field => $meta_key) {
                    $value = (string) ($posted[$field] ?? '');
                    $value = 'alamat' === $field ? sanitize_textarea_field($value) : sanitize_text_field($value);
                    update_user_meta($saved_id, $meta_key, $value);
                }

                $elvd_guru_notice = '<div class="alert alert-success">' . esc_html__('Data guru berhasil disimpan.', 'elearning-vd') . '</div>';
            }
        }
    }
}

$elvd_guru_items = array_map(
    static function (WP_User $user) use ($elvd_guru_meta_keys): array {
        return [
            'id' => (int) $user->ID,
            'nama' => '' !== trim($user->display_name) ? $user->display_name : $user->user_login,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'nip' => (string) get_user_meta($user->ID, $elvd_guru_meta_keys['nip'], true),
            'mata_pelajaran' => (string) get_user_meta($user->ID, $elvd_guru_meta_keys['mata_pelajaran'], true),
            'telepon' => (string) get_user_meta($user->ID, $elvd_guru_meta_keys['telepon'], true),
            'alamat' => (string) get_user_meta($user->ID, $elvd_guru_meta_keys['alamat'], true),
        ];
    },
    get_users(
        [
            'role' => 'guru',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]
    )
);
?>

<div
    x-show="active === 'guru'"
    x-data="{
        guruProfilUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(ELVD::app_route()) . '/guru-profil/')); ?>,
        teachers: <?php echo esc_attr(wp_json_encode($elvd_guru_items)); ?>,
        currentPage: 1,
        perPage: 15,
        modalOpen: false,
        saving: false,
        canManageGuru: <?php echo $elvd_can_manage_guru ? 'true' : 'false'; ?>,
        form: {
            id: null,
            username: '',
            nama: '',
            email: '',
            nip: '',
            mata_pelajaran: '',
            telepon: '',
            alamat: '',
            password: ''
        },
        init() {
            this.$dispatch('elvd-items-updated', { items: this.teachers });
        },
        totalPages() {
            return Math.max(1, Math.ceil(this.teachers.length / this.perPage));
        },
        paginatedTeachers() {
            const start = (this.currentPage - 1) * this.perPage;

            return this.teachers.slice(start, start + this.perPage);
        },
        paginationPages() {
            return Array.from({ length: this.totalPages() }, (_, index) => index + 1);
        },
        goToPage(page) {
            this.currentPage = Math.min(Math.max(Number(page), 1), this.totalPages());
        },
        resetForm() {
            this.form = {
                id: null,
                username: '',
                nama: '',
                email: '',
                nip: '',
                mata_pelajaran: '',
                telepon: '',
                alamat: '',
                password: ''
            };
        },
        openCreate() {
            this.resetForm();
            this.modalOpen = true;
        },
        openEdit(item) {
            this.form = {
                id: item.id,
                username: item.username || '',
                nama: item.nama || '',
                email: item.email || '',
                nip: item.nip || '',
                mata_pelajaran: item.mata_pelajaran || '',
                telepon: item.telepon || '',
                alamat: item.alamat || '',
                password: ''
            };
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
            this.$nextTick(() => this.$refs.guruForm.submit());
        }
    }"
    @keydown.escape.window="closeModal()">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <div></div>
            <button
                type="button"
                class="btn btn-primary elvd-action-button"
                x-show="canManageGuru"
                @click="openCreate()">
                <?php echo esc_html__('Tambah Guru', 'elearning-vd'); ?>
            </button>
        </div>

        <?php echo $elvd_guru_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
        ?>

        <div class="table-responsive">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Nama', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Email', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('NIP', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Telepon', 'elearning-vd'); ?></th>
                        <th scope="col" class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="item in paginatedTeachers()" :key="item.id">
                        <tr>
                            <td>
                                <strong x-text="item.nama || '-'"></strong>
                            </td>
                            <td x-text="item.email || '-'"></td>
                            <td x-text="item.nip || '-'"></td>
                            <td x-text="item.mata_pelajaran || '-'"></td>
                            <td x-text="item.telepon || '-'"></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary elvd-row-action" :href="`${guruProfilUrl}${item.id}/profil`">
                                    <?php echo esc_html__('Profil', 'elearning-vd'); ?>
                                </a>
                                <a class="btn btn-sm btn-outline-primary elvd-row-action" :href="`${guruProfilUrl}${item.id}/edit`">
                                    <?php echo esc_html__('Edit', 'elearning-vd'); ?>
                                </a>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="teachers.length === 0">
                        <td colspan="6"><?php echo esc_html__('Belum ada guru.', 'elearning-vd'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-3 border-top" x-show="teachers.length > perPage">
            <div class="text-muted small">
                <span x-text="((currentPage - 1) * perPage) + 1"></span>
                -
                <span x-text="Math.min(currentPage * perPage, teachers.length)"></span>
                <?php echo esc_html__('dari', 'elearning-vd'); ?>
                <span x-text="teachers.length"></span>
            </div>
            <nav aria-label="<?php echo esc_attr__('Pagination guru', 'elearning-vd'); ?>">
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

            <form class="modal-content" method="post" x-ref="guruForm" @submit.prevent="submitForm()">
                <input type="hidden" name="elvd_guru_action" value="save">
                <input type="hidden" name="elvd_guru_nonce" value="<?php echo esc_attr(wp_create_nonce('elvd_save_guru')); ?>">
                <input type="hidden" name="elvd_guru[id]" :value="form.id || ''">

                <div class="modal-header">
                    <h2 class="modal-title" x-text="form.id ? 'Edit Guru' : 'Tambah Guru'"></h2>
                    <button
                        type="button"
                        class="btn-close"
                        aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>"
                        @click="closeModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-guru-username"><?php echo esc_html__('Username', 'elearning-vd'); ?></label>
                            <input
                                type="text"
                                class="form-control"
                                id="elvd-guru-username"
                                name="elvd_guru[username]"
                                x-model="form.username"
                                :required="!form.id"
                                :readonly="Boolean(form.id)"
                                maxlength="60"
                                placeholder="<?php echo esc_attr__('Contoh: guru.matematika', 'elearning-vd'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-guru-nama"><?php echo esc_html__('Nama Lengkap', 'elearning-vd'); ?></label>
                            <input
                                type="text"
                                class="form-control"
                                id="elvd-guru-nama"
                                name="elvd_guru[nama]"
                                x-model="form.nama"
                                required
                                maxlength="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-guru-email"><?php echo esc_html__('Email', 'elearning-vd'); ?></label>
                            <input
                                type="email"
                                class="form-control"
                                id="elvd-guru-email"
                                name="elvd_guru[email]"
                                x-model="form.email"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-guru-password"><?php echo esc_html__('Password', 'elearning-vd'); ?></label>
                            <input
                                type="password"
                                class="form-control"
                                id="elvd-guru-password"
                                name="elvd_guru[password]"
                                x-model="form.password"
                                :required="!form.id"
                                autocomplete="new-password"
                                placeholder="<?php echo esc_attr__('Kosongkan jika tidak diubah', 'elearning-vd'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-guru-nip"><?php echo esc_html__('NIP', 'elearning-vd'); ?></label>
                            <input
                                type="text"
                                class="form-control"
                                id="elvd-guru-nip"
                                name="elvd_guru[nip]"
                                x-model="form.nip">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-guru-mapel"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></label>
                            <input
                                type="text"
                                class="form-control"
                                id="elvd-guru-mapel"
                                name="elvd_guru[mata_pelajaran]"
                                x-model="form.mata_pelajaran">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-guru-telepon"><?php echo esc_html__('No. Telepon', 'elearning-vd'); ?></label>
                            <input
                                type="tel"
                                class="form-control"
                                id="elvd-guru-telepon"
                                name="elvd_guru[telepon]"
                                x-model="form.telepon">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="elvd-guru-alamat"><?php echo esc_html__('Alamat', 'elearning-vd'); ?></label>
                            <textarea
                                class="form-control"
                                id="elvd-guru-alamat"
                                name="elvd_guru[alamat]"
                                x-model="form.alamat"
                                rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" @click="closeModal()">
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

</div>