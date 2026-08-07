<?php

defined('ABSPATH') || exit;

$elvd_ta_tugas_id = absint((int) get_query_var('elvd_tugas_id', 0));
$elvd_ta_back_url = untrailingslashit(ELVD::app_route()) . '/tugas/';
$elvd_ta_rest_tugas = untrailingslashit(rest_url('wp/v2/elvd_tugas'));
$elvd_ta_rest_pengerjaan = untrailingslashit(rest_url(ELVD_REST_NAMESPACE . '/pengerjaan-tugas'));
?>

<div x-show="active === 'tugas-answer'" x-data="{
    restTugasUrl: <?php echo esc_attr(wp_json_encode($elvd_ta_rest_tugas)); ?>,
    pengerjaanUrl: <?php echo esc_attr(wp_json_encode($elvd_ta_rest_pengerjaan)); ?>,
    backUrl: <?php echo esc_attr(wp_json_encode($elvd_ta_back_url)); ?>,
    tugasId: <?php echo esc_attr((string) $elvd_ta_tugas_id); ?>,
    isManager: config.isManager,
    loading: true,
    error: '',
    tugas: null,
    items: [],
    openItem: null,
    form: {
        nilai: '',
        catatan: ''
    },
    saving: false,
    init() {
        if (!this.tugasId) {
            this.loading = false;
            this.error = 'Tugas tidak ditemukan.';
            return;
        }

        this.loadData();
    },
    titleOf(item) {
        return (item.title && (item.title.rendered || item.title.raw)) ? (item.title.rendered || item.title.raw) : '';
    },
    metaValue(item, key) {
        return item.meta && item.meta[key] ? item.meta[key] : '';
    },
    formatNilai(item) {
        return item.nilai === null || item.nilai === '' ? '–' : `${Math.round(Number(item.nilai))}`;
    },
    formatTanggal(value) {
        return value ? String(value).replace('T', ' ').slice(0, 16) : '–';
    },
    fileName(file) {
        if (!file) {
            return '';
        }

        const parts = String(file).split('/');

        return parts[parts.length - 1];
    },
    loadData() {
        this.loading = true;
        this.error = '';

        Promise.all([
            fetch(`${this.restTugasUrl}/${this.tugasId}`, { headers: { 'X-WP-Nonce': config.nonce } }),
            fetch(`${this.pengerjaanUrl}?per_page=100&tugas_id=${this.tugasId}`, { headers: { 'X-WP-Nonce': config.nonce } })
        ])
        .then((responses) => {
            responses.forEach((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat data pengerjaan.');
                }
            });

            return Promise.all(responses.map((response) => response.json()));
        })
        .then(([tugas, items]) => {
            this.tugas = tugas;
            this.items = Array.isArray(items) ? items : [];
        })
        .catch((error) => {
            this.error = error.message || 'Gagal memuat data pengerjaan.';
        })
        .finally(() => {
            this.loading = false;
        });
    },
    openPenilaian(item) {
        this.openItem = item;
        this.form.nilai = item.nilai === null || item.nilai === '' ? '' : Math.round(Number(item.nilai));
        this.form.catatan = item.catatan || '';
    },
    closePenilaian() {
        this.openItem = null;
    },
    saveNilai() {
        if (!this.openItem) {
            return;
        }

        this.saving = true;
        this.error = '';

        fetch(`${this.pengerjaanUrl}/${this.openItem.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify({
                nilai: this.form.nilai === '' ? null : this.form.nilai,
                catatan: this.form.catatan,
                tanggal_nilai: this.form.nilai === '' ? null : new Date().toISOString()
            })
        })
        .then((response) => response.json().then((data) => ({ response, data })))
        .then(({ response, data }) => {
            if (!response.ok) {
                throw new Error(data?.message || 'Gagal menyimpan penilaian.');
            }

            const id = Number(this.openItem.id);
            this.items = this.items.map((item) => Number(item.id) === id ? data : item);
            this.openItem = null;
        })
        .catch((error) => {
            this.error = error.message || 'Gagal menyimpan penilaian.';
        })
        .finally(() => {
            this.saving = false;
        });
    }
}">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <a class="btn btn-outline-secondary elvd-text-button" :href="backUrl">
                &larr; <?php echo esc_html__('Kembali ke Daftar Tugas', 'elearning-vd'); ?>
            </a>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="alert alert-warning" x-show="!isManager && !loading && !error">
            <?php echo esc_html__('Halaman ini khusus Guru/Admin untuk melihat pengerjaan tugas.', 'elearning-vd'); ?>
        </div>

        <template x-if="loading">
            <div class="p-4 text-muted"><?php echo esc_html__('Memuat data...', 'elearning-vd'); ?></div>
        </template>

        <template x-if="!loading && isManager && tugas">
            <div class="p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-1" x-text="titleOf(tugas)"></h2>
                        <div class="d-flex gap-2">
                            <span class="badge rounded-pill text-bg-secondary" x-text="items.length + ' pengerjaan'"></span>
                        </div>
                    </div>
                </div>

                <template x-if="items.length">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 elvd-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Siswa', 'elearning-vd'); ?></th>
                                    <th><?php echo esc_html__('File', 'elearning-vd'); ?></th>
                                    <th><?php echo esc_html__('Tanggal', 'elearning-vd'); ?></th>
                                    <th><?php echo esc_html__('Nilai', 'elearning-vd'); ?></th>
                                    <th class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in items" :key="item.id">
                                    <tr>
                                        <td class="fw-semibold" x-text="item.user_name || item.user_id"></td>
                                        <td>
                                            <a
                                                x-show="item.file"
                                                :href="item.file"
                                                target="_blank"
                                                rel="noopener"
                                                x-text="fileName(item.file)"></a>
                                            <span x-show="!item.file" class="elvd-subtext">–</span>
                                        </td>
                                        <td class="elvd-subtext" x-text="formatTanggal(item.tanggal)"></td>
                                        <td>
                                            <span class="badge" :class="item.nilai !== null && item.nilai !== '' ? 'text-bg-success' : 'text-bg-light'" x-text="formatNilai(item)"></span>
                                        </td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary elvd-row-action"
                                                @click="openPenilaian(item)"><?php echo esc_html__('Penilaian', 'elearning-vd'); ?></button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <div class="text-muted p-4" x-show="!items.length">
                    <?php echo esc_html__('Belum ada pengerjaan untuk tugas ini.', 'elearning-vd'); ?>
                </div>
            </div>
        </template>

        <div x-show="openItem">
            <div class="modal-backdrop fade show" @click="closePenilaian()"></div>
            <div class="modal fade show elvd-modal" tabindex="-1" role="dialog" aria-modal="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" @submit.prevent="saveNilai()">
                        <div class="modal-header">
                            <h2 class="modal-title"><?php echo esc_html__('Penilaian Pengerjaan', 'elearning-vd'); ?></h2>
                            <button type="button" class="btn-close" aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>" @click="closePenilaian()"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <span class="fw-semibold" x-text="openItem ? (openItem.user_name || openItem.user_id) : ''"></span>
                            </div>

                            <div class="mb-3">
                                <span class="form-label d-block"><?php echo esc_html__('File Pengerjaan', 'elearning-vd'); ?></span>
                                <a
                                    x-show="openItem && openItem.file"
                                    :href="openItem ? openItem.file : '#'"
                                    target="_blank"
                                    rel="noopener"
                                    x-text="openItem ? fileName(openItem.file) : ''"></a>
                                <em class="text-muted" x-show="openItem && !openItem.file"><?php echo esc_html__('Tidak ada file.', 'elearning-vd'); ?></em>
                            </div>

                            <div class="mb-3" x-show="openItem && openItem.catatan">
                                <strong><?php echo esc_html__('Catatan Siswa', 'elearning-vd'); ?></strong>
                                <p class="mb-0 mt-1 text-muted" x-text="openItem ? openItem.catatan : ''"></p>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="elvd-ta-nilai"><?php echo esc_html__('Nilai (0-100)', 'elearning-vd'); ?></label>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="elvd-ta-nilai"
                                    x-model.number="form.nilai"
                                    min="0"
                                    max="100"
                                    placeholder="0">
                            </div>

                            <div>
                                <label class="form-label" for="elvd-ta-catatan"><?php echo esc_html__('Catatan Penilaian', 'elearning-vd'); ?></label>
                                <textarea class="form-control" id="elvd-ta-catatan" x-model="form.catatan" rows="3" placeholder="<?php echo esc_attr__('Catatan untuk siswa.', 'elearning-vd'); ?>"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" @click="closePenilaian()"><?php echo esc_html__('Batal', 'elearning-vd'); ?></button>
                            <button type="submit" class="btn btn-primary elvd-action-button" :disabled="saving">
                                <span x-show="!saving"><?php echo esc_html__('Simpan Nilai', 'elearning-vd'); ?></span>
                                <span x-show="saving"><?php echo esc_html__('Menyimpan...', 'elearning-vd'); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>