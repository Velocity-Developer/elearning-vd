<?php

defined('ABSPATH') || exit;

/**
 * @var array<string, mixed> $config
 */


if (! is_user_logged_in()) {
    return '<div class="alert alert-warning">' . esc_html__('Silakan login untuk mengakses elearning.', 'elearning-vd') . '</div>';
}

wp_enqueue_style('elvd-main');
wp_enqueue_script('elvd-main');

$config = [
    'restUrl' => esc_url_raw(rest_url(ELVD_REST_NAMESPACE)),
    'nonce' => wp_create_nonce('wp_rest'),
    'isManager' => elvd_can_manage_rest(),
];

$school_name = trim((string) get_option(ELVD::OPTION_SCHOOL_NAME, get_bloginfo('name')));
$school_name = '' !== $school_name ? $school_name : (string) get_bloginfo('name');
$school_logo_id = absint(get_option(ELVD::OPTION_SCHOOL_LOGO_ID, 0));
$school_logo_url = 0 < $school_logo_id ? (string) wp_get_attachment_image_url($school_logo_id, 'thumbnail') : '';

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

wp_localize_script(
    'elvd-main',
    'elvdDashboardChartData',
    [
        'labels' => array_map(static fn(array $metric): string => (string) $metric['label'], $dashboard_metrics),
        'values' => array_map(static fn(array $metric): int => (int) $metric['value'], $dashboard_metrics),
    ]
);
?>

<div
    class="elvd-app"
    @elvd-items-updated.window="items = Array.isArray($event.detail.items) ? $event.detail.items : []"
    x-data='{
        tabs: ["dashboard", "tahun-ajaran", "kelas", "mata-pelajaran", "jadwal-pelajaran", "materi", "guru", "siswa", "quiz"],
        labels: {
            "dashboard": "Dashboard",
            "tahun-ajaran": "Tahun Ajaran",
            "kelas": "Kelas",
            "mata-pelajaran": "Mata Pelajaran",
            "jadwal-pelajaran": "Jadwal Pelajaran",
            "materi": "Materi",
            "guru": "Guru",
            "siswa": "Siswa",
            "siswa-profil": "Profil Siswa",
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
            if (["dashboard", "materi", "guru", "siswa", "siswa-profil"].includes(this.active)) {
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

    <div class="offcanvas offcanvas-start d-lg-none elvd-mobile-sheet" tabindex="-1" id="elvdMobileMenu" aria-labelledby="elvdMobileMenuLabel">
        <div class="offcanvas-header">
            <div>
                <div class="elvd-brand elvd-brand-mobile">
                    <?php if ('' !== $school_logo_url) { ?>
                        <img class="elvd-school-logo" src="<?php echo esc_url($school_logo_url); ?>" alt="<?php echo esc_attr($school_name); ?>">
                    <?php } else { ?>
                        <span class="elvd-mark" aria-hidden="true">VD</span>
                    <?php } ?>
                    <div>
                        <h2 class="elvd-brand-title mb-1" id="elvdMobileMenuLabel"><?php echo esc_html($school_name); ?></h2>
                    </div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo esc_attr__('Tutup menu', 'elearning-vd'); ?>"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="elvd-nav" aria-label="<?php echo esc_attr__('Menu Elearning Mobile', 'elearning-vd'); ?>">
                <template x-for="tab in tabs" :key="tab">
                    <button
                        type="button"
                        class="elvd-nav-link"
                        :class="active === tab ? 'is-active' : ''"
                        @click="select(tab)"
                        x-text="labels[tab]"></button>
                </template>
            </nav>
        </div>
    </div>

    <div class="elvd-shell">
        <aside class="elvd-sidebar d-none d-lg-flex">
            <div class="elvd-sidebar-inner">
                <div class="elvd-brand">
                    <?php if ('' !== $school_logo_url) { ?>
                        <img class="elvd-school-logo" src="<?php echo esc_url($school_logo_url); ?>" alt="<?php echo esc_attr($school_name); ?>">
                    <?php } else { ?>
                        <span class="elvd-mark" aria-hidden="true">VD</span>
                    <?php } ?>
                    <div>
                        <h2 class="elvd-brand-title mb-1"><?php echo esc_html($school_name); ?></h2>
                    </div>
                </div>
                <nav class="elvd-nav" aria-label="<?php echo esc_attr__('Menu Elearning', 'elearning-vd'); ?>">
                    <template x-for="tab in tabs" :key="tab">
                        <button
                            type="button"
                            class="elvd-nav-link"
                            :class="active === tab ? 'is-active' : ''"
                            @click="select(tab)"
                            x-text="labels[tab]"></button>
                    </template>
                </nav>
                <div class="elvd-sidebar-note">
                    <span><?php echo esc_html__('Status', 'elearning-vd'); ?></span>
                    <strong><?php echo esc_html__('Aktif', 'elearning-vd'); ?></strong>
                </div>
            </div>
        </aside>

        <section class="elvd-main">
            <div class="elvd-topbar">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <button
                        type="button"
                        class="elvd-icon-button d-lg-none"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#elvdMobileMenu"
                        aria-controls="elvdMobileMenu"
                        aria-label="<?php echo esc_attr__('Buka menu', 'elearning-vd'); ?>">
                        <span aria-hidden="true"></span>
                    </button>
                    <div class="min-w-0">
                        <h1 class="elvd-page-title mb-0" x-text="labels[active] || defaultLabel"></h1>
                    </div>
                </div>
            </div>

            <div x-show="active === 'dashboard'">
                <div class="elvd-hero">
                    <div class="elvd-chevron elvd-chevron-left" aria-hidden="true"></div>
                    <div class="elvd-chevron elvd-chevron-right" aria-hidden="true"></div>
                    <div class="elvd-hero-copy">
                        <p class="elvd-eyebrow mb-2"><?php echo esc_html__('Ringkasan akademik', 'elearning-vd'); ?></p>
                        <h2><?php echo esc_html__('Kelola pembelajaran dari satu ruang kerja.', 'elearning-vd'); ?></h2>
                        <p><?php echo esc_html__('Pantau siswa, guru, kelas, mata pelajaran, konten, dan quiz dengan ritme visual yang bersih.', 'elearning-vd'); ?></p>
                    </div>
                    <div class="elvd-hero-stat">
                        <span><?php echo esc_html__('Total entitas', 'elearning-vd'); ?></span>
                        <strong><?php echo esc_html(number_format_i18n(array_sum(array_column($dashboard_metrics, 'value')))); ?></strong>
                    </div>
                </div>

                <div class="elvd-metric-grid">
                    <?php foreach (array_slice($dashboard_metrics, 0, 4) as $metric) { ?>
                        <div class="elvd-metric-card">
                            <span class="elvd-metric-kicker"><?php echo esc_html($metric['label']); ?></span>
                            <strong><?php echo esc_html(number_format_i18n($metric['value'])); ?></strong>
                            <span class="elvd-metric-line" aria-hidden="true"></span>
                        </div>
                    <?php } ?>
                </div>

                <div class="elvd-dashboard-grid">
                    <div class="elvd-panel">
                        <div class="elvd-panel-heading">
                            <div>
                                <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Statistik', 'elearning-vd'); ?></p>
                                <h2><?php echo esc_html__('Ringkasan Data', 'elearning-vd'); ?></h2>
                            </div>
                        </div>
                        <div class="elvd-chart-wrap">
                            <canvas id="elvdDashboardChart" aria-label="<?php echo esc_attr__('Chart ringkasan data elearning', 'elearning-vd'); ?>" role="img"></canvas>
                        </div>
                        <div class="elvd-bars">
                            <?php foreach ($dashboard_metrics as $metric) {
                                $bar_width = 0 < $max_dashboard_value ? max(6, (int) round(($metric['value'] / $max_dashboard_value) * 100)) : 0;
                            ?>
                                <div class="elvd-bar-row">
                                    <div class="elvd-bar-label">
                                        <span><?php echo esc_html($metric['label']); ?></span>
                                        <strong><?php echo esc_html(number_format_i18n($metric['value'])); ?></strong>
                                    </div>
                                    <div class="elvd-progress" role="progressbar" aria-label="<?php echo esc_attr($metric['label']); ?>" aria-valuenow="<?php echo esc_attr((string) $metric['value']); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr((string) max(1, $max_dashboard_value)); ?>">
                                        <span style="width: <?php echo esc_attr((string) $bar_width); ?>%;"></span>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="elvd-panel elvd-panel-dark">
                        <div class="elvd-panel-heading">
                            <div>
                                <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Aktivitas', 'elearning-vd'); ?></p>
                                <h2><?php echo esc_html__('Aktivitas Pembelajaran', 'elearning-vd'); ?></h2>
                            </div>
                        </div>
                        <div class="elvd-activity-list">
                            <div class="elvd-activity-item">
                                <span><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></span>
                                <strong><?php echo esc_html(number_format_i18n($count_table_rows('elvd_tahun_ajaran'))); ?></strong>
                            </div>
                            <div class="elvd-activity-item">
                                <span><?php echo esc_html__('Pengerjaan Quiz', 'elearning-vd'); ?></span>
                                <strong><?php echo esc_html(number_format_i18n($count_table_rows('elvd_pengerjaan_quiz'))); ?></strong>
                            </div>
                            <div class="elvd-activity-item">
                                <span><?php echo esc_html__('Konten Belajar', 'elearning-vd'); ?></span>
                                <strong><?php echo esc_html(number_format_i18n((isset($tugas_counts->publish) ? (int) $tugas_counts->publish : 0) + (isset($materi_counts->publish) ? (int) $materi_counts->publish : 0) + (isset($quiz_counts->publish) ? (int) $quiz_counts->publish : 0))); ?></strong>
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

            <div class="elvd-table-panel" x-show="!['dashboard', 'tahun-ajaran', 'kelas', 'mata-pelajaran', 'jadwal-pelajaran', 'guru', 'siswa', 'siswa-profil', 'materi'].includes(active)">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 elvd-table">
                        <thead>
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
            </div>
        </section>
    </div>
</div>