<?php

defined('ABSPATH') || exit;
?>

<div
    x-show="active === 'mata-pelajaran'"
    x-data="{
        subjects: [],
        loadingSubjects: false,
        saving: false,
        modalOpen: false,
        error: '',
        form: {
            id: null,
            nama: '',
            kode: '',
            deskripsi: ''
        },
        init() {
            this.fetchSubjects();
        },
        fetchSubjects() {
            this.loadingSubjects = true;
            this.error = '';

            fetch(`${config.restUrl}/mata-pelajaran?per_page=100`, {
                headers: { 'X-WP-Nonce': config.nonce }
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat data mata pelajaran.');
                }

                return response.json();
            })
            .then((data) => {
                this.subjects = Array.isArray(data) ? data : [];
                this.$dispatch('elvd-items-updated', { items: this.subjects });
            })
            .catch((error) => {
                this.error = error.message || 'Gagal memuat data mata pelajaran.';
            })
            .finally(() => {
                this.loadingSubjects = false;
            });
        },
        resetForm() {
            this.form = {
                id: null,
                nama: '',
                kode: '',
                deskripsi: ''
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
                kode: item.kode || '',
                deskripsi: item.deskripsi || ''
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
                ? `${config.restUrl}/mata-pelajaran/${this.form.id}`
                : `${config.restUrl}/mata-pelajaran`;

            fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({
                    nama: this.form.nama,
                    kode: this.form.kode,
                    deskripsi: this.form.deskripsi
                })
            })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => {
                if (!response.ok) {
                    throw new Error(data?.message || 'Gagal menyimpan data mata pelajaran.');
                }

                if (isEdit) {
                    this.subjects = this.subjects.map((item) => Number(item.id) === Number(data.id) ? data : item);
                } else {
                    this.subjects = [data, ...this.subjects];
                }

                this.$dispatch('elvd-items-updated', { items: this.subjects });
                this.modalOpen = false;
                this.resetForm();
            })
            .catch((error) => {
                this.error = error.message || 'Gagal menyimpan data mata pelajaran.';
            })
            .finally(() => {
                this.saving = false;
            });
        },
        shortDescription(value) {
            if (!value) {
                return '-';
            }

            return value.length > 120 ? `${value.slice(0, 120)}...` : value;
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
                <?php echo esc_html__('Tambah Mata Pelajaran', 'elearning-vd'); ?>
            </button>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Nama', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Kode', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Deskripsi', 'elearning-vd'); ?></th>
                        <th scope="col" class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingSubjects">
                        <td colspan="4"><?php echo esc_html__('Memuat data mata pelajaran...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="item in subjects" :key="item.id">
                        <tr>
                            <td>
                                <strong x-text="item.nama || '-'"></strong>
                            </td>
                            <td x-text="item.kode || '-'"></td>
                            <td x-text="shortDescription(item.deskripsi)"></td>
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
                    <tr x-show="!loadingSubjects && subjects.length === 0">
                        <td colspan="4"><?php echo esc_html__('Belum ada mata pelajaran.', 'elearning-vd'); ?></td>
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
                    <h2 class="modal-title" x-text="form.id ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran'"></h2>
                    <button
                        type="button"
                        class="btn-close"
                        aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>"
                        @click="closeModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="elvd-mata-pelajaran-nama"><?php echo esc_html__('Nama Mata Pelajaran', 'elearning-vd'); ?></label>
                        <input
                            type="text"
                            class="form-control"
                            id="elvd-mata-pelajaran-nama"
                            x-model="form.nama"
                            required
                            maxlength="120"
                            placeholder="<?php echo esc_attr__('Contoh: Matematika', 'elearning-vd'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="elvd-mata-pelajaran-kode"><?php echo esc_html__('Kode', 'elearning-vd'); ?></label>
                        <input
                            type="text"
                            class="form-control"
                            id="elvd-mata-pelajaran-kode"
                            x-model="form.kode"
                            maxlength="40"
                            placeholder="<?php echo esc_attr__('Contoh: mtk', 'elearning-vd'); ?>">
                    </div>
                    <div>
                        <label class="form-label" for="elvd-mata-pelajaran-deskripsi"><?php echo esc_html__('Deskripsi', 'elearning-vd'); ?></label>
                        <textarea
                            class="form-control"
                            id="elvd-mata-pelajaran-deskripsi"
                            x-model="form.deskripsi"
                            rows="4"
                            placeholder="<?php echo esc_attr__('Ringkasan singkat mata pelajaran.', 'elearning-vd'); ?>"></textarea>
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
