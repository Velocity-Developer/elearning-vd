<?php

defined('ABSPATH') || exit;

/**
 * @var array<string, mixed> $config
 */


if (! is_user_logged_in()) {
    return '<div class="alert alert-warning">' . esc_html__('Silakan login untuk mengakses elearning.', 'elearning-vd') . '</div>';
}

wp_enqueue_style('elvd-bootstrap');
wp_enqueue_script('elvd-alpine');

$config = [
    'restUrl' => esc_url_raw(rest_url(ELVD_REST_NAMESPACE)),
    'nonce' => wp_create_nonce('wp_rest'),
    'isManager' => elvd_can_manage_rest(),
];

$route_page = sanitize_key((string) get_query_var(ELVD::APP_PAGE_QUERY_VAR, ''));
$default_page = 'tahun-ajaran';
$page_file = '';

if ('' !== $route_page) {
    $candidate_page_file = ELVD_PLUGIN_DIR . 'templates/app/pages/' . $route_page . '.php';

    if (file_exists($candidate_page_file)) {
        $page_file = $candidate_page_file;
    } else {
        status_header(404);
        nocache_headers();
    }
}

$active_page = '' !== $route_page ? $route_page : $default_page;

global $wpdb;

$count_table_rows = static function (string $table) use ($wpdb): int {
    $table_name = elvd_table_name($table);
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

    if ($table_exists !== $table_name) {
        return 0;
    }

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
};

$user_counts = count_users();
$role_counts = isset($user_counts['avail_roles']) && is_array($user_counts['avail_roles']) ? $user_counts['avail_roles'] : [];
$tugas_counts = wp_count_posts('elvd_tugas');
$materi_counts = wp_count_posts('elvd_materi');
$quiz_counts = wp_count_posts('elvd_quiz');

$dashboard_metrics = [
    [
        'label' => __('Siswa', 'elearning-vd'),
        'value' => (int) ($role_counts['siswa'] ?? 0),
        'tone' => 'primary',
    ],
    [
        'label' => __('Guru', 'elearning-vd'),
        'value' => (int) ($role_counts['guru'] ?? 0),
        'tone' => 'success',
    ],
    [
        'label' => __('Kelas', 'elearning-vd'),
        'value' => $count_table_rows('elvd_kelas'),
        'tone' => 'info',
    ],
    [
        'label' => __('Mata Pelajaran', 'elearning-vd'),
        'value' => $count_table_rows('elvd_mata_pelajaran'),
        'tone' => 'warning',
    ],
    [
        'label' => __('Jadwal Pelajaran', 'elearning-vd'),
        'value' => $count_table_rows('elvd_jadwal_pelajaran'),
        'tone' => 'secondary',
    ],
    [
        'label' => __('Tugas', 'elearning-vd'),
        'value' => isset($tugas_counts->publish) ? (int) $tugas_counts->publish : 0,
        'tone' => 'danger',
    ],
    [
        'label' => __('Materi', 'elearning-vd'),
        'value' => isset($materi_counts->publish) ? (int) $materi_counts->publish : 0,
        'tone' => 'dark',
    ],
    [
        'label' => __('Quiz', 'elearning-vd'),
        'value' => isset($quiz_counts->publish) ? (int) $quiz_counts->publish : 0,
        'tone' => 'primary',
    ],
];

$max_dashboard_value = max(array_column($dashboard_metrics, 'value'));
?>

<div
    class="elvd-app container-fluid my-4 px-3 px-lg-4"
    x-data='{
        tabs: ["dashboard", "tahun-ajaran", "kelas", "mata-pelajaran", "jadwal-pelajaran", "guru", "siswa", "quiz"],
        labels: {
            "dashboard": "Dashboard",
            "tahun-ajaran": "Tahun Ajaran",
            "kelas": "Kelas",
            "mata-pelajaran": "Mata Pelajaran",
            "jadwal-pelajaran": "Jadwal Pelajaran",
            "guru": "Guru",
            "siswa": "Siswa",
            "quiz": "Quiz"
        },
        defaultLabel: <?php echo esc_attr(wp_json_encode(__('Elearning VD', 'elearning-vd'))); ?>,
        active: <?php echo esc_attr(wp_json_encode('' !== $route_page ? $active_page : 'dashboard')); ?>,
        appRoute: <?php echo esc_attr(wp_json_encode(untrailingslashit(ELVD::app_route()))); ?>,
        items: [],
        loading: false,
        config: <?php echo esc_attr(wp_json_encode($config)); ?>,
        init() { this.load(); },
        load() {
            if (this.active === "dashboard") {
                this.items = [];
                this.loading = false;
                return;
            }

            this.loading = true;
            fetch(`${this.config.restUrl}/${this.active}`, {
                headers: { "X-WP-Nonce": this.config.nonce }
            })
            .then((response) => response.json())
            .then((data) => { this.items = Array.isArray(data) ? data : []; })
            .finally(() => { this.loading = false; });
        },
        select(tab) {
            this.active = tab;
            if (tab === "dashboard") {
                window.location.href = `${this.appRoute}/`;
                return;
            }

            window.location.href = `${this.appRoute}/${tab}/`;
        }
    }'>
    <div class="row g-4">
        <aside class="col-12 col-lg-3 col-xl-2">
            <div class="border rounded bg-white shadow-sm">
                <div class="border-bottom p-3">
                    <h2 class="h5 mb-1"><?php echo esc_html__('Elearning VD', 'elearning-vd'); ?></h2>
                    <p class="small text-muted mb-0"><?php echo esc_html__('Dashboard sekolah', 'elearning-vd'); ?></p>
                </div>
                <nav class="nav nav-pills flex-column gap-1 p-2" aria-label="<?php echo esc_attr__('Menu Elearning', 'elearning-vd'); ?>">
                    <template x-for="tab in tabs" :key="tab">
                        <button
                            type="button"
                            class="nav-link text-start"
                            :class="active === tab ? 'active' : 'text-dark'"
                            @click="select(tab)"
                            x-text="labels[tab]"></button>
                    </template>
                </nav>
            </div>
        </aside>

        <section class="col-12 col-lg-9 col-xl-10">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h1 class="h3 mb-0" x-text="labels[active] || defaultLabel"></h1>
                </div>
                <div class="badge text-bg-light border px-3 py-2" x-show="active !== 'dashboard' && !loading">
                    <span x-text="items.length"></span>
                    <?php echo esc_html__('data', 'elearning-vd'); ?>
                </div>
            </div>

            <div x-show="active === 'dashboard'">
                <div class="row g-3 mb-4">
                    <?php foreach (array_slice($dashboard_metrics, 0, 4) as $metric) { ?>
                        <div class="col-6 col-xl-3">
                            <div class="border rounded bg-white shadow-sm p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <span class="small text-muted"><?php echo esc_html($metric['label']); ?></span>
                                    <span class="badge text-bg-<?php echo esc_attr($metric['tone']); ?>">&nbsp;</span>
                                </div>
                                <div class="display-6 fw-semibold mt-2"><?php echo esc_html(number_format_i18n($metric['value'])); ?></div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div class="row g-4">
                    <div class="col-12 col-xl-8">
                        <div class="border rounded bg-white shadow-sm p-3">
                            <h2 class="h5 mb-3"><?php echo esc_html__('Ringkasan Data', 'elearning-vd'); ?></h2>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($dashboard_metrics as $metric) {
                                    $bar_width = 0 < $max_dashboard_value ? max(6, (int) round(($metric['value'] / $max_dashboard_value) * 100)) : 0;
                                ?>
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between gap-3 mb-1">
                                            <span class="small fw-semibold"><?php echo esc_html($metric['label']); ?></span>
                                            <span class="small text-muted"><?php echo esc_html(number_format_i18n($metric['value'])); ?></span>
                                        </div>
                                        <div class="progress" role="progressbar" aria-label="<?php echo esc_attr($metric['label']); ?>" aria-valuenow="<?php echo esc_attr((string) $metric['value']); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr((string) max(1, $max_dashboard_value)); ?>" style="height: 0.75rem;">
                                            <div class="progress-bar bg-<?php echo esc_attr($metric['tone']); ?>" style="width: <?php echo esc_attr((string) $bar_width); ?>%;"></div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-4">
                        <div class="border rounded bg-white shadow-sm p-3 h-100">
                            <h2 class="h5 mb-3"><?php echo esc_html__('Aktivitas Pembelajaran', 'elearning-vd'); ?></h2>
                            <div class="list-group list-group-flush">
                                <div class="list-group-item px-0 d-flex justify-content-between">
                                    <span><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></span>
                                    <strong><?php echo esc_html(number_format_i18n($count_table_rows('elvd_tahun_ajaran'))); ?></strong>
                                </div>
                                <div class="list-group-item px-0 d-flex justify-content-between">
                                    <span><?php echo esc_html__('Pengerjaan Quiz', 'elearning-vd'); ?></span>
                                    <strong><?php echo esc_html(number_format_i18n($count_table_rows('elvd_pengerjaan_quiz'))); ?></strong>
                                </div>
                                <div class="list-group-item px-0 d-flex justify-content-between">
                                    <span><?php echo esc_html__('Konten Belajar', 'elearning-vd'); ?></span>
                                    <strong><?php echo esc_html(number_format_i18n((isset($tugas_counts->publish) ? (int) $tugas_counts->publish : 0) + (isset($materi_counts->publish) ? (int) $materi_counts->publish : 0) + (isset($quiz_counts->publish) ? (int) $quiz_counts->publish : 0))); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            if ('' !== $route_page && '' === $page_file) {
            ?>
                <div class="alert alert-warning mb-3">
                    <?php echo esc_html__('Halaman elearning tidak ditemukan.', 'elearning-vd'); ?>
                </div>
            <?php
            } elseif ('' !== $page_file) {
                require $page_file;
            }
            ?>

            <div class="table-responsive border rounded bg-white shadow-sm" x-show="active !== 'dashboard'">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col"><?php echo esc_html__('ID', 'elearning-vd'); ?></th>
                            <th scope="col"><?php echo esc_html__('Nama', 'elearning-vd'); ?></th>
                            <th scope="col"><?php echo esc_html__('Detail', 'elearning-vd'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr x-show="loading">
                            <td colspan="3"><?php echo esc_html__('Memuat data...', 'elearning-vd'); ?></td>
                        </tr>
                        <template x-for="item in items" :key="item.id">
                            <tr>
                                <td x-text="item.id"></td>
                                <td x-text="item.nama || item.hari || '-'"></td>
                                <td x-text="Object.entries(item).filter(([key]) => !['id','nama','created_at','updated_at'].includes(key)).map(([key, value]) => `${key}: ${value ?? '-'}`).join(' | ')"></td>
                            </tr>
                        </template>
                        <tr x-show="!loading && items.length === 0">
                            <td colspan="3"><?php echo esc_html__('Belum ada data.', 'elearning-vd'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>