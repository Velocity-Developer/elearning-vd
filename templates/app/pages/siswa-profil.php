<?php

defined('ABSPATH') || exit;

global $wpdb;

$elvd_siswa_id = absint((int) get_query_var('elvd_siswa_id', 0));
$elvd_profile_tab = sanitize_key((string) get_query_var('elvd_siswa_tab', ''));
$elvd_profile_tab = in_array($elvd_profile_tab, ['profil', 'edit', 'foto'], true) ? $elvd_profile_tab : 'profil';
$elvd_siswa_base_url = untrailingslashit(ELVD::app_route()) . '/siswa-profil';
$elvd_siswa_back_url = untrailingslashit(ELVD::app_route()) . '/siswa/';
$elvd_siswa_notice = '';

$elvd_profile_fields = class_exists(\ElearningVD\Siswa::class) ? \ElearningVD\Siswa::fields() : [];

if ($elvd_siswa_id > 0) {
    $elvd_siswa = get_userdata($elvd_siswa_id);
    $elvd_siswa_valid = $elvd_siswa instanceof WP_User && in_array('siswa', (array) $elvd_siswa->roles, true);
    $elvd_can_edit = $elvd_siswa_valid && (
        current_user_can('edit_user', $elvd_siswa_id)
        || current_user_can('manage_options')
        || $elvd_siswa->ID === get_current_user_id()
    );
} else {
    $elvd_siswa = null;
    $elvd_siswa_valid = false;
    $elvd_can_edit = false;
}

if ('POST' === strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) && isset($_POST['elvd_siswa_action'])) {
    $elvd_siswa_action = sanitize_key((string) wp_unslash($_POST['elvd_siswa_action']));

    if ('save' === $elvd_siswa_action) {
        $nonce = sanitize_text_field(wp_unslash((string) ($_POST['elvd_save_siswa_nonce'] ?? '')));

        if (! wp_verify_nonce($nonce, 'elvd_save_siswa')) {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Sesi form tidak valid. Silakan coba lagi.', 'elearning-vd') . '</div>';
        } elseif (! $elvd_can_edit) {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin untuk menyimpan profil ini.', 'elearning-vd') . '</div>';
        } else {
            $posted = isset($_POST['elvd_siswa']) && is_array($_POST['elvd_siswa'])
                ? wp_unslash($_POST['elvd_siswa'])
                : [];
            $user_data = ['ID' => $elvd_siswa_id];

            foreach ($elvd_profile_fields as $elvd_field_key => $elvd_field) {
                if (! empty($elvd_field['readonly'])) {
                    continue;
                }

                $elvd_raw = (string) ($posted[$elvd_field_key] ?? '');
                $elvd_type = (string) ($elvd_field['type'] ?? 'text');
                $elvd_target = (string) ($elvd_field['target'] ?? 'meta');

                if ('textarea' === $elvd_type) {
                    $elvd_value = sanitize_textarea_field($elvd_raw);
                } elseif ('email' === $elvd_type) {
                    $elvd_value = sanitize_email($elvd_raw);
                } elseif ('number' === $elvd_type) {
                    $elvd_value = (string) absint($elvd_raw);
                } else {
                    $elvd_value = sanitize_text_field($elvd_raw);
                }

                if ('display_name' === $elvd_target) {
                    if ('' !== $elvd_value) {
                        $user_data['display_name'] = $elvd_value;
                    }

                    continue;
                }

                if ('user_email' === $elvd_target) {
                    if ('' !== $elvd_value) {
                        $user_data['user_email'] = $elvd_value;
                    }

                    continue;
                }

                $elvd_meta_key = (string) ($elvd_field['meta_key'] ?? ('elvd_' . $elvd_field_key));
                update_user_meta($elvd_siswa_id, $elvd_meta_key, $elvd_value);
            }

            $saved = count($user_data) > 1 ? wp_update_user($user_data) : $elvd_siswa_id;

            if ($saved instanceof WP_Error) {
                $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html($saved->get_error_message()) . '</div>';
            } else {
                $elvd_siswa_notice = '<div class="alert alert-success">' . esc_html__('Profil berhasil disimpan.', 'elearning-vd') . '</div>';
            }
        }
    } elseif ('foto' === $elvd_siswa_action) {
        $nonce = sanitize_text_field(wp_unslash((string) ($_POST['elvd_foto_nonce'] ?? '')));

        if (! wp_verify_nonce($nonce, 'elvd_upload_foto')) {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Sesi form tidak valid. Silakan coba lagi.', 'elearning-vd') . '</div>';
        } elseif (! $elvd_can_edit) {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin untuk mengubah foto.', 'elearning-vd') . '</div>';
        } elseif (
            empty($_FILES['elvd_siswa_foto'])
            || ! is_array($_FILES['elvd_siswa_foto'])
            || UPLOAD_ERR_OK !== (int) ($_FILES['elvd_siswa_foto']['error'] ?? UPLOAD_ERR_NO_FILE)
        ) {
            $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html__('Pilih file foto terlebih dahulu.', 'elearning-vd') . '</div>';
        } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $upload = wp_handle_upload(
                $_FILES['elvd_siswa_foto'],
                [
                    'test_form' => false,
                    'mimes' => [
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'webp' => 'image/webp',
                    ],
                ]
            );

            if (! empty($upload['error'])) {
                $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html($upload['error']) . '</div>';
            } else {
                $file = $_FILES['elvd_siswa_foto'];
                $attachment_id = wp_insert_attachment(
                    [
                        'post_mime_type' => (string) ($upload['type'] ?? ''),
                        'post_title' => sanitize_file_name((string) ($file['name'] ?? '')),
                        'post_content' => '',
                        'post_status' => 'inherit',
                    ],
                    (string) ($upload['file'] ?? '')
                );

                if ($attachment_id instanceof WP_Error) {
                    $elvd_siswa_notice = '<div class="alert alert-danger">' . esc_html($attachment_id->get_error_message()) . '</div>';
                } else {
                    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, (string) ($upload['file'] ?? '')));
                    update_user_meta($elvd_siswa_id, 'elvd_foto', absint($attachment_id));
                    $elvd_siswa_notice = '<div class="alert alert-success">' . esc_html__('Foto berhasil diubah.', 'elearning-vd') . '</div>';
                }
            }
        }
    }
}

$elvd_siswa_data = [];

if ($elvd_siswa_valid && $elvd_siswa instanceof WP_User) {
    $elvd_kelas_table = elvd_table_name('elvd_kelas');
    $elvd_kelas_meta = (string) get_user_meta($elvd_siswa->ID, 'elvd_kelas', true);
    $elvd_kelas_info = null;

    if ('' !== $elvd_kelas_meta && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $elvd_kelas_table)) === $elvd_kelas_table) {
        $elvd_kelas_info = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT k.id, k.nama, k.tingkat, k.tahun_ajaran_id, ta.nama AS tahun_ajaran FROM ' . $elvd_kelas_table . ' k LEFT JOIN ' . elvd_table_name('elvd_tahun_ajaran') . ' ta ON ta.id = k.tahun_ajaran_id WHERE k.id = %d OR k.nama = %s LIMIT 1',
                absint($elvd_kelas_meta),
                $elvd_kelas_meta
            ),
            ARRAY_A
        );
    }

    $elvd_foto_id = absint(get_user_meta($elvd_siswa->ID, 'elvd_foto', true));

    $elvd_siswa_data = [
        'id' => (int) $elvd_siswa->ID,
        'nama' => '' !== trim((string) $elvd_siswa->display_name) ? $elvd_siswa->display_name : $elvd_siswa->user_login,
        'email' => $elvd_siswa->user_email,
        'foto' => $elvd_foto_id > 0 ? (string) wp_get_attachment_image_url($elvd_foto_id, 'thumbnail') : '',
    ];

    foreach ($elvd_profile_fields as $elvd_field_key => $elvd_field) {
        $elvd_target = (string) ($elvd_field['target'] ?? 'meta');

        if ('display_name' === $elvd_target) {
            $elvd_siswa_data[$elvd_field_key] = (string) $elvd_siswa->display_name;
            continue;
        }

        if ('user_email' === $elvd_target) {
            $elvd_siswa_data[$elvd_field_key] = (string) $elvd_siswa->user_email;
            continue;
        }

        $elvd_meta_key = (string) ($elvd_field['meta_key'] ?? ('elvd_' . $elvd_field_key));
        $elvd_field_value = (string) get_user_meta($elvd_siswa->ID, $elvd_meta_key, true);

        if ('kelas' === $elvd_field_key) {
            $elvd_siswa_data[$elvd_field_key] = $elvd_kelas_info ? (string) ($elvd_kelas_info['nama'] ?? '') : $elvd_field_value;
            $elvd_siswa_data['tingkat'] = $elvd_kelas_info ? (string) ($elvd_kelas_info['tingkat'] ?? '') : '';
            $elvd_siswa_data['tahun_ajaran'] = $elvd_kelas_info ? (string) ($elvd_kelas_info['tahun_ajaran'] ?? '') : '';
            continue;
        }

        $elvd_siswa_data[$elvd_field_key] = $elvd_field_value;
    }
}
?>

<div x-show="active === 'siswa-profil'" x-data="{
    siswa: <?php echo esc_attr(wp_json_encode($elvd_siswa_data)); ?>,
    canEdit: <?php echo $elvd_can_edit ? 'true' : 'false'; ?>,
    profileBaseUrl: <?php echo esc_attr(wp_json_encode($elvd_siswa_base_url)); ?>,
    activeTab: <?php echo esc_attr(wp_json_encode($elvd_profile_tab)); ?>,
    setTab(tab) {
        if (history.pushState) {
            history.pushState({}, '', this.profileBaseUrl + '/' + this.siswa.id + '/' + tab);
        }

        this.activeTab = tab;
    },
    formatDate(value) {
        if (!value) {
            return '-';
        }

        return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    },
    fieldValue(key) {
        let value = this.siswa[key] || '';

        if (key === 'tanggal_lahir' && value) {
            return this.formatDate(value);
        }

        return value || '-';
    },
    initials() {
        return this.siswa.nama ? this.siswa.nama.charAt(0).toUpperCase() : '?';
    }
}">
    <?php
    if ('' !== $elvd_siswa_notice) {
        echo $elvd_siswa_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    ?>

    <template x-if="siswa.id">
        <div class="elvd-table-panel">
            <div class="elvd-resource-toolbar">
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url($elvd_siswa_back_url); ?>">
                    &larr; <?php echo esc_html__('Kembali ke Daftar Siswa', 'elearning-vd'); ?>
                </a>
            </div>

            <div class="p-4">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3 mb-4">
                    <div class="elvd-avatar" aria-hidden="true">
                        <img x-show="siswa.foto" :src="siswa.foto" alt="">
                        <span x-show="!siswa.foto" x-text="initials()"></span>
                    </div>
                    <div>
                        <h2 class="h4 mb-1" x-text="siswa.nama || '-'"></h2>
                        <div class="text-muted" x-text="siswa.email || '-'"></div>
                    </div>
                </div>

                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" :class="{ active: activeTab === 'profil' }" @click="setTab('profil')">
                            <?php echo esc_html__('Profil', 'elearning-vd'); ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" :class="{ active: activeTab === 'edit' }" @click="setTab('edit')" x-show="canEdit">
                            <?php echo esc_html__('Edit', 'elearning-vd'); ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" :class="{ active: activeTab === 'foto' }" @click="setTab('foto')" x-show="canEdit">
                            <?php echo esc_html__('Ubah Foto', 'elearning-vd'); ?>
                        </button>
                    </li>
                </ul>

                <div x-show="activeTab === 'profil'">
                    <div class="row g-3">
                        <?php foreach ($elvd_profile_fields as $elvd_field_key => $elvd_field) : ?>
                            <div class="<?php echo esc_attr((string) ($elvd_field['wrapper_class'] ?? 'col-md-6 col-lg-4')); ?>">
                                <div class="elvd-profile-field">
                                    <span><?php echo esc_html((string) ($elvd_field['label'] ?? $elvd_field_key)); ?></span>
                                    <strong x-text="fieldValue('<?php echo esc_attr($elvd_field_key); ?>')"></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div x-show="activeTab === 'edit'" x-cloak>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="elvd_siswa_action" value="save">
                        <input type="hidden" name="elvd_save_siswa_nonce" value="<?php echo esc_attr(wp_create_nonce('elvd_save_siswa')); ?>">

                        <?php foreach ($elvd_profile_fields as $elvd_field_key => $elvd_field) : ?>
                            <?php
                            if (! empty($elvd_field['readonly'])) {
                                continue;
                            }

                            $elvd_input_type = (string) ($elvd_field['type'] ?? 'text');
                            $elvd_input_required = ! empty($elvd_field['required']) ? ' required' : '';
                            ?>
                            <div class="<?php echo esc_attr((string) ($elvd_field['wrapper_class'] ?? 'col-md-6')); ?>">
                                <label class="form-label" for="elvd-siswa-<?php echo esc_attr($elvd_field_key); ?>">
                                    <?php echo esc_html((string) ($elvd_field['label'] ?? $elvd_field_key)); ?>
                                </label>
                                <?php if ('textarea' === $elvd_input_type) : ?>
                                    <textarea class="form-control" id="elvd-siswa-<?php echo esc_attr($elvd_field_key); ?>" name="elvd_siswa[<?php echo esc_attr($elvd_field_key); ?>]" rows="3" <?php echo $elvd_input_required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                                                                                                                                                                                    ?>><?php echo esc_textarea((string) ($elvd_siswa_data[$elvd_field_key] ?? '')); ?></textarea>
                                <?php else : ?>
                                    <input type="<?php echo esc_attr($elvd_input_type); ?>" class="form-control" id="elvd-siswa-<?php echo esc_attr($elvd_field_key); ?>" name="elvd_siswa[<?php echo esc_attr($elvd_field_key); ?>]" value="<?php echo esc_attr((string) ($elvd_siswa_data[$elvd_field_key] ?? '')); ?>" <?php echo $elvd_input_required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                                                                                                                                                                                                                                                                                                            ?>>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary elvd-action-button">
                                <?php echo esc_html__('Simpan Profil', 'elearning-vd'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <div x-show="activeTab === 'foto'" x-cloak>
                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
                        <div class="elvd-avatar elvd-avatar-lg" aria-hidden="true">
                            <img x-show="siswa.foto" :src="siswa.foto" alt="">
                            <span x-show="!siswa.foto" x-text="initials()"></span>
                        </div>
                        <form method="post" enctype="multipart/form-data" class="flex-grow-1">
                            <input type="hidden" name="elvd_siswa_action" value="foto">
                            <input type="hidden" name="elvd_foto_nonce" value="<?php echo esc_attr(wp_create_nonce('elvd_upload_foto')); ?>">
                            <label class="form-label" for="elvd-siswa-foto"><?php echo esc_html__('Pilih foto baru', 'elearning-vd'); ?></label>
                            <input type="file" class="form-control mb-3" id="elvd-siswa-foto" name="elvd_siswa_foto" accept="image/jpeg,image/png,image/webp" required>
                            <button type="submit" class="btn btn-primary elvd-action-button">
                                <?php echo esc_html__('Unggah Foto', 'elearning-vd'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <div x-show="!siswa.id" x-cloak>
        <div class="alert alert-danger"><?php echo esc_html__('Data siswa tidak ditemukan.', 'elearning-vd'); ?></div>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url($elvd_siswa_back_url); ?>">
            &larr; <?php echo esc_html__('Kembali ke Daftar Siswa', 'elearning-vd'); ?>
        </a>
    </div>
</div>