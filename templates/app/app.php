<?php

defined('ABSPATH') || exit;

/**
 * @var array<string, mixed> $config
 */


if (! is_user_logged_in()) {

    echo '<div class="mx-auto container" style="min-height: 50vh;max-width: 33rem;">';
    echo '<div class="alert alert-warning">' . esc_html__('Silakan login untuk mengakses elearning.', 'elearning-vd') . '</div>';
    echo do_shortcode('[elvd-form-login]');
    echo '</div>';
} else {

    wp_enqueue_style('elvd-main');
    wp_enqueue_script('elvd-main');

    $elvd_current_user = wp_get_current_user();
    $elvd_current_role = (string) current(
        array_intersect(
            ['administrator', 'guru', 'siswa'],
            (array) $elvd_current_user->roles
        )
    );

    $elvd_siswa_kelas_id = 0;

    if ('siswa' === $elvd_current_role) {
        $elvd_kelas_meta = (string) get_user_meta($elvd_current_user->ID, 'elvd_kelas', true);

        if ('' !== $elvd_kelas_meta) {
            global $wpdb;

            $elvd_kelas_row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT id FROM `%1$s` WHERE id = %2$d OR nama = %3$s LIMIT 1',
                    elvd_table_name('elvd_kelas'),
                    absint($elvd_kelas_meta),
                    $elvd_kelas_meta
                )
            );

            if ($elvd_kelas_row) {
                $elvd_siswa_kelas_id = (int) $elvd_kelas_row->id;
            }
        }
    }

    $config = [
        'restUrl' => esc_url_raw(rest_url(ELVD_REST_NAMESPACE)),
        'nonce' => wp_create_nonce('wp_rest'),
        'isManager' => elvd_can_manage_rest(),
        'currentRole' => $elvd_current_role,
        'userId' => (int) $elvd_current_user->ID,
        'siswaKelasId' => $elvd_siswa_kelas_id,
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

    // Data dashboard untuk role siswa.
    $elvd_siswa_jadwal = [];
    $elvd_siswa_tugas = [];
    $elvd_siswa_materi = [];
    $elvd_siswa_quiz = [];

    if ('siswa' === $elvd_current_role && 0 < $elvd_siswa_kelas_id) {
        $elvd_siswa_jadwal = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, mp.nama AS mata_pelajaran, u.display_name AS guru
                 FROM %1\$s j
                 LEFT JOIN %2\$s mp ON mp.id = j.mata_pelajaran_id
                 LEFT JOIN {$wpdb->users} u ON u.ID = j.guru_id
                 WHERE j.kelas_id = %3\$d
                 ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai
                 LIMIT 20",
                elvd_table_name('elvd_jadwal_pelajaran'),
                elvd_table_name('elvd_mata_pelajaran'),
                $elvd_siswa_kelas_id
            )
        );

        $elvd_kelas_args = static function (string $post_type) use ($elvd_siswa_kelas_id): array {
            return [
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_key' => 'elvd_kelas_id',
                'meta_value' => $elvd_siswa_kelas_id,
                'meta_compare' => '=',
            ];
        };

        $elvd_siswa_tugas = get_posts($elvd_kelas_args('elvd_tugas'));
        $elvd_siswa_materi = get_posts($elvd_kelas_args('elvd_materi'));
        $elvd_siswa_quiz = get_posts($elvd_kelas_args('elvd_quiz'));
    }

    // Data dashboard untuk role guru.
    $elvd_guru_jadwal = [];
    $elvd_guru_mapel = [];
    $elvd_guru_tugas = [];
    $elvd_guru_materi = [];
    $elvd_guru_quiz = [];

    if ('guru' === $elvd_current_role) {
        $elvd_guru_jadwal = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, k.nama AS kelas, mp.nama AS mata_pelajaran
                 FROM `%1\$s` j
                 LEFT JOIN `%2\$s` k ON k.id = j.kelas_id
                 LEFT JOIN `%3\$s` mp ON mp.id = j.mata_pelajaran_id
                 WHERE j.guru_id = %4\$d
                 ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai
                 LIMIT 20",
                elvd_table_name('elvd_jadwal_pelajaran'),
                elvd_table_name('elvd_kelas'),
                elvd_table_name('elvd_mata_pelajaran'),
                $elvd_current_user->ID
            )
        );

        $elvd_guru_content_args = static fn(string $post_type): array => [
            'post_type' => $post_type,
            'author' => $elvd_current_user->ID,
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $elvd_guru_mapel = class_exists(\ElearningVD\Mapel::class)
            ? \ElearningVD\Mapel::dapatkan_oleh_guru($elvd_current_user->ID)
            : [];

        $elvd_guru_tugas = get_posts($elvd_guru_content_args('elvd_tugas'));
        $elvd_guru_materi = get_posts($elvd_guru_content_args('elvd_materi'));
        $elvd_guru_quiz = get_posts($elvd_guru_content_args('elvd_quiz'));
    }

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
            'value' => count($elvd_guru_mapel),
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

    $elvd_guru_metrics = [
        [
            'label' => __('Kelas', 'elearning-vd'),
            'value' => $count_table_rows('elvd_kelas'),
            'tone' => 'info',
        ],
        [
            'label' => __('Mata Pelajaran', 'elearning-vd'),
            'value' => count($elvd_guru_mapel),
            'tone' => 'warning',
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
    ];

    wp_localize_script(
        'elvd-main',
        'elvdDashboardChartData',
        [
            'labels' => array_map(static fn(array $metric): string => (string) $metric['label'], $dashboard_metrics),
            'values' => array_map(static fn(array $metric): int => (int) $metric['value'], $dashboard_metrics),
        ]
    );
?>

    <script>
        window.elvdAppConfig = {
            tabs: <?php echo wp_json_encode(
                        'siswa' === $elvd_current_role
                            ? ['dashboard', 'jadwal-pelajaran-siswa', 'tugas', 'materi', 'quiz']
                            : ('guru' === $elvd_current_role
                                ? ['dashboard', 'jadwal-pelajaran', 'tugas', 'materi', 'quiz', 'siswa', 'kelas', 'mata-pelajaran']
                                : ['dashboard', 'tahun-ajaran', 'kelas', 'mata-pelajaran', 'jadwal-pelajaran', 'tugas', 'materi', 'guru', 'siswa', 'quiz']
                            )
                    ); ?>,
            labels: {
                "dashboard": "Dashboard",
                "tahun-ajaran": "Tahun Ajaran",
                "kelas": "Kelas",
                "mata-pelajaran": "Mata Pelajaran",
                "jadwal-pelajaran": "Jadwal Pelajaran",
                "jadwal-pelajaran-siswa": "Jadwal Pelajaran",
                "tugas": "Tugas",
                "materi": "Materi",
                "guru": "Guru",
                "siswa": "Siswa",
                "siswa-profil": "Profil Siswa",
                "guru-profil": "Profil Guru",
                "quiz": "Quiz",
                "quiz-form": "Form Quiz",
                "quiz-workspace": "Kerjakan Quiz",
                "quiz-answer": "Hasil Quiz",
                "tugas-answer": "Hasil Tugas"
            },
            icons: {
                "dashboard": "bi bi-grid-1x2",
                "tahun-ajaran": "bi bi-calendar3",
                "kelas": "bi bi-door-open",
                "mata-pelajaran": "bi bi-book",
                "jadwal-pelajaran": "bi bi-clock-history",
                "jadwal-pelajaran-siswa": "bi bi-clock-history",
                "tugas": "bi bi-clipboard-check",
                "materi": "bi bi-journal-text",
                "guru": "bi bi-person-badge",
                "siswa": "bi bi-people",
                "quiz": "bi bi-patch-question"
            },
            defaultLabel: <?php echo wp_json_encode(__('Elearning VD', 'elearning-vd')); ?>,
            active: <?php echo wp_json_encode('' !== $route_page ? $active_page : 'dashboard'); ?>,
            appRoute: <?php echo wp_json_encode(untrailingslashit(ELVD::app_route())); ?>,
            items: [],
            loading: false,
            config: <?php echo wp_json_encode($config); ?>,
            init() {
                this.load();
            },
            load() {
                const hiddenTabs = this.config.currentRole !== "guru" ? ["guru", "siswa"] : ["tahun-ajaran", "guru"];

                if ([...hiddenTabs, "dashboard", "jadwal-pelajaran-siswa", "tugas", "materi", "siswa", "siswa-profil", "guru-profil", "quiz-form", "quiz-workspace", "quiz-answer", "tugas-answer"].includes(this.active)) {
                    this.items = [];
                    this.loading = false;
                    return;
                }

                this.loading = true;
                fetch(`${this.config.restUrl}/${this.active}`, {
                        headers: {
                            "X-WP-Nonce": this.config.nonce
                        }
                    })
                    .then((response) => response.json())
                    .then((data) => {
                        this.items = Array.isArray(data) ? data : [];
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },
            select(tab) {
                this.active = tab;
                if (tab === "dashboard") {
                    window.location.href = `${this.appRoute}/`;
                    return;
                }

                const targetTab = this.config.currentRole === "siswa" && tab === "jadwal-pelajaran" ?
                    "jadwal-pelajaran-siswa" :
                    tab;

                window.location.href = `${this.appRoute}/${targetTab}/`;
            }
        };
    </script>

    <script>
        window.config = window.elvdAppConfig.config;
    </script>

    <div
        class="elvd-app"
        @elvd-items-updated.window="items = Array.isArray($event.detail.items) ? $event.detail.items : []"
        x-data="window.elvdAppConfig">

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
                            @click="select(tab)">
                            <i :class="icons[tab]" aria-hidden="true"></i>
                            <span x-text="labels[tab]"></span>
                        </button>
                    </template>
                    <a class="elvd-nav-link" href="<?php echo esc_url(wp_logout_url(untrailingslashit(ELVD::app_route()))); ?>">
                        <i class="bi bi-door-open"></i>
                        <?php echo esc_html__('Keluar', 'elearning-vd'); ?>
                    </a>
                </nav>
                <hr class="elvd-mobile-divider">
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
                                @click="select(tab)">
                                <i :class="icons[tab]" aria-hidden="true"></i>
                                <span x-text="labels[tab]"></span>
                            </button>
                        </template>
                        <a class="elvd-nav-link" href="<?php echo esc_url(wp_logout_url(untrailingslashit(ELVD::app_route()))); ?>">
                            <i class="bi bi-door-open"></i>
                            <?php echo esc_html__('Keluar', 'elearning-vd'); ?>
                        </a>
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

                <?php if ('' === $route_page && 'siswa' === $elvd_current_role) { ?>
                    <div x-show="active === 'dashboard'">
                        <div class="elvd-hero">
                            <div class="elvd-hero-copy">
                                <p class="elvd-eyebrow mb-2"><?php echo esc_html__('Dashboard Siswa', 'elearning-vd'); ?></p>
                                <h3><?php echo esc_html__('Halo, selamat datang di ruang belajarmu.', 'elearning-vd'); ?></h3>
                                <p><?php echo esc_html__('Pantau jadwal pelajaran, tugas, materi, dan quiz terbaru untuk kelasmu.', 'elearning-vd'); ?></p>
                            </div>
                        </div>

                        <div class="elvd-dashboard-grid">
                            <div class="elvd-panel">
                                <div class="elvd-panel-heading">
                                    <div>
                                        <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Jadwal', 'elearning-vd'); ?></p>
                                        <h2><?php echo esc_html__('Jadwal Pelajaran', 'elearning-vd'); ?></h2>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0 elvd-table">
                                        <thead>
                                            <tr>
                                                <th scope="col"><?php echo esc_html__('Hari', 'elearning-vd'); ?></th>
                                                <th scope="col"><?php echo esc_html__('Jam', 'elearning-vd'); ?></th>
                                                <th scope="col"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></th>
                                                <th scope="col"><?php echo esc_html__('Guru', 'elearning-vd'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ([] === $elvd_siswa_jadwal) { ?>
                                                <tr>
                                                    <td colspan="4"><?php echo esc_html__('Belum ada jadwal untuk kelasmu.', 'elearning-vd'); ?></td>
                                                </tr>
                                            <?php } ?>
                                            <?php foreach ($elvd_siswa_jadwal as $elvd_jadwal) { ?>
                                                <tr>
                                                    <td><?php echo esc_html($elvd_jadwal->hari); ?></td>
                                                    <td><?php echo esc_html($elvd_jadwal->jam_mulai . ' - ' . $elvd_jadwal->jam_selesai); ?></td>
                                                    <td><?php echo esc_html($elvd_jadwal->mata_pelajaran ?? '-'); ?></td>
                                                    <td><?php echo esc_html($elvd_jadwal->guru ?? '-'); ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="elvd-panel">
                                <div class="elvd-panel-heading">
                                    <div>
                                        <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Terbaru', 'elearning-vd'); ?></p>
                                        <h2><?php echo esc_html__('Tugas Terbaru', 'elearning-vd'); ?></h2>
                                    </div>
                                </div>
                                <div class="elvd-activity-list">
                                    <?php if ([] === $elvd_siswa_tugas) { ?>
                                        <div class="elvd-activity-item"><span><?php echo esc_html__('Belum ada tugas.', 'elearning-vd'); ?></span></div>
                                    <?php } ?>
                                    <?php foreach ($elvd_siswa_tugas as $elvd_tugas) {
                                        $elvd_deadline = (string) get_post_meta($elvd_tugas->ID, 'elvd_deadline', true);
                                    ?>
                                        <div class="elvd-activity-item">
                                            <span><?php echo esc_html($elvd_tugas->post_title); ?></span>
                                            <strong><?php echo esc_html('' !== $elvd_deadline ? date_i18n(get_option('date_format'), strtotime($elvd_deadline)) : '&ndash;'); ?></strong>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="elvd-panel">
                                <div class="elvd-panel-heading">
                                    <div>
                                        <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Terbaru', 'elearning-vd'); ?></p>
                                        <h2><?php echo esc_html__('Materi Terbaru', 'elearning-vd'); ?></h2>
                                    </div>
                                </div>
                                <div class="elvd-activity-list">
                                    <?php if ([] === $elvd_siswa_materi) { ?>
                                        <div class="elvd-activity-item"><span><?php echo esc_html__('Belum ada materi.', 'elearning-vd'); ?></span></div>
                                    <?php } ?>
                                    <?php foreach ($elvd_siswa_materi as $elvd_materi) { ?>
                                        <div class="elvd-activity-item">
                                            <span><?php echo esc_html($elvd_materi->post_title); ?></span>
                                            <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($elvd_materi->post_date))); ?></strong>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="elvd-panel">
                                <div class="elvd-panel-heading">
                                    <div>
                                        <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Terbaru', 'elearning-vd'); ?></p>
                                        <h2><?php echo esc_html__('Quiz Terbaru', 'elearning-vd'); ?></h2>
                                    </div>
                                </div>
                                <div class="elvd-activity-list">
                                    <?php if ([] === $elvd_siswa_quiz) { ?>
                                        <div class="elvd-activity-item"><span><?php echo esc_html__('Belum ada quiz.', 'elearning-vd'); ?></span></div>
                                    <?php } ?>
                                    <?php foreach ($elvd_siswa_quiz as $elvd_quiz) {
                                        $elvd_quiz_tipe = (string) get_post_meta($elvd_quiz->ID, 'elvd_quiz_tipe', true);
                                    ?>
                                        <div class="elvd-activity-item">
                                            <span><?php echo esc_html($elvd_quiz->post_title); ?></span>
                                            <strong><?php echo esc_html('' !== $elvd_quiz_tipe ? ucfirst($elvd_quiz_tipe) : date_i18n(get_option('date_format'), strtotime($elvd_quiz->post_date))); ?></strong>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } elseif ('' === $route_page && 'guru' === $elvd_current_role) { ?>
                    <div x-show="active === 'dashboard'">
                        <div class="elvd-hero">
                            <div class="elvd-hero-copy">
                                <p class="elvd-eyebrow mb-2"><?php echo esc_html__('Dashboard Guru', 'elearning-vd'); ?></p>
                                <h3><?php echo esc_html__('Halo, selamat datang di ruang mengajarmu.', 'elearning-vd'); ?></h3>
                                <p><?php echo esc_html__('Pantau jadwal mengajar serta konten tugas, materi, dan quiz yang kamu buat.', 'elearning-vd'); ?></p>
                            </div>
                        </div>

                        <div class="elvd-metric-grid">
                            <?php foreach (array_slice($elvd_guru_metrics, 0, 4) as $metric) { ?>
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
                                        <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Mengajar', 'elearning-vd'); ?></p>
                                        <h2><?php echo esc_html__('Jadwal Mengajar', 'elearning-vd'); ?></h2>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0 elvd-table">
                                        <thead>
                                            <tr>
                                                <th scope="col"><?php echo esc_html__('Hari', 'elearning-vd'); ?></th>
                                                <th scope="col"><?php echo esc_html__('Jam', 'elearning-vd'); ?></th>
                                                <th scope="col"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></th>
                                                <th scope="col"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ([] === $elvd_guru_jadwal) { ?>
                                                <tr>
                                                    <td colspan="4"><?php echo esc_html__('Belum ada jadwal mengajar.', 'elearning-vd'); ?></td>
                                                </tr>
                                            <?php } ?>
                                            <?php foreach ($elvd_guru_jadwal as $elvd_jadwal) { ?>
                                                <tr>
                                                    <td><?php echo esc_html($elvd_jadwal->hari); ?></td>
                                                    <td><?php echo esc_html($elvd_jadwal->jam_mulai . ' - ' . $elvd_jadwal->jam_selesai); ?></td>
                                                    <td><?php echo esc_html($elvd_jadwal->mata_pelajaran ?? '-'); ?></td>
                                                    <td><?php echo esc_html($elvd_jadwal->kelas ?? '-'); ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="elvd-panel elvd-panel-dark">
                                <div class="elvd-panel-heading">
                                    <div>
                                        <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Mengajar', 'elearning-vd'); ?></p>
                                        <h2><?php echo esc_html__('Daftar Mapel yang Diajar', 'elearning-vd'); ?></h2>
                                    </div>
                                </div>
                                <div class="elvd-activity-list">
                                    <?php if ([] === $elvd_guru_mapel) { ?>
                                        <div class="elvd-activity-item"><span><?php echo esc_html__('Belum ada mata pelajaran yang diajar.', 'elearning-vd'); ?></span></div>
                                    <?php } ?>
                                    <?php foreach ($elvd_guru_mapel as $elvd_mapel) { ?>
                                        <div class="elvd-activity-item">
                                            <span><?php echo esc_html($elvd_mapel['nama'] ?? '-'); ?></span>
                                            <strong><?php echo esc_html($elvd_mapel['kode'] ?? __('Mapel', 'elearning-vd')); ?></strong>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <div class="elvd-dashboard-grid">
                            <div class="elvd-panel">
                                <div class="elvd-panel-heading">
                                    <div>
                                        <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Terbaru', 'elearning-vd'); ?></p>
                                        <h2><?php echo esc_html__('Tugas Saya', 'elearning-vd'); ?></h2>
                                    </div>
                                </div>
                                <div class="elvd-activity-list">
                                    <?php if ([] === $elvd_guru_tugas) { ?>
                                        <div class="elvd-activity-item"><span><?php echo esc_html__('Belum ada tugas.', 'elearning-vd'); ?></span></div>
                                    <?php } ?>
                                    <?php foreach ($elvd_guru_tugas as $elvd_tugas) {
                                        $elvd_deadline = (string) get_post_meta($elvd_tugas->ID, 'elvd_deadline', true);
                                    ?>
                                        <div class="elvd-activity-item">
                                            <span><?php echo esc_html($elvd_tugas->post_title); ?></span>
                                            <strong><?php echo esc_html('' !== $elvd_deadline ? date_i18n(get_option('date_format'), strtotime($elvd_deadline)) : '&ndash;'); ?></strong>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="elvd-panel">
                                <div class="elvd-panel-heading">
                                    <div>
                                        <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Terbaru', 'elearning-vd'); ?></p>
                                        <h2><?php echo esc_html__('Materi Saya', 'elearning-vd'); ?></h2>
                                    </div>
                                </div>
                                <div class="elvd-activity-list">
                                    <?php if ([] === $elvd_guru_materi) { ?>
                                        <div class="elvd-activity-item"><span><?php echo esc_html__('Belum ada materi.', 'elearning-vd'); ?></span></div>
                                    <?php } ?>
                                    <?php foreach ($elvd_guru_materi as $elvd_materi) { ?>
                                        <div class="elvd-activity-item">
                                            <span><?php echo esc_html($elvd_materi->post_title); ?></span>
                                            <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($elvd_materi->post_date))); ?></strong>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="elvd-panel">
                                <div class="elvd-panel-heading">
                                    <div>
                                        <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Terbaru', 'elearning-vd'); ?></p>
                                        <h2><?php echo esc_html__('Quiz Saya', 'elearning-vd'); ?></h2>
                                    </div>
                                </div>
                                <div class="elvd-activity-list">
                                    <?php if ([] === $elvd_guru_quiz) { ?>
                                        <div class="elvd-activity-item"><span><?php echo esc_html__('Belum ada quiz.', 'elearning-vd'); ?></span></div>
                                    <?php } ?>
                                    <?php foreach ($elvd_guru_quiz as $elvd_quiz) {
                                        $elvd_quiz_tipe = (string) get_post_meta($elvd_quiz->ID, 'elvd_quiz_tipe', true);
                                    ?>
                                        <div class="elvd-activity-item">
                                            <span><?php echo esc_html($elvd_quiz->post_title); ?></span>
                                            <strong><?php echo esc_html('' !== $elvd_quiz_tipe ? ucfirst($elvd_quiz_tipe) : date_i18n(get_option('date_format'), strtotime($elvd_quiz->post_date))); ?></strong>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } elseif ('' === $route_page) { ?>
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
                                        <p class="elvd-eyebrow mb-1"><?php echo esc_html__('Jadwal Pelajaran', 'elearning-vd'); ?></p>
                                        <h2><?php echo esc_html__('Ringkasan Jadwal Pelajaran', 'elearning-vd'); ?></h2>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0 elvd-table">
                                        <thead>
                                            <tr>
                                                <th scope="col"><?php echo esc_html__('Hari', 'elearning-vd'); ?></th>
                                                <th scope="col"><?php echo esc_html__('Jam', 'elearning-vd'); ?></th>
                                                <th scope="col"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></th>
                                                <th scope="col"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $elvd_hari_ini = [
                                                '0' => 'Minggu',
                                                '1' => 'Senin',
                                                '2' => 'Selasa',
                                                '3' => 'Rabu',
                                                '4' => 'Kamis',
                                                '5' => 'Jumat',
                                                '6' => 'Sabtu',
                                            ][(string) current_time('w')] ?? '';

                                            $elvd_jadwal_all = $wpdb->get_results(
                                                $wpdb->prepare(
                                                    "SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, k.nama AS kelas, mp.nama AS mata_pelajaran
                                                     FROM %1\$s j
                                                     LEFT JOIN %2\$s k ON k.id = j.kelas_id
                                                     LEFT JOIN %3\$s mp ON mp.id = j.mata_pelajaran_id
                                                     WHERE j.hari = '%4\$s'
                                                     ORDER BY j.jam_mulai",
                                                    elvd_table_name('elvd_jadwal_pelajaran'),
                                                    elvd_table_name('elvd_kelas'),
                                                    elvd_table_name('elvd_mata_pelajaran'),
                                                    $elvd_hari_ini
                                                )
                                            );
                                            if ([] === $elvd_jadwal_all) {
                                            ?>
                                                <tr>
                                                    <td colspan="4"><?php echo esc_html__('Belum ada jadwal pelajaran hari ini.', 'elearning-vd'); ?></td>
                                                </tr>
                                            <?php
                                            } else {
                                            ?>
                                                <?php foreach ($elvd_jadwal_all as $elvd_jadwal) { ?>
                                                    <tr>
                                                        <td><?php echo esc_html($elvd_jadwal->hari); ?></td>
                                                        <td><?php echo esc_html($elvd_jadwal->jam_mulai . ' - ' . $elvd_jadwal->jam_selesai); ?></td>
                                                        <td><?php echo esc_html($elvd_jadwal->mata_pelajaran ?? '-'); ?></td>
                                                        <td><?php echo esc_html($elvd_jadwal->kelas ?? '-'); ?></td>
                                                    </tr>
                                                <?php } ?>
                                            <?php
                                            }
                                            ?>
                                        </tbody>
                                    </table>
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
                <?php } ?>

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

                <div class="elvd-table-panel" x-show="!['dashboard', 'tahun-ajaran', 'kelas', 'mata-pelajaran', 'jadwal-pelajaran', 'jadwal-pelajaran-siswa', 'guru', 'siswa', 'siswa-profil', 'guru-profil', 'tugas', 'materi', 'quiz', 'quiz-form', 'quiz-workspace', 'quiz-answer', 'tugas-answer'].includes(active)">
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
<?php }
