<?php

defined('ABSPATH') || exit;

$elvd_quiz_form_url = untrailingslashit(ELVD::app_route()) . '/quiz-form';
$elvd_quiz_workspace_url = untrailingslashit(ELVD::app_route()) . '/quiz-workspace';
$elvd_quiz_answer_url = untrailingslashit(ELVD::app_route()) . '/quiz-answer';
$elvd_quiz_pengerjaan_url = untrailingslashit(rest_url(ELVD_REST_NAMESPACE . '/pengerjaan-quiz'));
?>

<div x-show="active === 'quiz'" x-data="{
    quizzes: [],
    classes: [],
    subjects: [],
    restUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url('wp/v2/elvd_quiz')))); ?>,
    formUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_form_url)); ?>,
    workspaceUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_workspace_url)); ?>,
    answerUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_answer_url)); ?>,
    pengerjaanUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_pengerjaan_url)); ?>,
    loading: false,
    loadingRelations: false,
    loadingAttempts: false,
    error: '',
    filterTipe: '',
    filterKelas: '',
    myAttempts: {},
    init() {
        this.fetchQuizzes();
        this.fetchRelations();
        if (!config.isManager) {
            this.fetchMyAttempts();
        }
    },
    metaValue(item, key) {
        return item.meta && item.meta[key] ? item.meta[key] : '';
    },
    titleOf(item) {
        return (item.title && (item.title.rendered || item.title.raw)) ? (item.title.rendered || item.title.raw) : '-';
    },
    fetchQuizzes() {
        this.loading = true;
        this.error = '';

        const params = new URLSearchParams({ per_page: '100' });

        if (this.filterTipe) {
            params.set('elvd_filter_tipe', this.filterTipe);
        }

        if (this.filterKelas) {
            params.set('elvd_filter_kelas', String(this.filterKelas));
        }

        fetch(`${this.restUrl}?${params.toString()}`, {
            headers: { 'X-WP-Nonce': config.nonce }
        })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Gagal memuat data quiz.');
            }

            return response.json();
        })
        .then((data) => {
            this.quizzes = Array.isArray(data) ? data : [];
            this.$dispatch('elvd-items-updated', { items: this.quizzes });
        })
        .catch((error) => {
            this.error = error.message || 'Gagal memuat data quiz.';
        })
        .finally(() => {
            this.loading = false;
        });
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
    className(id) {
        const classItem = this.classes.find((item) => Number(item.id) === Number(id));
        return classItem ? classItem.nama : '-';
    },
    subjectName(id) {
        const subject = this.subjects.find((item) => Number(item.id) === Number(id));
        return subject ? subject.nama : '-';
    },
    tipeLabel(value) {
        return value === 'essay' ? 'Essay' : 'Pilihan Ganda';
    },
    formatNilai(nilai) {
        return nilai === null || nilai === '' ? '–' : `${Math.round(Number(nilai))}`;
    },
    fetchMyAttempts() {
        this.loadingAttempts = true;

        fetch(`${this.pengerjaanUrl}?per_page=100&siswa_id=${config.userId}`, {
            headers: { 'X-WP-Nonce': config.nonce }
        })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Gagal memuat pengerjaan Anda.');
            }
            return response.json();
        })
        .then((data) => {
            const attempts = Array.isArray(data) ? data : [];
            const map = {};
            attempts.forEach((attempt) => {
                if (!map[attempt.quiz_id] || (attempt.nilai !== null && (map[attempt.quiz_id].nilai === null || attempt.nilai > map[attempt.quiz_id].nilai))) {
                    map[attempt.quiz_id] = attempt;
                }
            });
            this.myAttempts = map;
        })
        .catch((error) => {
            console.warn('Gagal memuat pengerjaan:', error.message);
        })
        .finally(() => {
            this.loadingAttempts = false;
        });
    },
    myAttempt(quizId) {
        return this.myAttempts[Number(quizId)] || null;
    },
    deleteQuiz(item) {
        if (!window.confirm('Yakin hapus quiz ini?')) {
            return;
        }

        fetch(`${this.restUrl}/${item.id}`, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': config.nonce }
        })
        .then((response) => response.json().then((data) => ({ response, data })))
        .then(({ response }) => {
            if (!response.ok) {
                throw new Error('Gagal menghapus quiz.');
            }

            this.quizzes = this.quizzes.filter((quiz) => Number(quiz.id) !== Number(item.id));
            this.$dispatch('elvd-items-updated', { items: this.quizzes });
        })
        .catch((error) => {
            this.error = error.message || 'Gagal menghapus quiz.';
        });
    }
}">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <div></div>
            <a
                class="btn btn-primary elvd-action-button"
                x-show="config.isManager"
                :href="`${formUrl}/`">
                <i class="bi bi-plus-lg me-1"></i><?php echo esc_html__('Tambah Quiz', 'elearning-vd'); ?>
            </a>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="d-flex flex-wrap gap-3 pb-3 pt-2">
            <div class="col-md-3 col-12">
                <label class="form-label" for="elvd-filter-tipe"><?php echo esc_html__('Filter Tipe', 'elearning-vd'); ?></label>
                <select class="form-select" id="elvd-filter-tipe" x-model="filterTipe" @change="fetchQuizzes()">
                    <option value=""><?php echo esc_html__('Semua Tipe', 'elearning-vd'); ?></option>
                    <option value="pilihan_ganda"><?php echo esc_html__('Pilihan Ganda', 'elearning-vd'); ?></option>
                    <option value="essay"><?php echo esc_html__('Essay', 'elearning-vd'); ?></option>
                </select>
            </div>
            <div class="col-md-3 col-12">
                <label class="form-label" for="elvd-filter-kelas"><?php echo esc_html__('Filter Kelas', 'elearning-vd'); ?></label>
                <select class="form-select" id="elvd-filter-kelas" x-model="filterKelas" :disabled="loadingRelations" @change="fetchQuizzes()">
                    <option value=""><?php echo esc_html__('Semua Kelas', 'elearning-vd'); ?></option>
                    <template x-for="classItem in classes" :key="classItem.id">
                        <option :value="String(classItem.id)" x-text="classItem.nama"></option>
                    </template>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Judul', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Tipe', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Durasi (Menit)', 'elearning-vd'); ?></th>
                        <th scope="col" class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loading">
                        <td colspan="6"><?php echo esc_html__('Memuat data quiz...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="item in quizzes" :key="item.id">
                        <tr>
                            <td x-text="titleOf(item)"></td>
                            <td x-text="tipeLabel(metaValue(item, 'elvd_quiz_tipe'))"></td>
                            <td x-text="subjectName(metaValue(item, 'elvd_mata_pelajaran_id'))"></td>
                            <td x-text="className(metaValue(item, 'elvd_kelas_id'))"></td>
                            <td x-text="metaValue(item, 'elvd_durasi_menit') || '-'"></td>
                            <td class="text-end">
                                <a
                                    title="<?php echo esc_html__('Lihat', 'elearning-vd'); ?>"
                                    class="btn btn-sm btn-outline-primary elvd-row-action"
                                    :href="`${workspaceUrl}/${item.id}/`"
                                    x-show="!myAttempt(item.id)">
                                    <i class="bi bi-play-fill"></i>
                                </a>
                                <template x-if="myAttempt(item.id)">
                                    <a
                                        title="<?php echo esc_html__('Lihat Hasil', 'elearning-vd'); ?>"
                                        class="btn btn-sm btn-outline-secondary elvd-row-action"
                                        :href="`${answerUrl}/${item.id}/`">
                                        <i class="bi bi-clipboard2-check"></i>
                                    </a>
                                    <span class="badge rounded-pill text-bg-success mx-2" x-text="formatNilai(myAttempt(item.id).nilai)"></span>
                                </template>
                                <a
                                    title="<?php echo esc_html__('Jawab', 'elearning-vd'); ?>"
                                    class="btn btn-sm btn-outline-secondary elvd-row-action"
                                    x-show="config.isManager"
                                    :href="`${answerUrl}/${item.id}/`">
                                    <i class="bi bi-clipboard2-check"></i>
                                </a>
                                <a
                                    title="<?php echo esc_html__('Edit', 'elearning-vd'); ?>"
                                    class="btn btn-sm btn-outline-secondary elvd-row-action"
                                    x-show="config.isManager"
                                    :href="`${formUrl}/${item.id}/`">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button
                                    title="<?php echo esc_html__('Hapus', 'elearning-vd'); ?>"
                                    type="button"
                                    class="btn btn-sm btn-outline-danger elvd-row-action"
                                    x-show="config.currentRole === 'administrator' || Number(item.author) === Number(config.userId)"
                                    @click="deleteQuiz(item)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loading && quizzes.length === 0">
                        <td colspan="6"><?php echo esc_html__('Belum ada quiz.', 'elearning-vd'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>