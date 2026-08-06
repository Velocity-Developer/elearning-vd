<?php

defined('ABSPATH') || exit;

$elvd_quiz_id = absint((int) get_query_var('elvd_quiz_id', 0));
$elvd_quiz_base_url = untrailingslashit(ELVD::app_route()) . '/quiz-form';
$elvd_quiz_back_url = untrailingslashit(ELVD::app_route()) . '/quiz/';
?>

<div x-show="active === 'quiz-form'" x-data="{
    restUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url('wp/v2/elvd_quiz')))); ?>,
    baseUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_base_url)); ?>,
    backUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_back_url)); ?>,
    quizId: <?php echo esc_attr((string) $elvd_quiz_id); ?>,
    classes: [],
    subjects: [],
    loading: false,
    loadingRelations: false,
    saving: false,
    saved: false,
    error: '',
    form: {
        judul: '',
        tipe: 'pilihan_ganda',
        durasi_menit: 30,
        kelas_id: '',
        mata_pelajaran_id: '',
        pertanyaan: ''
    },
    init() {
        this.fetchRelations();
        if (this.quizId) {
            this.loadQuiz();
        }
    },
    metaValue(item, key) {
        return item.meta && item.meta[key] ? item.meta[key] : '';
    },
    titleOf(item) {
        return (item.title && (item.title.rendered || item.title.raw)) ? (item.title.rendered || item.title.raw) : '';
    },
    contentOf(item) {
        if (item.content && item.content.raw) {
            return item.content.raw;
        }

        if (item.content && item.content.rendered) {
            const div = document.createElement('div');
            div.innerHTML = item.content.rendered;
            return div.textContent || div.innerText || '';
        }

        return '';
    },
    fetchRelations() {
        this.loadingRelations = true;

        Promise.all([
            fetch(`${config.restUrl}/kelas?per_page=100`, { headers: { 'X-WP-Nonce': config.nonce } }),
            fetch(`${config.restUrl}/mata-pelajaran?per_page=100`, { headers: { 'X-WP-Nonce': config.nonce } })
        ])
        .then((responses) => {
            responses.forEach((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat data pilihan quiz.');
                }
            });

            return Promise.all(responses.map((response) => response.json()));
        })
        .then(([classes, subjects]) => {
            this.classes = Array.isArray(classes) ? classes : [];
            this.subjects = Array.isArray(subjects) ? subjects : [];
        })
        .catch((error) => {
            this.error = error.message || 'Gagal memuat data pilihan quiz.';
        })
        .finally(() => {
            this.loadingRelations = false;
        });
    },
    loadQuiz() {
        this.loading = true;
        this.error = '';

        fetch(`${this.restUrl}/${this.quizId}`, { headers: { 'X-WP-Nonce': config.nonce } })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Gagal memuat data quiz.');
            }

            return response.json();
        })
        .then((item) => {
            this.form = {
                judul: this.titleOf(item),
                tipe: this.metaValue(item, 'elvd_quiz_tipe') || 'pilihan_ganda',
                durasi_menit: Number(this.metaValue(item, 'elvd_durasi_menit')) || 30,
                kelas_id: this.metaValue(item, 'elvd_kelas_id') ? String(this.metaValue(item, 'elvd_kelas_id')) : '',
                mata_pelajaran_id: this.metaValue(item, 'elvd_mata_pelajaran_id') ? String(this.metaValue(item, 'elvd_mata_pelajaran_id')) : '',
                pertanyaan: this.contentOf(item)
            };
        })
        .catch((error) => {
            this.error = error.message || 'Gagal memuat data quiz.';
        })
        .finally(() => {
            this.loading = false;
        });
    },
    submitForm() {
        this.saving = true;
        this.error = '';
        this.saved = false;

        const url = this.quizId ? `${this.restUrl}/${this.quizId}` : this.restUrl;

        const payload = {
            title: this.form.judul,
            content: this.form.pertanyaan,
            status: 'publish',
            meta: {
                elvd_quiz_tipe: this.form.tipe,
                elvd_durasi_menit: Number(this.form.durasi_menit) || 0,
                elvd_kelas_id: this.form.kelas_id ? Number(this.form.kelas_id) : 0,
                elvd_mata_pelajaran_id: this.form.mata_pelajaran_id ? Number(this.form.mata_pelajaran_id) : 0
            }
        };

        fetch(url, {
            method: this.quizId ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify(payload)
        })
        .then((response) => response.json().then((data) => ({ response, data })))
        .then(({ response, data }) => {
            if (!response.ok) {
                throw new Error(data?.message || 'Gagal menyimpan quiz.');
            }

            this.saved = true;
        })
        .catch((error) => {
            this.error = error.message || 'Gagal menyimpan quiz.';
        })
        .finally(() => {
            this.saving = false;
        });
    }
}">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <a class="btn btn-outline-secondary elvd-text-button" :href="backUrl">
                &larr; <?php echo esc_html__('Kembali ke Daftar Quiz', 'elearning-vd'); ?>
            </a>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>
        <div class="alert alert-success" x-show="saved" x-cloak>
            <?php echo esc_html__('Quiz berhasil disimpan.', 'elearning-vd'); ?>
        </div>

        <div x-show="loading" x-cloak>
            <?php echo esc_html__('Memuat data quiz...', 'elearning-vd'); ?>
        </div>

        <form
            class="p-4"
            x-show="!loading"
            x-cloak
            @submit.prevent="submitForm()">
            <h2 class="h4 mb-4" x-text="quizId ? 'Edit Quiz' : 'Tambah Quiz'"></h2>

            <div class="mb-3">
                <label class="form-label" for="elvd-quiz-judul"><?php echo esc_html__('Judul Quiz', 'elearning-vd'); ?></label>
                <input
                    type="text"
                    class="form-control"
                    id="elvd-quiz-judul"
                    x-model="form.judul"
                    required
                    maxlength="200"
                    placeholder="<?php echo esc_attr__('Contoh: Quiz Bab 1 - Bilangan', 'elearning-vd'); ?>">
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="elvd-quiz-tipe"><?php echo esc_html__('Tipe Quiz', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-quiz-tipe" x-model="form.tipe" required>
                        <option value="pilihan_ganda"><?php echo esc_html__('Pilihan Ganda', 'elearning-vd'); ?></option>
                        <option value="essay"><?php echo esc_html__('Essay', 'elearning-vd'); ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="elvd-quiz-durasi"><?php echo esc_html__('Durasi (Menit)', 'elearning-vd'); ?></label>
                    <input
                        type="number"
                        class="form-control"
                        id="elvd-quiz-durasi"
                        x-model.number="form.durasi_menit"
                        min="1"
                        max="600"
                        required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="elvd-quiz-mapel"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-quiz-mapel" x-model="form.mata_pelajaran_id" required :disabled="loadingRelations">
                        <option value="" x-text="loadingRelations ? 'Memuat mapel...' : 'Pilih mata pelajaran'"></option>
                        <template x-for="subject in subjects" :key="subject.id">
                            <option :value="String(subject.id)" x-text="subject.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="elvd-quiz-kelas"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-quiz-kelas" x-model="form.kelas_id" required :disabled="loadingRelations">
                        <option value="" x-text="loadingRelations ? 'Memuat kelas...' : 'Pilih kelas'"></option>
                        <template x-for="classItem in classes" :key="classItem.id">
                            <option :value="String(classItem.id)" x-text="classItem.nama"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label" for="elvd-quiz-pertanyaan"><?php echo esc_html__('Pertanyaan Quiz', 'elearning-vd'); ?></label>
                <textarea
                    class="form-control"
                    id="elvd-quiz-pertanyaan"
                    x-model="form.pertanyaan"
                    rows="6"
                    placeholder="<?php echo esc_attr__('Tulis pertanyaan atau petunjuk quiz di sini.', 'elearning-vd'); ?>"></textarea>
                <div class="form-text">
                    <?php echo esc_html__('Pertanyaan detail dikelola terpisah melalui halaman pertanyaan quiz.', 'elearning-vd'); ?>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a class="btn btn-outline-secondary" :href="backUrl">
                    <?php echo esc_html__('Batal', 'elearning-vd'); ?>
                </a>
                <button type="submit" class="btn btn-primary elvd-action-button" :disabled="saving">
                    <span x-show="!saving"><?php echo esc_html__('Simpan', 'elearning-vd'); ?></span>
                    <span x-show="saving"><?php echo esc_html__('Menyimpan...', 'elearning-vd'); ?></span>
                </button>
            </div>
        </form>
    </div>
</div>