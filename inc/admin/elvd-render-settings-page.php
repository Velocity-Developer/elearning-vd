<?php

defined('ABSPATH') || exit;

function elvd_render_settings_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $school_name = (string) get_option(ELVD::OPTION_SCHOOL_NAME, '');
    $logo_id = absint(get_option(ELVD::OPTION_SCHOOL_LOGO_ID, 0));
    $logo_url = $logo_id > 0 ? wp_get_attachment_image_url($logo_id, 'thumbnail') : '';
    $elearning_page_id = absint(get_option(ELVD::OPTION_ELEARNING_PAGE_ID, 0));
    $active_tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'umum';
    $active_tab = in_array($active_tab, ['umum', 'seeder'], true) ? $active_tab : 'umum';
    $seed_items = [
        'users' => __('User Guru dan Siswa', ELVD::TEXT_DOMAIN),
        'tahun_ajaran' => __('Tahun Ajaran', ELVD::TEXT_DOMAIN),
        'kelas' => __('Kelas', ELVD::TEXT_DOMAIN),
        'mata_pelajaran' => __('Mata Pelajaran', ELVD::TEXT_DOMAIN),
        'jadwal_pelajaran' => __('Jadwal Pelajaran', ELVD::TEXT_DOMAIN),
        'materi' => __('Materi', ELVD::TEXT_DOMAIN),
        'tugas' => __('Tugas', ELVD::TEXT_DOMAIN),
        'quiz' => __('Quiz', ELVD::TEXT_DOMAIN),
    ];
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Pengaturan Elearning', ELVD::TEXT_DOMAIN); ?></h1>

        <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Tab Pengaturan Elearning', ELVD::TEXT_DOMAIN); ?>">
            <a
                href="<?php echo esc_url(add_query_arg(['page' => ELVD::SETTINGS_MENU_SLUG, 'tab' => 'umum'], admin_url('admin.php'))); ?>"
                class="nav-tab <?php echo 'umum' === $active_tab ? 'nav-tab-active' : ''; ?>"
            >
                <?php esc_html_e('Umum', ELVD::TEXT_DOMAIN); ?>
            </a>
            <a
                href="<?php echo esc_url(add_query_arg(['page' => ELVD::SETTINGS_MENU_SLUG, 'tab' => 'seeder'], admin_url('admin.php'))); ?>"
                class="nav-tab <?php echo 'seeder' === $active_tab ? 'nav-tab-active' : ''; ?>"
            >
                <?php esc_html_e('Seeder', ELVD::TEXT_DOMAIN); ?>
            </a>
        </nav>

        <?php if (isset($_GET['elvd_app_page']) && 'created' === sanitize_key((string) $_GET['elvd_app_page'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Halaman App Elearning berhasil dibuat atau diperbarui.', ELVD::TEXT_DOMAIN); ?></p>
            </div>
        <?php elseif (isset($_GET['elvd_app_page']) && 'failed' === sanitize_key((string) $_GET['elvd_app_page'])) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php esc_html_e('Halaman App Elearning gagal dibuat.', ELVD::TEXT_DOMAIN); ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['elvd_seed']) && 'success' === sanitize_key((string) $_GET['elvd_seed'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Seeder berhasil dijalankan.', ELVD::TEXT_DOMAIN); ?></p>
            </div>
        <?php elseif (isset($_GET['elvd_seed']) && 'partial' === sanitize_key((string) $_GET['elvd_seed'])) : ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php esc_html_e('Seeder selesai dijalankan, tetapi sebagian data mungkin belum berhasil dibuat.', ELVD::TEXT_DOMAIN); ?></p>
            </div>
        <?php elseif (isset($_GET['elvd_seed']) && 'failed' === sanitize_key((string) $_GET['elvd_seed'])) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php esc_html_e('Seeder gagal dijalankan.', ELVD::TEXT_DOMAIN); ?></p>
            </div>
        <?php elseif (isset($_GET['elvd_seed']) && 'empty' === sanitize_key((string) $_GET['elvd_seed'])) : ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php esc_html_e('Pilih minimal satu data seeder untuk dijalankan.', ELVD::TEXT_DOMAIN); ?></p>
            </div>
        <?php endif; ?>

        <?php if ('umum' === $active_tab) : ?>
        <form method="post" action="options.php">
            <?php settings_fields(ELVD::OPTION_GROUP); ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="elvd-school-name"><?php esc_html_e('Nama Sekolah', ELVD::TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="elvd-school-name"
                                name="<?php echo esc_attr(ELVD::OPTION_SCHOOL_NAME); ?>"
                                value="<?php echo esc_attr($school_name); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Logo Sekolah', ELVD::TEXT_DOMAIN); ?>
                        </th>
                        <td>
                            <input
                                type="hidden"
                                id="elvd-school-logo-id"
                                name="<?php echo esc_attr(ELVD::OPTION_SCHOOL_LOGO_ID); ?>"
                                value="<?php echo esc_attr((string) $logo_id); ?>"
                            >

                            <p>
                                <img
                                    id="elvd-school-logo-preview"
                                    src="<?php echo esc_url((string) $logo_url); ?>"
                                    alt="<?php esc_attr_e('Logo Sekolah', ELVD::TEXT_DOMAIN); ?>"
                                    class="<?php echo $logo_url ? '' : 'hidden'; ?>"
                                    style="max-width: 120px; height: auto; margin-bottom: 12px;"
                                >
                            </p>

                            <button type="button" class="button" id="elvd-select-school-logo">
                                <?php esc_html_e('Pilih Logo', ELVD::TEXT_DOMAIN); ?>
                            </button>
                            <button type="button" class="button <?php echo $logo_url ? '' : 'hidden'; ?>" id="elvd-remove-school-logo">
                                <?php esc_html_e('Hapus Logo', ELVD::TEXT_DOMAIN); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="elvd-elearning-page-id"><?php esc_html_e('Halaman Elearning', ELVD::TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_pages(
                                [
                                    'name' => ELVD::OPTION_ELEARNING_PAGE_ID,
                                    'id' => 'elvd-elearning-page-id',
                                    'selected' => $elearning_page_id,
                                    'show_option_none' => __('Pilih halaman', ELVD::TEXT_DOMAIN),
                                    'option_none_value' => '0',
                                ]
                            );
                            ?>
                            <p>
                                <a
                                    href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=elvd_create_app_page'), 'elvd_create_app_page')); ?>"
                                    class="button"
                                >
                                    <?php esc_html_e('Buat Halaman App', ELVD::TEXT_DOMAIN); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(__('Simpan Pengaturan', ELVD::TEXT_DOMAIN)); ?>
        </form>
        <?php else : ?>
            <div class="card" style="max-width: 720px; margin-top: 20px;">
                <h2><?php esc_html_e('Jalankan Seeder', ELVD::TEXT_DOMAIN); ?></h2>
                <p><?php esc_html_e('Pilih data demo yang ingin dibuat. Aman dijalankan berulang karena data yang sama tidak akan dibuat dua kali.', ELVD::TEXT_DOMAIN); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="elvd_seed_data">
                    <?php wp_nonce_field('elvd_seed_data'); ?>
                    <fieldset style="margin: 16px 0;">
                        <legend class="screen-reader-text"><?php esc_html_e('Pilihan Seeder', ELVD::TEXT_DOMAIN); ?></legend>
                        <?php foreach ($seed_items as $seed_key => $seed_label) : ?>
                            <label style="display: block; margin-bottom: 8px;">
                                <input
                                    type="checkbox"
                                    name="elvd_seed_items[]"
                                    value="<?php echo esc_attr($seed_key); ?>"
                                    checked
                                >
                                <?php echo esc_html($seed_label); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php submit_button(__('Jalankan Seeder', ELVD::TEXT_DOMAIN), 'primary', 'submit', false); ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
