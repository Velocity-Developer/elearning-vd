<?php

defined('ABSPATH') || exit;

$elvd_qa_quiz_id = absint((int) get_query_var('elvd_quiz_id', 0));
$elvd_qa_back_url = untrailingslashit(ELVD::app_route()) . '/quiz/';
$elvd_qa_rest_quiz = untrailingslashit(rest_url('wp/v2/elvd_quiz'));
$elvd_qa_rest_question = untrailingslashit(rest_url('wp/v2/elvd_quiz_question'));
$elvd_qa_rest_pengerjaan = untrailingslashit(rest_url(ELVD_REST_NAMESPACE . '/pengerjaan-quiz'));
?>

<div x-show="active === 'quiz-answer'" x-data="{
    restQuizUrl: <?php echo esc_attr(wp_json_encode($elvd_qa_rest_quiz)); ?>,
    restQuestionUrl: <?php echo esc_attr(wp_json_encode($elvd_qa_rest_question)); ?>,
    pengerjaanUrl: <?php echo esc_attr(wp_json_encode($elvd_qa_rest_pengerjaan)); ?>,
    backUrl: <?php echo esc_attr(wp_json_encode($elvd_qa_back_url)); ?>,
    quizId: <?php echo esc_attr((string) $elvd_qa_quiz_id); ?>,
    isManager: config.isManager,
    loading: true,
    error: '',
    quiz: null,
    questions: [],
    attempts: [],
    openAttempt: null,
    form: {
        nilai: '',
        status: 'selesai'
    },
    saving: false,
    init() {
        if (!this.quizId) {
            this.loading = false;
            this.error = 'Quiz tidak ditemukan.';
            return;
        }

        this.loadData();
    },
    metaValue(item, key) {
        return item.meta && item.meta[key] ? item.meta[key] : '';
    },
    titleOf(item) {
        return (item.title && (item.title.rendered || item.title.raw)) ? (item.title.rendered || item.title.raw) : '';
    },
    quizTipe() {
        return this.quiz ? (this.metaValue(this.quiz, 'elvd_quiz_tipe') || 'pilihan_ganda') : 'pilihan_ganda';
    },
    questionTipe(item) {
        return this.metaValue(item, 'elvd_pertanyaan_tipe') || this.quizTipe();
    },
    essayQuestions() {
        return this.questions.filter((item) => this.questionTipe(item) === 'essay');
    },
    loadData() {
        this.loading = true;
        this.error = '';

        const pengerjaanParams = new URLSearchParams({ per_page: '100', quiz_id: String(this.quizId) });

        if (!this.isManager) {
            pengerjaanParams.set('siswa_id', String(config.userId));
        }

        Promise.all([
            fetch(`${this.restQuizUrl}/${this.quizId}`, { headers: { 'X-WP-Nonce': config.nonce } }),
            fetch(`${this.restQuestionUrl}?per_page=100`, { headers: { 'X-WP-Nonce': config.nonce } }),
            fetch(`${this.pengerjaanUrl}?${pengerjaanParams.toString()}`, { headers: { 'X-WP-Nonce': config.nonce } })
        ])
        .then((responses) => {
            responses.forEach((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat data pengerjaan.');
                }
            });

            return Promise.all(responses.map((response) => response.json()));
        })
        .then(([quiz, allQuestions, attempts]) => {
            this.quiz = quiz;
            this.questions = (Array.isArray(allQuestions) ? allQuestions : [])
                .filter((item) => Number(this.metaValue(item, 'elvd_quiz_id')) === Number(this.quizId))
                .sort((a, b) => Number(a.id) - Number(b.id));
            this.attempts = Array.isArray(attempts) ? attempts : [];
        })
        .catch((error) => {
            this.error = error.message || 'Gagal memuat data pengerjaan.';
        })
        .finally(() => {
            this.loading = false;
        });
    },
    jawabanOf(item) {
        let parsed = {};

        try {
            parsed = JSON.parse(item.jawaban || '{}');
        } catch (err) {
            parsed = {};
        }

        return parsed && typeof parsed === 'object' ? parsed : {};
    },
    answerFor(attempt, questionId) {
        const map = this.jawabanOf(attempt);

        return (map[questionId] ?? '');
    },
    formatNilai(item) {
        const nilai = item.nilai;

        return nilai === null || nilai === '' ? '–' : `${Math.round(Number(nilai))}`;
    },
    formatTanggal(value) {
        return value ? String(value).replace('T', ' ').slice(0, 16) : '–';
    },
    openKoreksi(item) {
        if (!this.isManager || this.quizTipe() !== 'essay') {
            return;
        }

        this.openAttempt = item;
        this.form.nilai = item.nilai === null || item.nilai === '' ? '' : Math.round(Number(item.nilai));
        this.form.status = item.status || 'selesai';
    },
    closeKoreksi() {
        this.openAttempt = null;
    },
    saveNilai() {
        if (!this.openAttempt) {
            return;
        }

        this.saving = true;
        this.error = '';

        fetch(`${this.pengerjaanUrl}/${this.openAttempt.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify({
                nilai: this.form.nilai === '' ? null : this.form.nilai,
                status: this.form.status
            })
        })
        .then((response) => response.json().then((data) => ({ response, data })))
        .then(({ response, data }) => {
            if (!response.ok) {
                throw new Error(data?.message || 'Gagal menyimpan nilai.');
            }

            const id = Number(this.openAttempt.id);
            this.attempts = this.attempts.map((attempt) => Number(attempt.id) === id ? data : attempt);
            this.openAttempt = null;
        })
        .catch((error) => {
            this.error = error.message || 'Gagal menyimpan nilai.';
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

        <template x-if="!loading && !isManager && quiz">
            <div class="p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-1" x-text="titleOf(quiz)"></h2>
                        <div class="d-flex gap-2">
                            <span class="badge rounded-pill text-bg-primary" x-text="quizTipe() === 'essay' ? 'Essay' : 'Pilihan Ganda'"></span>
                            <span class="badge rounded-pill text-bg-secondary" x-text="attempts.length + ' pengerjaan'"></span>
                        </div>
                    </div>
                </div>

                <template x-if="attempts.length">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 elvd-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Mulai', 'elearning-vd'); ?></th>
                                    <th><?php echo esc_html__('Selesai', 'elearning-vd'); ?></th>
                                    <th><?php echo esc_html__('Nilai', 'elearning-vd'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in attempts" :key="item.id">
                                    <tr>
                                        <td class="elvd-subtext" x-text="formatTanggal(item.mulai_pada)"></td>
                                        <td class="elvd-subtext" x-text="formatTanggal(item.selesai_pada)"></td>
                                        <td>
                                            <span class="badge" :class="item.nilai !== null && item.nilai !== '' ? 'text-bg-success' : 'text-bg-light'" x-text="formatNilai(item)"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <div class="text-muted p-4" x-show="!attempts.length">
                    <?php echo esc_html__('Anda belum mengerjakan quiz ini.', 'elearning-vd'); ?>
                </div>
            </div>
        </template>

        <template x-if="loading">
            <div class="p-4 text-muted"><?php echo esc_html__('Memuat data...', 'elearning-vd'); ?></div>
        </template>

        <template x-if="!loading && isManager && quiz">
            <div class="p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-1" x-text="titleOf(quiz)"></h2>
                        <div class="d-flex gap-2">
                            <span class="badge rounded-pill text-bg-primary" x-text="quizTipe() === 'essay' ? 'Essay' : 'Pilihan Ganda'"></span>
                            <span class="badge rounded-pill text-bg-secondary" x-text="attempts.length + ' pengerjaan'"></span>
                        </div>
                    </div>
                </div>

                <template x-if="attempts.length">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 elvd-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Siswa', 'elearning-vd'); ?></th>
                                    <th><?php echo esc_html__('Mulai', 'elearning-vd'); ?></th>
                                    <th><?php echo esc_html__('Selesai', 'elearning-vd'); ?></th>
                                    <th><?php echo esc_html__('Nilai', 'elearning-vd'); ?></th>
                                    <th class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in attempts" :key="item.id">
                                    <tr>
                                        <td class="fw-semibold" x-text="item.siswa_name || item.siswa_id"></td>
                                        <td class="elvd-subtext" x-text="formatTanggal(item.mulai_pada)"></td>
                                        <td class="elvd-subtext" x-text="formatTanggal(item.selesai_pada)"></td>
                                        <td>
                                            <span class="badge" :class="item.nilai !== null && item.nilai !== '' ? 'text-bg-success' : 'text-bg-light'" x-text="formatNilai(item)"></span>
                                        </td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary elvd-row-action"
                                                x-show="quizTipe() === 'essay'"
                                                @click="openKoreksi(item)"><?php echo esc_html__('Koreksi', 'elearning-vd'); ?></button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <div class="text-muted p-4" x-show="!attempts.length">
                    <?php echo esc_html__('Belum ada pengerjaan untuk quiz ini.', 'elearning-vd'); ?>
                </div>
            </div>
        </template>

        <div x-show="openAttempt">
            <div class="modal-backdrop fade show" @click="closeKoreksi()"></div>
            <div class="modal fade show elvd-modal" tabindex="-1" role="dialog" aria-modal="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <form class="modal-content" @submit.prevent="saveNilai()">
                        <div class="modal-header">
                            <h2 class="modal-title"><?php echo esc_html__('Koreksi Jawaban', 'elearning-vd'); ?></h2>
                            <button type="button" class="btn-close" aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>" @click="closeKoreksi()"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <span class="fw-semibold" x-text="openAttempt ? (openAttempt.siswa_name || openAttempt.siswa_id) : ''"></span>
                            </div>

                            <template x-for="(item, index) in essayQuestions()" :key="item.id">
                                <div class="border rounded-3 p-3 mb-3">
                                    <div class="d-flex gap-2 mb-2">
                                        <span class="badge rounded-pill text-bg-light border">No. <span x-text="index + 1"></span></span>
                                        <span class="badge rounded-pill text-bg-light border">Essay</span>
                                    </div>
                                    <div class="fw-semibold mb-2" x-text="titleOf(item) || '-'"></div>
                                    <div class="elvd-subtext"><?php echo esc_html__('Jawaban siswa:', 'elearning-vd'); ?></div>
                                    <p class="mb-0">
                                        <span x-text="answerFor(openAttempt, item.id) || '–'" x-show="answerFor(openAttempt, item.id)"></span>
                                        <em class="text-muted" x-show="!answerFor(openAttempt, item.id)"><?php echo esc_html__('Tidak menjawab.', 'elearning-vd'); ?></em>
                                    </p>
                                </div>
                            </template>

                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <label class="form-label" for="elvd-answer-nilai"><?php echo esc_html__('Nilai (0-100)', 'elearning-vd'); ?></label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="elvd-answer-nilai"
                                        x-model.number="form.nilai"
                                        min="0"
                                        max="100"
                                        placeholder="0">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="elvd-answer-status"><?php echo esc_html__('Status', 'elearning-vd'); ?></label>
                                    <select class="form-select" id="elvd-answer-status" x-model="form.status">
                                        <option value="selesai"><?php echo esc_html__('Selesai', 'elearning-vd'); ?></option>
                                        <option value="dikoreksi"><?php echo esc_html__('Dikoreksi', 'elearning-vd'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" @click="closeKoreksi()"><?php echo esc_html__('Batal', 'elearning-vd'); ?></button>
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