<?php

defined('ABSPATH') || exit;

$elvd_quiz_id = absint((int) get_query_var('elvd_quiz_id', 0));
$elvd_quiz_base_url = untrailingslashit(ELVD::app_route()) . '/quiz-form';
$elvd_quiz_back_url = untrailingslashit(ELVD::app_route()) . '/quiz/';
?>

<div x-show="active === 'quiz-form'" x-data="{
    restUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url('wp/v2/elvd_quiz')))); ?>,
    questionRestUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url('wp/v2/elvd_quiz_question')))); ?>,
    baseUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_base_url)); ?>,
    backUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_back_url)); ?>,
    quizId: <?php echo esc_attr((string) $elvd_quiz_id); ?>,
    view: 'form',
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
    questions: [],
    loadingQuestions: false,
    savingQuestion: false,
    questionError: '',
    editingQuestion: null,
    openJawabanIndex: -1,
    init() {
        this.fetchRelations();
        if (this.quizId) {
            this.loadQuiz();
            this.fetchQuestions();
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

            if (!this.quizId) {
                this.quizId = Number(data.id);
                this.view = 'questions';
                this.$nextTick(() => this.fetchQuestions());
            }

            this.saved = true;
        })
        .catch((error) => {
            this.error = error.message || 'Gagal menyimpan quiz.';
        })
        .finally(() => {
            this.saving = false;
        });
    },
    setView(next) {
        if (next === 'questions' && !this.quizId) {
            return;
        }

        this.view = next;
    },
    blankQuestion() {
        return {
            id: null,
            pertanyaan: '',
            tipe: this.form.tipe || 'pilihan_ganda',
            poin: 1,
            opsi: [
                { id: 'new-0', text: '' },
                { id: 'new-1', text: '' }
            ],
            jawaban_benar: ''
        };
    },
    openCreateQuestion() {
        this.editingQuestion = this.blankQuestion();
        this.openJawabanIndex = -1;
        this.questionError = '';
    },
    openEditQuestion(item) {
        let parsed = [];

        try {
            parsed = JSON.parse(this.metaValue(item, 'elvd_opsi') || '[]');
        } catch (err) {
            parsed = [];
        }

        if (!Array.isArray(parsed)) {
            parsed = [];
        }

        const opsi = parsed.map((text, i) => ({ id: `opsi-${i}`, text: String(text ?? '') }));

        this.editingQuestion = {
            id: Number(item.id),
            pertanyaan: this.titleOf(item),
            tipe: this.form.tipe || 'pilihan_ganda',
            poin: Number(this.metaValue(item, 'elvd_poin')) || 1,
            opsi: opsi.length ? opsi : [{ id: 'new-0', text: '' }, { id: 'new-1', text: '' }],
            jawaban_benar: this.metaValue(item, 'elvd_jawaban_benar')
        };

        this.openJawabanIndex = this.editingQuestion.opsi.findIndex((opsiItem, i) => this.editingQuestion.jawaban_benar === String(i));
        this.questionError = '';
    },
    closeQuestion() {
        this.editingQuestion = null;
        this.questionError = '';
    },
    addOpsi() {
        this.editingQuestion.opsi.push({ id: `opsi-${Date.now()}`, text: '' });
    },
    removeOpsi(index) {
        if (this.editingQuestion.jawaban_benar === String(index)) {
            this.editingQuestion.jawaban_benar = '';
            this.openJawabanIndex = -1;
        }

        this.editingQuestion.opsi.splice(index, 1);
    },
    selectJawaban(index) {
        this.editingQuestion.jawaban_benar = String(index);
        this.openJawabanIndex = index;
    },
    saveQuestion() {
        if (!this.quizId) {
            this.questionError = 'Simpan quiz dahulu sebelum menambah pertanyaan.';
            this.view = 'form';
            return;
        }

        if (this.editingQuestion.tipe === 'pilihan_ganda') {
            const filled = this.editingQuestion.opsi.filter((opsi) => (opsi.text || '').trim() !== '');

            if (filled.length < 2) {
                this.questionError = 'Pilihan ganda minimal memiliki 2 opsi.';
                return;
            }

            if (this.editingQuestion.jawaban_benar === '') {
                this.questionError = 'Pilih jawaban yang benar.';
                return;
            }
        }

        this.savingQuestion = true;
        this.questionError = '';

        const isEdit = Boolean(this.editingQuestion.id);
        const url = isEdit ? `${this.questionRestUrl}/${this.editingQuestion.id}` : this.questionRestUrl;

        const payload = {
            title: this.editingQuestion.pertanyaan,
            content: '',
            status: 'publish',
            meta: {
                elvd_quiz_id: Number(this.quizId),
                elvd_pertanyaan_tipe: this.editingQuestion.tipe,
                elvd_poin: Number(this.editingQuestion.poin) || 0,
                elvd_opsi: this.editingQuestion.tipe === 'pilihan_ganda'
                    ? JSON.stringify(this.editingQuestion.opsi.map((o) => o.text))
                    : '',
                elvd_jawaban_benar: this.editingQuestion.jawaban_benar
            }
        };

        fetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify(payload)
        })
        .then((response) => response.json().then((data) => ({ response, data })))
        .then(({ response, data }) => {
            if (!response.ok) {
                throw new Error(data?.message || 'Gagal menyimpan pertanyaan.');
            }

            this.fetchQuestions();
            this.closeQuestion();
        })
        .catch((error) => {
            this.questionError = error.message || 'Gagal menyimpan pertanyaan.';
        })
        .finally(() => {
            this.savingQuestion = false;
        });
    },
    deleteQuestion(item) {
        if (!window.confirm('Hapus pertanyaan ini?')) {
            return;
        }

        fetch(`${this.questionRestUrl}/${item.id}`, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': config.nonce }
        })
        .then((response) => response.json().then((data) => ({ response, data })))
        .then(({ response }) => {
            if (!response.ok) {
                throw new Error('Gagal menghapus pertanyaan.');
            }

            this.fetchQuestions();
        })
        .catch((error) => {
            this.questionError = error.message || 'Gagal menghapus pertanyaan.';
        });
    },
    fetchQuestions() {
        if (!this.quizId) {
            return;
        }

        this.loadingQuestions = true;

        fetch(`${this.questionRestUrl}?per_page=100`, { headers: { 'X-WP-Nonce': config.nonce } })
        .then((response) => response.json())
        .then((data) => {
            const all = Array.isArray(data) ? data : [];

            this.questions = all.filter((item) => Number(this.metaValue(item, 'elvd_quiz_id')) === Number(this.quizId));
        })
        .catch(() => {
            this.questions = [];
        })
        .finally(() => {
            this.loadingQuestions = false;
        });
    },
    previewOpsi(item) {
        let parsed = [];

        try {
            parsed = JSON.parse(this.metaValue(item, 'elvd_opsi') || '[]');
        } catch (err) {
            parsed = [];
        }

        if (!Array.isArray(parsed) || parsed.length === 0) {
            return '-';
        }

        return parsed.map((text, i) => `${['A', 'B', 'C', 'D', 'E', 'F'][i] || (i + 1)}. ${text}`).join(' | ');
    }
}">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <a class="btn btn-outline-secondary elvd-text-button" :href="backUrl">
                &larr; <?php echo esc_html__('Kembali ke Daftar Quiz', 'elearning-vd'); ?>
            </a>
        </div>

        <nav class="nav nav-tabs mb-3 px-3 pt-3" role="tablist">
            <button
                type="button"
                class="nav-link"
                :class="{ active: view === 'form' }"
                role="tab"
                @click="setView('form')">
                <?php echo esc_html__('Form Quiz', 'elearning-vd'); ?>
            </button>
            <button
                type="button"
                class="nav-link"
                :class="{ active: view === 'questions', disabled: !quizId }"
                role="tab"
                @click="setView('questions')">
                <?php echo esc_html__('Pertanyaan', 'elearning-vd'); ?>
                <span class="badge text-bg-secondary ms-1" x-text="quizId ? questions.length : 0"></span>
            </button>
        </nav>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div x-show="view === 'form'">
            <form class="p-4" @submit.prevent="submitForm()">
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
                    <label class="form-label" for="elvd-quiz-pertanyaan"><?php echo esc_html__('Petunjuk Quiz', 'elearning-vd'); ?></label>
                    <textarea
                        class="form-control"
                        id="elvd-quiz-pertanyaan"
                        x-model="form.pertanyaan"
                        rows="4"
                        placeholder="<?php echo esc_attr__('Opsional. Detail pertanyaan diatur pada tab Pertanyaan.', 'elearning-vd'); ?>"></textarea>
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

        <div x-show="view === 'questions'" x-cloak>
            <div class="alert alert-success" x-show="saved" x-cloak>
                <?php echo esc_html__('Quiz berhasil disimpan.', 'elearning-vd'); ?>
            </div>

            <div class="d-flex justify-content-between align-items-center p-4 pb-0">
                <h2 class="h4 mb-0"><?php echo esc_html__('Daftar Pertanyaan', 'elearning-vd'); ?></h2>
                <button
                    type="button"
                    class="btn btn-primary elvd-action-button"
                    x-show="config.isManager"
                    @click="openCreateQuestion()">
                    <?php echo esc_html__('Tambah Pertanyaan', 'elearning-vd'); ?>
                </button>
            </div>

            <div class="px-4 py-3">
                <div class="alert alert-danger" x-show="questionError" x-text="questionError"></div>

                <div x-show="loadingQuestions">
                    <?php echo esc_html__('Memuat pertanyaan...', 'elearning-vd'); ?>
                </div>

                <div x-show="!loadingQuestions && questions.length === 0" class="text-muted">
                    <?php echo esc_html__('Belum ada pertanyaan. Tambahkan pertanyaan pertama.', 'elearning-vd'); ?>
                </div>

                <div class="list-group mb-3" x-show="questions.length > 0">
                    <template x-for="(item, index) in questions" :key="item.id">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill text-bg-light border">No. <span x-text="index + 1"></span></span>
                                </div>
                                <div class="fw-semibold mt-1" x-text="titleOf(item) || '-'"></div>
                                <div class="elvd-subtext" x-text="previewOpsi(item)"></div>
                            </div>
                            <div class="ms-3 d-flex gap-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary elvd-row-action"
                                    x-show="config.isManager"
                                    @click="openEditQuestion(item)">
                                    <?php echo esc_html__('Edit', 'elearning-vd'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger elvd-row-action"
                                    x-show="config.isManager"
                                    @click="deleteQuestion(item)">
                                    <?php echo esc_html__('Hapus', 'elearning-vd'); ?>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <template x-if="editingQuestion">
        <div>
            <div class="modal-backdrop fade show" @click="closeQuestion()"></div>
            <div class="modal fade show elvd-modal" tabindex="-1" role="dialog" aria-modal="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <form class="modal-content" @submit.prevent="saveQuestion()">
                        <div class="modal-header">
                            <h2 class="modal-title" x-text="editingQuestion.id ? 'Edit Pertanyaan' : 'Tambah Pertanyaan'"></h2>
                            <button type="button" class="btn-close" aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>" @click="closeQuestion()"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger" x-show="questionError" x-text="questionError"></div>

                            <div class="mb-3">
                                <label class="form-label" for="elvd-q-teks"><?php echo esc_html__('Teks Pertanyaan', 'elearning-vd'); ?></label>
                                <textarea
                                    class="form-control"
                                    id="elvd-q-teks"
                                    x-model="editingQuestion.pertanyaan"
                                    rows="3"
                                    required
                                    placeholder="<?php echo esc_attr__('Tulis pertanyaan di sini.', 'elearning-vd'); ?>"></textarea>
                            </div>

                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="text-muted small">
                                    <?php echo esc_html__('Tipe mengikuti pengaturan Form Quiz.', 'elearning-vd'); ?>
                                </span>
                            </div>

                            <div class="form-label"><?php echo esc_html__('Poin', 'elearning-vd'); ?></div>
                            <input
                                type="number"
                                class="form-control"
                                id="elvd-q-poin"
                                x-model.number="editingQuestion.poin"
                                min="0"
                                required>
                            <div class="mt-2"></div>

                            <div class="mt-3" x-show="editingQuestion.tipe === 'pilihan_ganda'">
                                <label class="form-label"><?php echo esc_html__('Opsi Jawaban', 'elearning-vd'); ?></label>
                                <template x-for="(opsi, index) in editingQuestion.opsi" :key="opsi.id">
                                    <div class="input-group mb-2">
                                        <span class="input-group-text" x-text="['A','B','C','D','E','F'][index] || (index + 1)"></span>
                                        <input
                                            type="text"
                                            class="form-control"
                                            x-model="opsi.text"
                                            :placeholder="'Opsi ' + (['A','B','C','D','E','F'][index] || (index + 1))">
                                        <button
                                            type="button"
                                            class="btn btn-outline-success"
                                            :class="{ 'active': openJawabanIndex === index }"
                                            @click="selectJawaban(index)"
                                            x-show="(opsi.text || '').trim() !== ''"
                                            :aria-label="'Tandai opsi ' + (['A','B','C','D','E','F'][index] || (index + 1)) + ' sebagai jawaban benar'">
                                            <?php echo esc_html__('Jawaban', 'elearning-vd'); ?>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-outline-danger"
                                            @click="removeOpsi(index)"
                                            x-show="editingQuestion.opsi.length > 2"
                                            aria-label="<?php echo esc_attr__('Hapus opsi', 'elearning-vd'); ?>">
                                            &times;
                                        </button>
                                    </div>
                                </template>
                                <button type="button" class="btn btn-sm btn-outline-secondary elvd-text-button" @click="addOpsi()">
                                    + <?php echo esc_html__('Tambah Opsi', 'elearning-vd'); ?>
                                </button>
                                <div class="form-text">
                                    <?php echo esc_html__('Klik tombol Jawaban untuk menandai jawaban yang benar.', 'elearning-vd'); ?>
                                </div>
                            </div>

                            <div class="mt-3" x-show="editingQuestion.tipe === 'essay'">
                                <div class="alert alert-info mb-0">
                                    <?php echo esc_html__('Jawaban essay dinilai manual oleh guru setelah siswa mengumpulkan.', 'elearning-vd'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" @click="closeQuestion()">
                                <?php echo esc_html__('Batal', 'elearning-vd'); ?>
                            </button>
                            <button type="submit" class="btn btn-primary elvd-action-button" :disabled="savingQuestion">
                                <span x-show="!savingQuestion"><?php echo esc_html__('Simpan Pertanyaan', 'elearning-vd'); ?></span>
                                <span x-show="savingQuestion"><?php echo esc_html__('Menyimpan...', 'elearning-vd'); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>