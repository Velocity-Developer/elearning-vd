<?php

defined('ABSPATH') || exit;
?>

<div
    x-show="active === 'tahun-ajaran'"
    x-data="{
        years: [],
        loadingYears: false,
        saving: false,
        modalOpen: false,
        error: '',
        form: {
            id: null,
            nama: '',
            mulai: '',
            selesai: '',
            status: 'draft'
        },
        init() {
            this.fetchYears();
        },
        fetchYears() {
            this.loadingYears = true;
            this.error = '';

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
                this.$dispatch('elvd-items-updated', { items: this.years });
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
                mulai: '',
                selesai: '',
                status: 'draft'
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
                mulai: item.mulai || '',
                selesai: item.selesai || '',
                status: item.status || 'draft'
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
                ? `${config.restUrl}/tahun-ajaran/${this.form.id}`
                : `${config.restUrl}/tahun-ajaran`;

            fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify({
                    nama: this.form.nama,
                    mulai: this.form.mulai,
                    selesai: this.form.selesai,
                    status: this.form.status
                })
            })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => {
                if (!response.ok) {
                    throw new Error(data?.message || 'Gagal menyimpan data tahun ajaran.');
                }

                if (isEdit) {
                    this.years = this.years.map((item) => Number(item.id) === Number(data.id) ? data : item);
                } else {
                    this.years = [data, ...this.years];
                }

                this.$dispatch('elvd-items-updated', { items: this.years });
                this.modalOpen = false;
                this.resetForm();
            })
            .catch((error) => {
                this.error = error.message || 'Gagal menyimpan data tahun ajaran.';
            })
            .finally(() => {
                this.saving = false;
            });
        },
        removeItem(item) {
            if (!confirm('Hapus tahun ajaran ini?')) {
                return;
            }

            fetch(`${config.restUrl}/tahun-ajaran/${item.id}`, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': config.nonce }
            })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response }) => {
                if (!response.ok) {
                    throw new Error('Gagal menghapus data tahun ajaran.');
                }

                this.years = this.years.filter((row) => Number(row.id) !== Number(item.id));
                this.$dispatch('elvd-items-updated', { items: this.years });
            })
            .catch((error) => {
                this.error = error.message || 'Gagal menghapus data tahun ajaran.';
            });
        },
        statusLabel(status) {
            return status === 'aktif' ? 'Aktif' : 'Draft';
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
        }
    }"
    @keydown.escape.window="closeModal()">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <div>
            </div>
            <button
                type="button"
                class="btn btn-primary elvd-action-button"
                x-show="config.isManager"
                @click="openCreate()">
                <?php echo esc_html__('Tambah Tahun Ajaran', 'elearning-vd'); ?>
            </button>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Nama', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Mulai', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Selesai', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Status', 'elearning-vd'); ?></th>
                        <th scope="col" class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingYears">
                        <td colspan="5"><?php echo esc_html__('Memuat data tahun ajaran...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="item in years" :key="item.id">
                        <tr>
                            <td>
                                <strong x-text="item.nama || '-'"></strong>
                            </td>
                            <td x-text="formatDate(item.mulai)"></td>
                            <td x-text="formatDate(item.selesai)"></td>
                            <td>
                                <span
                                    class="elvd-status-pill"
                                    :class="item.status === 'aktif' ? 'is-active' : ''"
                                    x-text="statusLabel(item.status)"></span>
                            </td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary elvd-row-action"
                                    x-show="config.isManager"
                                    @click="openEdit(item)">
                                    <?php echo esc_html__('Edit', 'elearning-vd'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger elvd-row-action"
                                    x-show="config.isManager"
                                    @click="removeItem(item)">
                                    <?php echo esc_html__('Hapus', 'elearning-vd'); ?>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loadingYears && years.length === 0">
                        <td colspan="5"><?php echo esc_html__('Belum ada tahun ajaran.', 'elearning-vd'); ?></td>
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
                    <h2 class="modal-title" x-text="form.id ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran'"></h2>
                    <button
                        type="button"
                        class="btn-close"
                        aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>"
                        @click="closeModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="elvd-tahun-ajaran-nama"><?php echo esc_html__('Nama Tahun Ajaran', 'elearning-vd'); ?></label>
                        <input
                            type="text"
                            class="form-control"
                            id="elvd-tahun-ajaran-nama"
                            x-model="form.nama"
                            required
                            maxlength="120"
                            placeholder="<?php echo esc_attr__('Contoh: 2026/2027', 'elearning-vd'); ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-tahun-ajaran-mulai"><?php echo esc_html__('Tanggal Mulai', 'elearning-vd'); ?></label>
                            <input
                                type="date"
                                class="form-control"
                                id="elvd-tahun-ajaran-mulai"
                                x-model="form.mulai"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-tahun-ajaran-selesai"><?php echo esc_html__('Tanggal Selesai', 'elearning-vd'); ?></label>
                            <input
                                type="date"
                                class="form-control"
                                id="elvd-tahun-ajaran-selesai"
                                x-model="form.selesai"
                                required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="elvd-tahun-ajaran-status"><?php echo esc_html__('Status', 'elearning-vd'); ?></label>
                        <select class="form-select" id="elvd-tahun-ajaran-status" x-model="form.status" required>
                            <option value="draft"><?php echo esc_html__('Draft', 'elearning-vd'); ?></option>
                            <option value="aktif"><?php echo esc_html__('Aktif', 'elearning-vd'); ?></option>
                        </select>
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