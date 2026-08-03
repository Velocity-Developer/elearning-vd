<?php

defined('ABSPATH') || exit;

function elvd_register_admin_menu(): void
{
    add_menu_page(
        __('Elearning Dashboard', ELVD::TEXT_DOMAIN),
        __('Elearning', ELVD::TEXT_DOMAIN),
        'manage_options',
        ELVD::ADMIN_MENU_SLUG,
        'elvd_render_dashboard_page',
        'dashicons-welcome-learn-more',
        56
    );

    add_submenu_page(
        ELVD::ADMIN_MENU_SLUG,
        __('Elearning Dashboard', ELVD::TEXT_DOMAIN),
        __('Dashboard', ELVD::TEXT_DOMAIN),
        'manage_options',
        ELVD::ADMIN_MENU_SLUG,
        'elvd_render_dashboard_page'
    );

    add_submenu_page(
        ELVD::ADMIN_MENU_SLUG,
        __('Pengaturan Elearning', ELVD::TEXT_DOMAIN),
        __('Pengaturan Elearning', ELVD::TEXT_DOMAIN),
        'manage_options',
        ELVD::SETTINGS_MENU_SLUG,
        'elvd_render_settings_page'
    );
}

function elvd_render_dashboard_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $summary = elvd_dashboard_summary();
    $max_value = max(1, ...array_column($summary, 'value'));

    wp_enqueue_script(
        'elvd-chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js',
        [],
        '4.4.8',
        true
    );

    wp_register_script(
        'elvd-admin-dashboard',
        '',
        ['elvd-chartjs'],
        ELVD_PLUGIN_VERSION,
        true
    );

    wp_enqueue_script('elvd-admin-dashboard');
    wp_localize_script(
        'elvd-admin-dashboard',
        'elvdAdminDashboardChartData',
        [
            'labels' => array_map(static fn (array $item): string => (string) $item['label'], $summary),
            'values' => array_map(static fn (array $item): int => (int) $item['value'], $summary),
        ]
    );
    wp_add_inline_script('elvd-admin-dashboard', elvd_admin_dashboard_chart_script());
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Elearning Dashboard', ELVD::TEXT_DOMAIN); ?></h1>

        <style>
            .elvd-dashboard-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 16px;
                margin: 20px 0;
            }

            .elvd-dashboard-card,
            .elvd-dashboard-chart {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                padding: 16px;
            }

            .elvd-dashboard-card strong {
                display: block;
                color: #1d2327;
                font-size: 28px;
                line-height: 1.2;
            }

            .elvd-dashboard-card span,
            .elvd-chart-label {
                color: #50575e;
            }

            .elvd-admin-chart-wrap {
                height: 360px;
                margin-top: 16px;
                position: relative;
                width: 100%;
            }

            .elvd-admin-chart-wrap canvas {
                display: block;
                max-height: 100%;
                width: 100% !important;
            }

            .elvd-dashboard-chart.has-chart .elvd-chart-fallback {
                display: none;
            }

            .elvd-chart-row {
                display: grid;
                grid-template-columns: 150px 1fr 56px;
                gap: 12px;
                align-items: center;
                margin: 12px 0;
            }

            .elvd-chart-track {
                background: #f0f0f1;
                border-radius: 4px;
                height: 22px;
                overflow: hidden;
            }

            .elvd-chart-bar {
                background: #2271b1;
                height: 100%;
                min-width: 4px;
            }
        </style>

        <div class="elvd-dashboard-grid">
            <?php foreach ($summary as $item) : ?>
                <div class="elvd-dashboard-card">
                    <strong><?php echo esc_html(number_format_i18n((int) $item['value'])); ?></strong>
                    <span><?php echo esc_html($item['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="elvd-dashboard-chart">
            <h2><?php esc_html_e('Ringkasan Data Elearning', ELVD::TEXT_DOMAIN); ?></h2>

            <div class="elvd-admin-chart-wrap">
                <canvas id="elvdAdminDashboardChart" aria-label="<?php echo esc_attr__('Chart ringkasan data elearning', ELVD::TEXT_DOMAIN); ?>" role="img"></canvas>
            </div>

            <div class="elvd-chart-fallback">
                <?php foreach ($summary as $item) : ?>
                    <?php $width = ((int) $item['value'] / $max_value) * 100; ?>
                    <div class="elvd-chart-row">
                        <div class="elvd-chart-label"><?php echo esc_html($item['label']); ?></div>
                        <div class="elvd-chart-track" aria-hidden="true">
                            <div class="elvd-chart-bar" style="width: <?php echo esc_attr((string) $width); ?>%;"></div>
                        </div>
                        <div><?php echo esc_html(number_format_i18n((int) $item['value'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function elvd_admin_dashboard_chart_script(): string
{
    return <<<'JS'
(function () {
    function createAdminDashboardChart() {
        var canvas = document.getElementById('elvdAdminDashboardChart');
        var chartData = window.elvdAdminDashboardChartData;

        if (!canvas || !window.Chart || !chartData || canvas.dataset.elvdChartReady === 'true') {
            return;
        }

        var labels = Array.isArray(chartData.labels) ? chartData.labels : [];
        var values = Array.isArray(chartData.values)
            ? chartData.values.map(function (value) { return Number(value) || 0; })
            : [];

        canvas.dataset.elvdChartReady = 'true';

        if (canvas.closest) {
            var panel = canvas.closest('.elvd-dashboard-chart');

            if (panel) {
                panel.classList.add('has-chart');
            }
        }

        new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: '#024ad8',
                    borderRadius: 4,
                    borderSkipped: false,
                    barThickness: 28,
                    maxBarThickness: 36
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1a1a1a',
                        bodyColor: '#ffffff',
                        displayColors: false,
                        padding: 12,
                        titleColor: '#ffffff'
                    }
                },
                scales: {
                    x: {
                        border: {
                            display: false
                        },
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#3d3d3d',
                            maxRotation: 0,
                            minRotation: 0
                        }
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false
                        },
                        grid: {
                            color: '#e8e8e8'
                        },
                        ticks: {
                            color: '#636363',
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createAdminDashboardChart);
        return;
    }

    createAdminDashboardChart();
})();
JS;
}

/**
 * @return array<int, array{label: string, value: int}>
 */
function elvd_dashboard_summary(): array
{
    $user_counts = count_users();
    $roles = $user_counts['avail_roles'] ?? [];

    return [
        [
            'label' => __('Siswa', ELVD::TEXT_DOMAIN),
            'value' => $roles['siswa'] ?? 0,
        ],
        [
            'label' => __('Guru', ELVD::TEXT_DOMAIN),
            'value' => $roles['guru'] ?? 0,
        ],
        [
            'label' => __('Tugas', ELVD::TEXT_DOMAIN),
            'value' => (int) wp_count_posts('elvd_tugas')->publish,
        ],
        [
            'label' => __('Materi', ELVD::TEXT_DOMAIN),
            'value' => (int) wp_count_posts('elvd_materi')->publish,
        ],
        [
            'label' => __('Quiz', ELVD::TEXT_DOMAIN),
            'value' => (int) wp_count_posts('elvd_quiz')->publish,
        ],
        [
            'label' => __('Tahun Ajaran', ELVD::TEXT_DOMAIN),
            'value' => elvd_dashboard_table_count('elvd_tahun_ajaran'),
        ],
        [
            'label' => __('Kelas', ELVD::TEXT_DOMAIN),
            'value' => elvd_dashboard_table_count('elvd_kelas'),
        ],
        [
            'label' => __('Mata Pelajaran', ELVD::TEXT_DOMAIN),
            'value' => elvd_dashboard_table_count('elvd_mata_pelajaran'),
        ],
    ];
}

function elvd_dashboard_table_count(string $table): int
{
    global $wpdb;

    $table_name = elvd_table_name($table);

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
}
