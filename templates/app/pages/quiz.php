<?php

defined('ABSPATH') || exit;

$elvd_quiz_form_url = untrailingslashit(ELVD::app_route()) . '/quiz-form';
$elvd_quiz_workspace_url = untrailingslashit(ELVD::app_route()) . '/quiz-workspace';
$elvd_quiz_answer_url = untrailingslashit(ELVD::app_route()) . '/quiz-answer';
?>

<div x-show="active === 'quiz'" x-data="{
    quizzes: [],
    classes: [],
    subjects: [],
    restUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url('wp/v2/elvd_quiz')))); ?>,
    formUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_form_url)); ?>,
    workspaceUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_workspace_url)); ?>,
    answerUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_answer_url)); ?>,
    loading: false,
    loadingRelations: false,
    error: '',
    filterTipe: '',
    filterKelas: '',
    init() {
        this.fetchQuizzes();
        this.fetchRelations();
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
    }
}">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <div></div>
            <a
                class="btn btn-primary elvd-action-button"
                x-show="config.isManager"
                :href="`${formUrl}/`">
                <?php echo esc_html__('Tambah Quiz', 'elearning-vd'); ?>
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
                                    class="btn btn-sm btn-outline-primary elvd-row-action"
                                    :href="`${workspaceUrl}/${item.id}/`">
                                    <?php echo esc_html__('Kerjakan', 'elearning-vd'); ?>
                                </a>
                                <a
                                    class="btn btn-sm btn-outline-secondary elvd-row-action"
                                    x-show="config.isManager"
                                    :href="`${answerUrl}/${item.id}/`">
                                    <?php echo esc_html__('Nilai', 'elearning-vd'); ?>
                                </a>
                                <a
                                    class="btn btn-sm btn-outline-secondary elvd-row-action"
                                    x-show="config.isManager"
                                    :href="`${formUrl}/${item.id}/`">
                                    <?php echo esc_html__('Edit', 'elearning-vd'); ?>
                                </a>
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