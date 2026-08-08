<?php

defined('ABSPATH') || exit;

$elvd_ws_quiz_id = absint((int) get_query_var('elvd_quiz_id', 0));
$elvd_ws_back_url = untrailingslashit(ELVD::app_route()) . '/quiz/';
$elvd_ws_rest_quiz = untrailingslashit(rest_url('wp/v2/elvd_quiz'));
$elvd_ws_rest_question = untrailingslashit(rest_url('wp/v2/elvd_quiz_question'));
$elvd_ws_rest_pengerjaan = untrailingslashit(rest_url(ELVD_REST_NAMESPACE . '/pengerjaan-quiz'));
$elvd_ws_is_preview = elvd_can_manage_rest();
$elvd_ws_result_url = untrailingslashit(ELVD::app_route()) . '/quiz-answer';
?>

<div x-show="active === 'quiz-workspace'" x-data="{
    restUrl: <?php echo esc_attr(wp_json_encode($elvd_ws_rest_quiz)); ?>,
    questionRestUrl: <?php echo esc_attr(wp_json_encode($elvd_ws_rest_question)); ?>,
    pengerjaanUrl: <?php echo esc_attr(wp_json_encode($elvd_ws_rest_pengerjaan)); ?>,
    resultUrl: <?php echo esc_attr(wp_json_encode($elvd_ws_result_url)); ?>,
    backUrl: <?php echo esc_attr(wp_json_encode($elvd_ws_back_url)); ?>,
    isPreview: <?php echo esc_attr($elvd_ws_is_preview ? 'true' : 'false'); ?>,
    quizId: <?php echo esc_attr((string) $elvd_ws_quiz_id); ?>,
    view: 'intro',
    loading: true,
    error: '',
    alreadyDone: false,
    quiz: null,
    questions: [],
    answers: {},
    currentIndex: 0,
    durasiMenit: 0,
    remaining: 0,
    startedAt: '',
    timerId: null,
    submitting: false,
    submitted: false,
    adminBarHeight: 0,
    init() {
        const bar = document.getElementById('wpadminbar');
        if (bar) {
            this.adminBarHeight = Math.ceil(bar.getBoundingClientRect().height);
        }

        if (!this.quizId) {
            this.loading = false;
            this.error = 'Quiz tidak ditemukan.';
            return;
        }

        this.loadQuiz();
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
    loadQuiz() {
        this.loading = true;
        this.error = '';
        this.alreadyDone = false;

        const tasks = [
            fetch(`${this.restUrl}/${this.quizId}`, { headers: { 'X-WP-Nonce': config.nonce } }),
            fetch(`${this.questionRestUrl}?per_page=100`, { headers: { 'X-WP-Nonce': config.nonce } })
        ];

        if (!this.isPreview) {
            tasks.push(fetch(`${this.pengerjaanUrl}?per_page=1&quiz_id=${this.quizId}&siswa_id=${config.userId}`, { headers: { 'X-WP-Nonce': config.nonce } }));
        }

        Promise.all(tasks)
        .then((responses) => {
            responses.forEach((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat data quiz.');
                }
            });

            return Promise.all(responses.map((response) => response.json()));
        })
        .then(([quiz, allQuestions, attempts]) => {
            this.quiz = quiz;
            this.durasiMenit = Number(this.metaValue(quiz, 'elvd_durasi_menit')) || 0;
            this.questions = (Array.isArray(allQuestions) ? allQuestions : [])
                .filter((item) => Number(this.metaValue(item, 'elvd_quiz_id')) === Number(this.quizId))
                .sort((a, b) => Number(a.id) - Number(b.id));

            if (!this.isPreview && Array.isArray(attempts) && attempts.length > 0) {
                this.alreadyDone = true;
            }
        })
        .catch((error) => {
            this.error = error.message || 'Gagal memuat data quiz.';
        })
        .finally(() => {
            this.loading = false;
        });
    },
    quizTipe() {
        return this.quiz ? (this.metaValue(this.quiz, 'elvd_quiz_tipe') || 'pilihan_ganda') : 'pilihan_ganda';
    },
    questionTipe(item) {
        return this.metaValue(item, 'elvd_pertanyaan_tipe') || this.quizTipe();
    },
    optionsOf(item) {
        let parsed = [];

        try {
            parsed = JSON.parse(this.metaValue(item, 'elvd_opsi') || '[]');
        } catch (err) {
            parsed = [];
        }

        return Array.isArray(parsed) ? parsed : [];
    },
    answerOf(item) {
        const value = this.answers[item.id];

        return value === undefined || value === null ? '' : String(value);
    },
    answeredCount() {
        return this.questions.filter((item) => this.answerOf(item).trim() !== '').length;
    },
    currentQuestion() {
        return this.questions[this.currentIndex] || null;
    },
    prev() {
        if (this.currentIndex > 0) {
            this.currentIndex -= 1;
        }
    },
    next() {
        if (this.currentIndex < this.questions.length - 1) {
            this.currentIndex += 1;
        }
    },
    goto(index) {
        if (index >= 0 && index < this.questions.length) {
            this.currentIndex = index;
        }
    },
    correctCount() {
        if (this.quizTipe() !== 'pilihan_ganda') {
            return 0;
        }

        return this.questions.filter((item) => {
            const benar = this.metaValue(item, 'elvd_jawaban_benar');

            return benar !== '' && this.answerOf(item) === benar;
        }).length;
    },
    score() {
        if (this.quizTipe() !== 'pilihan_ganda' || !this.questions.length) {
            return 0;
        }

        return Math.round((this.correctCount() / this.questions.length) * 100);
    },
    scoreLabel() {
        const n = this.score();

        if (n >= 80) {
            return 'Luar biasa';
        }

        if (n >= 60) {
            return 'Bagus';
        }

        return 'Perlu lebih banyak latihan';
    },
    start() {
        if (this.alreadyDone) {
            return;
        }

        if (!this.questions.length) {
            this.error = 'Quiz belum memiliki pertanyaan.';
            return;
        }

        this.error = '';
        this.startedAt = new Date().toISOString();
        this.remaining = this.durasiMenit > 0 ? this.durasiMenit * 60 : 0;
        this.view = 'work';

        if (this.remaining > 0) {
            this.timerId = setInterval(() => {
                this.remaining -= 1;

                if (this.remaining <= 0) {
                    this.submit(true);
                }
            }, 1000);
        }
    },
    formatTime(seconds) {
        const total = Math.max(0, seconds);
        const minutes = Math.floor(total / 60);
        const rest = total % 60;

        return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
    },
    submit(autoSubmit = false) {
        if (this.submitting || this.submitted) {
            return;
        }

        if (!autoSubmit && !window.confirm('Kumpulkan jawaban quiz sekarang?')) {
            return;
        }

        if (this.timerId) {
            clearInterval(this.timerId);
            this.timerId = null;
        }

        this.submitting = true;
        this.error = '';

        if (this.isPreview) {
            this.finish();
            return;
        }

        fetch(`${this.pengerjaanUrl}/kerjakan`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify({
                quiz_id: Number(this.quizId),
                jawaban: this.answers,
                mulai_pada: this.startedAt,
                nilai: this.quizTipe() === 'pilihan_ganda' ? this.score() : null
            })
        })
        .then((response) => response.json().then((data) => ({ response, data })))
        .then(({ response, data }) => {
            if (!response.ok) {
                throw new Error(data?.message || 'Gagal menyimpan hasil quiz.');
            }

            this.finish();
        })
        .catch((error) => {
            this.error = error.message || 'Gagal menyimpan hasil quiz.';
        })
        .finally(() => {
            this.submitting = false;
        });
    },
    finish() {
        this.submitted = true;
        this.view = 'done';
    }
}">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <a class="btn btn-outline-secondary elvd-text-button" :href="backUrl">
                &larr; <?php echo esc_html__('Kembali ke Daftar Quiz', 'elearning-vd'); ?>
            </a>
        </div>

        <div class="alert alert-warning" x-show="isPreview" x-cloak>
            <?php echo esc_html__('Mode Preview: Anda login sebagai Guru/Admin. Jawaban tidak akan disimpan.', 'elearning-vd'); ?>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div x-show="loading">
            <div class="p-4 text-muted">
                <?php echo esc_html__('Memuat quiz...', 'elearning-vd'); ?>
            </div>
        </div>

        <template x-if="!loading && quiz">
            <div x-show="view === 'intro'">
                <div class="p-4">
                    <h2 class="h4 mb-2" x-text="titleOf(quiz)"></h2>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge rounded-pill text-bg-primary" x-text="quizTipe() === 'essay' ? 'Essay' : 'Pilihan Ganda'"></span>
                        <span class="badge rounded-pill text-bg-secondary" x-text="questions.length + ' soal'"></span>
                        <span class="badge rounded-pill text-bg-secondary" x-show="durasiMenit > 0" x-text="durasiMenit + ' menit'"></span>
                    </div>

                    <div class="alert alert-light border" x-show="contentOf(quiz)" x-text="contentOf(quiz)"></div>

                    <button
                        type="button"
                        class="btn btn-primary elvd-action-button"
                        x-show="!alreadyDone"
                        @click="start()"
                        x-text="isPreview ? 'Lihat Soal' : 'Mulai Kerjakan'"></button>

                    <div class="alert alert-info" x-show="alreadyDone">
                        <div class="mb-2"><?php echo esc_html__('Anda sudah pernah mengerjakan quiz ini. Silakan lihat hasil pengerjaan Anda.', 'elearning-vd'); ?></div>
                        <a class="btn btn-sm btn-primary elvd-action-button" :href="`${resultUrl}/${quizId}/`">
                            <i class="bi bi-clipboard2-check me-1"></i><?php echo esc_html__('Lihat Hasil', 'elearning-vd'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="!loading && quiz && view === 'work'">
            <div class="position-fixed start-0 w-100 bottom-0 d-flex flex-column bg-white" :style="{ zIndex: 1050, top: adminBarHeight + 'px' }">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 px-4 py-3 border-bottom bg-white">
                    <div class="min-w-0">
                        <h2 class="h5 mb-0" x-text="titleOf(quiz)"></h2>
                        <div class="text-muted small" x-text="`${answeredCount()} / ${questions.length} terjawab`"></div>
                    </div>
                    <span class="badge rounded-pill text-bg-dark fs-6" x-show="remaining > 0" x-text="formatTime(remaining)"></span>
                </div>

                <div class="flex-grow-1 overflow-auto px-3 py-4">
                    <div class="mx-auto" style="max-width: 860px;">
                        <div class="alert alert-danger" x-show="error" x-text="error"></div>

                        <template x-if="currentQuestion()">
                            <div class="list-group-item border rounded-3 p-3 mb-3">
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="badge rounded-pill text-bg-light border">Soal <span x-text="currentIndex + 1"></span> dari <span x-text="questions.length"></span></span>
                                    <span class="badge rounded-pill text-bg-light border" x-text="questionTipe(currentQuestion()) === 'essay' ? 'Essay' : 'PG'"></span>
                                </div>

                                <div class="fw-semibold mb-3" x-text="titleOf(currentQuestion()) || '-'"></div>

                                <div x-show="questionTipe(currentQuestion()) === 'pilihan_ganda'">
                                    <template x-for="(opsi, opsiIndex) in optionsOf(currentQuestion())" :key="opsiIndex">
                                        <div class="form-check mb-2">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                :name="`elvd-jawaban-${currentQuestion().id}`"
                                                :id="`elvd-jawaban-${currentQuestion().id}-${opsiIndex}`"
                                                :value="String(opsiIndex)"
                                                x-model="answers[currentQuestion().id]">
                                            <label class="form-check-label" :for="`elvd-jawaban-${currentQuestion().id}-${opsiIndex}`">
                                                <span x-text="['A','B','C','D','E','F'][opsiIndex] || (opsiIndex + 1)"></span>. <span x-text="opsi"></span>
                                            </label>
                                        </div>
                                    </template>
                                </div>

                                <textarea
                                    class="form-control"
                                    :id="`elvd-essai-${currentQuestion().id}`"
                                    rows="4"
                                    x-model="answers[currentQuestion().id]"
                                    x-show="questionTipe(currentQuestion()) === 'essay'"
                                    placeholder="<?php echo esc_attr__('Tulis jawaban essay di sini...', 'elearning-vd'); ?>"></textarea>
                            </div>
                        </template>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                :disabled="currentIndex === 0"
                                @click="prev()">
                                &larr; <?php echo esc_html__('Sebelumnya', 'elearning-vd'); ?>
                            </button>

                            <div class="d-flex flex-wrap justify-content-center gap-1">
                                <template x-for="(item, index) in questions" :key="item.id">
                                    <button
                                        type="button"
                                        class="btn btn-sm"
                                        :class="index === currentIndex ? 'btn-primary' : (answerOf(item).trim() !== '' ? 'btn-success' : 'btn-outline-secondary')"
                                        @click="goto(index)"
                                        x-text="index + 1"></button>
                                </template>
                            </div>

                            <button
                                type="button"
                                class="btn btn-primary elvd-action-button"
                                :disabled="submitting"
                                @click="currentIndex < questions.length - 1 ? next() : submit()">
                                <span x-show="!submitting" x-text="currentIndex < questions.length - 1 ? 'Berikutnya' : (isPreview ? 'Selesai (Preview)' : 'Kumpulkan Jawaban')"></span>
                                <span x-show="submitting"><?php echo esc_html__('Menyimpan...', 'elearning-vd'); ?></span>
                            </button>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <a class="btn btn-outline-secondary" :href="backUrl">
                                <?php echo esc_html__('Batal', 'elearning-vd'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="view === 'done'">
            <div class="p-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center" x-show="quizTipe() === 'pilihan_ganda'">
                            <h2 class="h4 mb-3" x-text="titleOf(quiz)"></h2>

                            <div
                                class="mx-auto d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10"
                                style="width: 9rem; height: 9rem;">
                                <div>
                                    <div class="text-primary fw-bold" style="font-size: 3rem; line-height: 1;" x-text="score()"></div>
                                    <div class="text-muted small">/ 100</div>
                                </div>
                            </div>

                            <div class="text-muted mt-3" x-text="`${correctCount()} dari ${questions.length} soal benar`"></div>
                            <span class="badge rounded-pill text-bg-primary fs-6 mt-2 px-3 py-2" x-text="scoreLabel()"></span>

                            <div class="alert alert-warning mt-3 mb-0" x-show="isPreview">
                                <?php echo esc_html__('Mode Preview: hasil tidak disimpan.', 'elearning-vd'); ?>
                            </div>

                            <div class="d-flex justify-content-center mt-4">
                                <a class="btn btn-primary elvd-action-button" :href="backUrl">
                                    <?php echo esc_html__('Kembali ke Daftar Quiz', 'elearning-vd'); ?>
                                </a>
                            </div>
                        </div>

                        <div class="text-center py-4" x-show="quizTipe() !== 'pilihan_ganda'">
                            <div class="alert alert-success d-inline-block mb-3">
                                <span x-show="isPreview" x-text="<?php echo esc_attr(wp_json_encode(__('Preview selesai. Hasil tidak disimpan karena Anda login sebagai Guru/Admin.', 'elearning-vd'))); ?>"></span>
                                <span x-show="!isPreview" x-text="<?php echo esc_attr(wp_json_encode(__('Jawaban quiz berhasil dikumpulkan.', 'elearning-vd'))); ?>"></span>
                            </div>
                            <div class="text-muted mb-3"><?php echo esc_html__('Hasil jawaban essay akan dinilai oleh guru.', 'elearning-vd'); ?></div>
                            <a class="btn btn-outline-secondary" :href="backUrl">
                                <?php echo esc_html__('Kembali ke Daftar Quiz', 'elearning-vd'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>