<?php

defined('ABSPATH') || exit;

/**
 * @param array{
 *     option_name: string,
 *     option_group: string,
 *     fields: array<string, array<string, mixed>>,
 *     field_types: array<string, string>,
 *     field_targets: array<string, string>,
 *     title: string,
 *     description: string,
 *     table_id: string
 * } $args
 */
function elvd_render_profile_fields_settings(array $args): void
{
    $option_name = (string) $args['option_name'];
    $option_group = (string) $args['option_group'];
    $fields = $args['fields'];
    $field_types = $args['field_types'];
    $field_targets = $args['field_targets'];
    $title = (string) $args['title'];
    $description = (string) $args['description'];
    $table_id = (string) $args['table_id'];
?>
    <form method="post" action="options.php">
        <?php settings_fields($option_group); ?>

        <h2><?php echo esc_html($title); ?></h2>
        <p><?php echo esc_html($description); ?></p>

        <div class="elvd-profile-fields-wrap">
            <table class="widefat striped elvd-profile-fields" id="<?php echo esc_attr($table_id); ?>">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Key', ELVD::TEXT_DOMAIN); ?></th>
                        <th><?php esc_html_e('Label', ELVD::TEXT_DOMAIN); ?></th>
                        <th><?php esc_html_e('Tipe', ELVD::TEXT_DOMAIN); ?></th>
                        <th><?php esc_html_e('Target', ELVD::TEXT_DOMAIN); ?></th>
                        <th><?php esc_html_e('Wajib', ELVD::TEXT_DOMAIN); ?></th>
                        <th><?php esc_html_e('Aksi', ELVD::TEXT_DOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $field_index = 0; ?>
                    <?php foreach ($fields as $field_key => $field) : ?>
                        <?php
                        if (! is_array($field)) {
                            continue;
                        }

                        $field_target = (string) ($field['target'] ?? 'meta');
                        ?>
                        <tr>
                            <td>
                                <input
                                    type="text"
                                    name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr((string) $field_index); ?>][key]"
                                    value="<?php echo esc_attr((string) $field_key); ?>"
                                    class="regular-text"
                                    required>
                            </td>
                            <td>
                                <input
                                    type="text"
                                    name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr((string) $field_index); ?>][label]"
                                    value="<?php echo esc_attr((string) ($field['label'] ?? $field_key)); ?>"
                                    class="regular-text"
                                    required>
                            </td>
                            <td>
                                <select name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr((string) $field_index); ?>][type]">
                                    <?php foreach ($field_types as $type_key => $type_label) : ?>
                                        <option value="<?php echo esc_attr($type_key); ?>" <?php selected((string) ($field['type'] ?? 'text'), $type_key); ?>>
                                            <?php echo esc_html($type_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr((string) $field_index); ?>][target]">
                                    <?php foreach ($field_targets as $target_key => $target_label) : ?>
                                        <option value="<?php echo esc_attr($target_key); ?>" <?php selected($field_target, $target_key); ?>>
                                            <?php echo esc_html($target_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr((string) $field_index); ?>][required]"
                                        value="1"
                                        <?php checked(! empty($field['required'])); ?>>
                                    <?php esc_html_e('Ya', ELVD::TEXT_DOMAIN); ?>
                                </label>
                            </td>
                            <td>
                                <button type="button" class="button elvd-remove-profile-field">
                                    <?php esc_html_e('Hapus', ELVD::TEXT_DOMAIN); ?>
                                </button>
                            </td>
                        </tr>
                        <?php ++$field_index; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p>
            <button
                type="button"
                class="button elvd-add-profile-field"
                data-field-option="<?php echo esc_attr($option_name); ?>"
                data-target="#<?php echo esc_attr($table_id); ?>">
                <?php esc_html_e('Tambah Field', ELVD::TEXT_DOMAIN); ?>
            </button>
        </p>

        <?php submit_button(__('Simpan Profile Fields', ELVD::TEXT_DOMAIN)); ?>
    </form>
<?php
}

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
    $active_tab = in_array($active_tab, ['umum', 'profil-siswa', 'profil-guru', 'seeder'], true) ? $active_tab : 'umum';
    $siswa_profile_fields = get_option(ELVD::OPTION_SISWA_PROFILE_FIELDS, elvd_default_siswa_profile_fields());
    $siswa_profile_fields = is_array($siswa_profile_fields) && [] !== $siswa_profile_fields
        ? $siswa_profile_fields
        : elvd_default_siswa_profile_fields();
    $guru_profile_fields = get_option(ELVD::OPTION_GURU_PROFILE_FIELDS, elvd_default_guru_profile_fields());
    $guru_profile_fields = is_array($guru_profile_fields) && [] !== $guru_profile_fields
        ? $guru_profile_fields
        : elvd_default_guru_profile_fields();
    $field_types = [
        'text' => __('Text', ELVD::TEXT_DOMAIN),
        'email' => __('Email', ELVD::TEXT_DOMAIN),
        'number' => __('Angka', ELVD::TEXT_DOMAIN),
        'date' => __('Tanggal', ELVD::TEXT_DOMAIN),
        'tel' => __('Telepon', ELVD::TEXT_DOMAIN),
        'textarea' => __('Textarea', ELVD::TEXT_DOMAIN),
    ];
    $field_targets = [
        'meta' => __('User Meta', ELVD::TEXT_DOMAIN),
        'display_name' => __('Nama Tampilan User', ELVD::TEXT_DOMAIN),
        'user_email' => __('Email User', ELVD::TEXT_DOMAIN),
    ];
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
    <div class="wrap elvd-admin-settings">
        <h1><?php esc_html_e('Pengaturan Elearning', ELVD::TEXT_DOMAIN); ?></h1>

        <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Tab Pengaturan Elearning', ELVD::TEXT_DOMAIN); ?>">
            <a
                href="<?php echo esc_url(add_query_arg(['page' => ELVD::SETTINGS_MENU_SLUG, 'tab' => 'umum'], admin_url('admin.php'))); ?>"
                class="nav-tab <?php echo 'umum' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Umum', ELVD::TEXT_DOMAIN); ?>
            </a>
            <a
                href="<?php echo esc_url(add_query_arg(['page' => ELVD::SETTINGS_MENU_SLUG, 'tab' => 'profil-siswa'], admin_url('admin.php'))); ?>"
                class="nav-tab <?php echo 'profil-siswa' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Profil Siswa', ELVD::TEXT_DOMAIN); ?>
            </a>
            <a
                href="<?php echo esc_url(add_query_arg(['page' => ELVD::SETTINGS_MENU_SLUG, 'tab' => 'profil-guru'], admin_url('admin.php'))); ?>"
                class="nav-tab <?php echo 'profil-guru' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Profil Guru', ELVD::TEXT_DOMAIN); ?>
            </a>
            <a
                href="<?php echo esc_url(add_query_arg(['page' => ELVD::SETTINGS_MENU_SLUG, 'tab' => 'seeder'], admin_url('admin.php'))); ?>"
                class="nav-tab <?php echo 'seeder' === $active_tab ? 'nav-tab-active' : ''; ?>">
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
                                    class="regular-text">
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
                                    value="<?php echo esc_attr((string) $logo_id); ?>">

                                <p>
                                    <img
                                        id="elvd-school-logo-preview"
                                        src="<?php echo esc_url((string) $logo_url); ?>"
                                        alt="<?php esc_attr_e('Logo Sekolah', ELVD::TEXT_DOMAIN); ?>"
                                        class="<?php echo $logo_url ? '' : 'hidden'; ?>"
                                        style="max-width: 120px; height: auto; margin-bottom: 12px;">
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
                                        class="button">
                                        <?php esc_html_e('Buat Halaman App', ELVD::TEXT_DOMAIN); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Simpan Pengaturan', ELVD::TEXT_DOMAIN)); ?>
            </form>
        <?php elseif ('profil-siswa' === $active_tab) : ?>
            <?php
            elvd_render_profile_fields_settings([
                'option_name' => ELVD::OPTION_SISWA_PROFILE_FIELDS,
                'option_group' => ELVD::OPTION_GROUP_SISWA_PROFILE,
                'fields' => $siswa_profile_fields,
                'field_types' => $field_types,
                'field_targets' => $field_targets,
                'title' => __('Profile Fields Siswa', ELVD::TEXT_DOMAIN),
                'description' => __('Key di bawah ini dipakai sebagai nama field pada form profil siswa dan disimpan sebagai meta user dengan prefix elvd_.', ELVD::TEXT_DOMAIN),
                'table_id' => 'elvd-siswa-profile-fields',
            ]);
            ?>
        <?php elseif ('profil-guru' === $active_tab) : ?>
            <?php
            elvd_render_profile_fields_settings([
                'option_name' => ELVD::OPTION_GURU_PROFILE_FIELDS,
                'option_group' => ELVD::OPTION_GROUP_GURU_PROFILE,
                'fields' => $guru_profile_fields,
                'field_types' => $field_types,
                'field_targets' => $field_targets,
                'title' => __('Profile Fields Guru', ELVD::TEXT_DOMAIN),
                'description' => __('Key di bawah ini dipakai sebagai nama field pada form profil guru dan disimpan sebagai meta user dengan prefix elvd_.', ELVD::TEXT_DOMAIN),
                'table_id' => 'elvd-guru-profile-fields',
            ]);
            ?>
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
                                    checked>
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
