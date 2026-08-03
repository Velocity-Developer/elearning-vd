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
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Pengaturan Elearning', ELVD::TEXT_DOMAIN); ?></h1>

        <?php if (isset($_GET['elvd_app_page']) && 'created' === sanitize_key((string) $_GET['elvd_app_page'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Halaman App Elearning berhasil dibuat atau diperbarui.', ELVD::TEXT_DOMAIN); ?></p>
            </div>
        <?php elseif (isset($_GET['elvd_app_page']) && 'failed' === sanitize_key((string) $_GET['elvd_app_page'])) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php esc_html_e('Halaman App Elearning gagal dibuat.', ELVD::TEXT_DOMAIN); ?></p>
            </div>
        <?php endif; ?>

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
    </div>
    <?php
}
