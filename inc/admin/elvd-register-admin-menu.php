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
                border-radius: 4px;
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
    <?php
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
