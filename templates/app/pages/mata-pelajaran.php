<?php

defined('ABSPATH') || exit;
?>

<script>
    window.elvdMataPelajaranConfig = {
        subjects: [],
        loadingSubjects: false,
        saving: false,
        modalOpen: false,
        error: '',
        filterNama: '',
        page: 1,
        perPage: 10,
        filteredSubjects() {
            const nama = this.filterNama.trim().toLowerCase();

            if (!nama) {
                return this.subjects;
            }

            return this.subjects.filter((item) => String(item.nama || '').toLowerCase().includes(nama));
        },
        totalPages() {
            return Math.max(1, Math.ceil(this.filteredSubjects().length / this.perPage));
        },
        pageItems() {
            const start = (this.page - 1) * this.perPage;

            return this.filteredSubjects().slice(start, start + this.perPage);
        },
        goPage(p) {
            this.page = Math.min(Math.max(1, p), this.totalPages());
        },
        form: {
            id: null,
            nama: '',
            kode: '',
            kode_warna: '#3b82f6',
            deskripsi: ''
        },
        init() {
            this.fetchSubjects();
            this.$watch('filterNama', () => {
                this.page = 1;
            });
        },
        fetchSubjects() {
            this.loadingSubjects = true;
            this.error = '';

            fetch(`${config.restUrl}/mata-pelajaran?per_page=100`, {
                    headers: {
                        'X-WP-Nonce': config.nonce
                    }
                })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Gagal memuat data mata pelajaran.');
                    }

                    return response.json();
                })
                .then((data) => {
                    this.subjects = Array.isArray(data) ? data : [];
                    this.$dispatch('elvd-items-updated', {
                        items: this.subjects
                    });
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
                kode_warna: '#3b82f6',
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
                kode_warna: item.kode_warna || '#3b82f6',
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
            const url = isEdit ?
                `${config.restUrl}/mata-pelajaran/${this.form.id}` :
                `${config.restUrl}/mata-pelajaran`;

            fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce
                    },
                    body: JSON.stringify({
                        nama: this.form.nama,
                        kode: this.form.kode,
                        kode_warna: this.form.kode_warna,
                        deskripsi: this.form.deskripsi
                    })
                })
                .then((response) => response.json().then((data) => ({
                    response,
                    data
                })))
                .then(({
                    response,
                    data
                }) => {
                    if (!response.ok) {
                        throw new Error(data?.message || 'Gagal menyimpan data mata pelajaran.');
                    }

                    if (isEdit) {
                        this.subjects = this.subjects.map((item) => Number(item.id) === Number(data.id) ? data : item);
                    } else {
                        this.subjects = [data, ...this.subjects];
                    }

                    this.$dispatch('elvd-items-updated', {
                        items: this.subjects
                    });
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
        },
        deleteSubject(item) {
            if (!window.confirm('Yakin hapus mata pelajaran ini?')) {
                return;
            }

            fetch(`${config.restUrl}/mata-pelajaran/${item.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': config.nonce
                    }
                })
                .then((response) => response.json().then((data) => ({
                    response,
                    data
                })))
                .then(({
                    response
                }) => {
                    if (!response.ok) {
                        throw new Error('Gagal menghapus mata pelajaran.');
                    }

                    this.subjects = this.subjects.filter((subject) => Number(subject.id) !== Number(item.id));
                    this.$dispatch('elvd-items-updated', {
                        items: this.subjects
                    });
                })
                .catch((error) => {
                    this.error = error.message || 'Gagal menghapus mata pelajaran.';
                });
        }
    };
</script>

<div
    x-show="active === 'mata-pelajaran'"
    x-data="window.elvdMataPelajaranConfig"
    @keydown.escape.window="closeModal()">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <input
                type="search"
                class="form-control w-auto elvd-filter-input"
                x-model="filterNama"
                placeholder="<?php echo esc_attr__('Cari nama mata pelajaran', 'elearning-vd'); ?>">
            <button
                type="button"
                class="btn btn-primary elvd-action-button"
                x-show="config.currentRole === 'administrator'"
                @click="openCreate()">
                <i class="bi bi-plus-lg me-1"></i><?php echo esc_html__('Tambah Mata Pelajaran', 'elearning-vd'); ?>
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
                        <th scope="col" class="text-end" x-show="config.currentRole === 'administrator'"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingSubjects">
                        <td colspan="5"><?php echo esc_html__('Memuat data mata pelajaran...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="item in pageItems()" :key="item.id">
                        <tr>
                            <td>
                                <span class="text-white px-2 rounded-pill me-2" x-bind:style="{'backgroundColor': item.kode_warna || '#dddddd'}"></span>
                                <strong x-text="item.nama || '-'"></strong>
                            </td>
                            <td x-text="item.kode || '-'"></td>
                            <td x-text="shortDescription(item.deskripsi)"></td>
                            <td class="text-end" x-show="config.currentRole === 'administrator'">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary elvd-row-action"
                                    x-show="config.currentRole === 'administrator'"
                                    @click="openEdit(item)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger elvd-row-action"
                                    x-show="config.currentRole === 'administrator'"
                                    @click="deleteSubject(item)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loadingSubjects && filteredSubjects().length === 0">
                        <td colspan="4"><?php echo esc_html__('Belum ada mata pelajaran.', 'elearning-vd'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3"
            x-show="!loadingSubjects && totalPages() > 1"
            aria-label="<?php echo esc_attr__('Paginasi mata pelajaran', 'elearning-vd'); ?>">
            <small class="text-muted">
                <?php echo esc_html__('Halaman', 'elearning-vd'); ?> <span x-text="page"></span> / <span x-text="totalPages()"></span>
            </small>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item" :class="page === 1 ? 'disabled' : ''">
                    <a class="page-link" href="#" @click.prevent="goPage(page - 1)">
                        <?php echo esc_html__('Sebelumnya', 'elearning-vd'); ?>
                    </a>
                </li>
                <template x-for="p in totalPages()" :key="p">
                    <li class="page-item" :class="page === p ? 'active' : ''">
                        <a class="page-link" href="#" @click.prevent="goPage(p)" x-text="p"></a>
                    </li>
                </template>
                <li class="page-item" :class="page === totalPages() ? 'disabled' : ''">
                    <a class="page-link" href="#" @click.prevent="goPage(page + 1)">
                        <?php echo esc_html__('Berikutnya', 'elearning-vd'); ?>
                    </a>
                </li>
            </ul>
        </nav>
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
                    <div class="mb-3">
                        <label class="form-label" for="elvd-mata-pelajaran-kode-warna"><?php echo esc_html__('Kode Warna', 'elearning-vd'); ?></label>
                        <input
                            type="color"
                            class="form-control form-control-color"
                            id="elvd-mata-pelajaran-kode-warna"
                            x-model="form.kode_warna"
                            title="<?php echo esc_attr__('Pilih warna mata pelajaran', 'elearning-vd'); ?>">
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
                    <button type="button" class="btn btn-secondary" @click="closeModal()">
                        <i class="bi bi-x-lg me-1"></i><?php echo esc_html__('Batal', 'elearning-vd'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary elvd-action-button" :disabled="saving">
                        <span x-show="!saving"><i class="bi bi-check-lg me-1"></i><?php echo esc_html__('Simpan', 'elearning-vd'); ?></span>
                        <span x-show="saving"><?php echo esc_html__('Menyimpan...', 'elearning-vd'); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>