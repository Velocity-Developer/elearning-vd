<?php

defined('ABSPATH') || exit;

$elvd_quiz_form_url = untrailingslashit(ELVD::app_route()) . '/quiz-form';
?>

<div x-show="active === 'quiz'" x-data="{
    quizzes: [],
    classes: [],
    subjects: [],
    restUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url('wp/v2/elvd_quiz')))); ?>,
    formUrl: <?php echo esc_attr(wp_json_encode($elvd_quiz_form_url)); ?>,
    loading: false,
    loadingRelations: false,
    error: '',
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

        fetch(`${this.restUrl}?per_page=100`, {
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