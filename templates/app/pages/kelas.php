<?php

defined('ABSPATH') || exit;

$elvd_guru_options = array_map(
    static function (WP_User $user): array {
        return [
            'id' => (int) $user->ID,
            'nama' => '' !== trim($user->display_name) ? $user->display_name : $user->user_login,
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
    x-show="active === 'kelas'"
    x-data="{
        classes: [],
        years: [],
        teachers: <?php echo esc_attr(wp_json_encode($elvd_guru_options)); ?>,
        loadingClasses: false,
        loadingYears: false,
        saving: false,
        modalOpen: false,
        error: '',
        form: {
            id: null,
            nama: '',
            tingkat: '',
            wali_guru_id: '',
            tahun_ajaran_id: ''
        },
        init() {
            this.fetchClasses();
            this.fetchYears();
        },
        fetchClasses() {
            this.loadingClasses = true;
            this.error = '';

            fetch(`${config.restUrl}/kelas?per_page=100`, {
                headers: { 'X-WP-Nonce': config.nonce }
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat data kelas.');
                }

                return response.json();
            })
            .then((data) => {
                this.classes = Array.isArray(data) ? data : [];
                this.$dispatch('elvd-items-updated', { items: this.classes });
            })
            .catch((error) => {
                this.error = error.message || 'Gagal memuat data kelas.';
            })
            .finally(() => {
                this.loadingClasses = false;
            });
        },
        fetchYears() {
            this.loadingYears = true;

            fetch(`${config.restUrl}/tahun-ajaran?per_page=100`, {
                headers: { 'X-WP-Nonce': config.nonce }
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat data tahun ajaran.');
                }

                return response.json();
            })
            .then((data) => {
                this.years = Array.isArray(data) ? data : [];
            })
            .catch((error) => {
                this.error = error.message || 'Gagal memuat data tahun ajaran.';
            })
            .finally(() => {
                this.loadingYears = false;
            });
        },
        resetForm() {
            this.form = {
                id: null,
                nama: '',
                tingkat: '',
                wali_guru_id: '',
                tahun_ajaran_id: ''
            };
        },
        openCreate() {
            this.resetForm();
            this.error = '';
            this.modalOpen = true;
        },
        openEdit(item) {
            this.form = {
                id: item.id,
                nama: item.nama || '',
                tingkat: item.tingkat || '',
                wali_guru_id: item.wali_guru_id ? String(item.wali_guru_id) : '',
                tahun_ajaran_id: item.tahun_ajaran_id ? String(item.tahun_ajaran_id) : ''
            };
            this.error = '';
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
            this.error = '';

            const isEdit = Boolean(this.form.id);
            const url = isEdit
                ? `${config.restUrl}/kelas/${this.form.id}`
                : `${config.restUrl}/kelas`;

            fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({
                    nama: this.form.nama,
                    tingkat: this.form.tingkat,
                    wali_guru_id: this.form.wali_guru_id ? Number(this.form.wali_guru_id) : 0,
                    tahun_ajaran_id: this.form.tahun_ajaran_id ? Number(this.form.tahun_ajaran_id) : 0
                })
            })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => {
                if (!response.ok) {
                    throw new Error(data?.message || 'Gagal menyimpan data kelas.');
                }

                if (isEdit) {
                    this.classes = this.classes.map((item) => Number(item.id) === Number(data.id) ? data : item);
                } else {
                    this.classes = [data, ...this.classes];
                }

                this.$dispatch('elvd-items-updated', { items: this.classes });
                this.modalOpen = false;
                this.resetForm();
            })
            .catch((error) => {
                this.error = error.message || 'Gagal menyimpan data kelas.';
            })
            .finally(() => {
                this.saving = false;
            });
        },
        teacherName(id) {
            const teacher = this.teachers.find((item) => Number(item.id) === Number(id));

            return teacher ? teacher.nama : '-';
        },
        yearName(id) {
            const year = this.years.find((item) => Number(item.id) === Number(id));

            return year ? year.nama : '-';
        }
    }"
    @keydown.escape.window="closeModal()">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <div></div>
            <button
                type="button"
                class="btn btn-primary elvd-action-button"
                x-show="config.isManager"
                @click="openCreate()">
                <?php echo esc_html__('Tambah Kelas', 'elearning-vd'); ?>
            </button>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Nama', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Tingkat', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Wali Guru', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></th>
                        <th scope="col" class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingClasses">
                        <td colspan="5"><?php echo esc_html__('Memuat data kelas...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="item in classes" :key="item.id">
                        <tr>
                            <td>
                                <strong x-text="item.nama || '-'"></strong>
                            </td>
                            <td x-text="item.tingkat || '-'"></td>
                            <td x-text="teacherName(item.wali_guru_id)"></td>
                            <td x-text="yearName(item.tahun_ajaran_id)"></td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary elvd-row-action"
                                    x-show="config.isManager"
                                    @click="openEdit(item)">
                                    <?php echo esc_html__('Edit', 'elearning-vd'); ?>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loadingClasses && classes.length === 0">
                        <td colspan="5"><?php echo esc_html__('Belum ada kelas.', 'elearning-vd'); ?></td>
                    </tr>
                </tbody>
            </table>
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
            <form class="modal-content" @submit.prevent="submitForm()">
                <div class="modal-header">
                    <h2 class="modal-title" x-text="form.id ? 'Edit Kelas' : 'Tambah Kelas'"></h2>
                    <button
                        type="button"
                        class="btn-close"
                        aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>"
                        @click="closeModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="elvd-kelas-nama"><?php echo esc_html__('Nama Kelas', 'elearning-vd'); ?></label>
                        <input
                            type="text"
                            class="form-control"
                            id="elvd-kelas-nama"
                            x-model="form.nama"
                            required
                            maxlength="120"
                            placeholder="<?php echo esc_attr__('Contoh: VII A', 'elearning-vd'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="elvd-kelas-tingkat"><?php echo esc_html__('Tingkat', 'elearning-vd'); ?></label>
                        <input
                            type="text"
                            class="form-control"
                            id="elvd-kelas-tingkat"
                            x-model="form.tingkat"
                            required
                            maxlength="50"
                            placeholder="<?php echo esc_attr__('Contoh: VII', 'elearning-vd'); ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-kelas-wali-guru"><?php echo esc_html__('Wali Guru', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-kelas-wali-guru" x-model="form.wali_guru_id">
                                <option value=""><?php echo esc_html__('Pilih wali guru', 'elearning-vd'); ?></option>
                                <template x-for="teacher in teachers" :key="teacher.id">
                                    <option :value="String(teacher.id)" x-text="teacher.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-kelas-tahun-ajaran"><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-kelas-tahun-ajaran" x-model="form.tahun_ajaran_id" :disabled="loadingYears">
                                <option value="" x-text="loadingYears ? 'Memuat tahun ajaran...' : 'Pilih tahun ajaran'"></option>
                                <template x-for="year in years" :key="year.id">
                                    <option :value="String(year.id)" x-text="year.nama"></option>
                                </template>
                            </select>
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
