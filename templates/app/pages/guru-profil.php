<?php

defined('ABSPATH') || exit;

global $wpdb;

$elvd_guru_id = absint((int) get_query_var('elvd_guru_id', 0));
$elvd_profile_tab = sanitize_key((string) get_query_var('elvd_guru_tab', ''));
$elvd_profile_tab = in_array($elvd_profile_tab, ['profil', 'edit', 'foto', 'akun'], true) ? $elvd_profile_tab : 'profil';
$elvd_guru_base_url = untrailingslashit(ELVD::app_route()) . '/guru-profil';
$elvd_guru_back_url = untrailingslashit(ELVD::app_route()) . '/guru/';
$elvd_guru_notice = '';

$elvd_profile_fields = class_exists(\ElearningVD\Guru::class) ? \ElearningVD\Guru::fields() : [];

if ($elvd_guru_id > 0) {
    $elvd_guru = get_userdata($elvd_guru_id);
    $elvd_guru_valid = $elvd_guru instanceof WP_User && in_array('guru', (array) $elvd_guru->roles, true);
    $elvd_can_edit = $elvd_guru_valid && (
        current_user_can('edit_user', $elvd_guru_id)
        || current_user_can('manage_options')
        || $elvd_guru->ID === get_current_user_id()
    );
} else {
    $elvd_guru = null;
    $elvd_guru_valid = false;
    $elvd_can_edit = false;
}

if ('POST' === strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) && isset($_POST['elvd_guru_action'])) {
    $elvd_guru_action = sanitize_key((string) wp_unslash($_POST['elvd_guru_action']));

    if ('save' === $elvd_guru_action) {
        $nonce = sanitize_text_field(wp_unslash((string) ($_POST['elvd_save_guru_nonce'] ?? '')));

        if (! wp_verify_nonce($nonce, 'elvd_save_guru')) {
            $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Sesi form tidak valid. Silakan coba lagi.', 'elearning-vd') . '</div>';
        } elseif (! $elvd_can_edit) {
            $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin untuk menyimpan profil ini.', 'elearning-vd') . '</div>';
        } else {
            $posted = isset($_POST['elvd_guru']) && is_array($_POST['elvd_guru'])
                ? wp_unslash($_POST['elvd_guru'])
                : [];
            $user_data = ['ID' => $elvd_guru_id];

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
                update_user_meta($elvd_guru_id, $elvd_meta_key, $elvd_value);
            }

            $saved = count($user_data) > 1 ? wp_update_user($user_data) : $elvd_guru_id;

            if ($saved instanceof WP_Error) {
                $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html($saved->get_error_message()) . '</div>';
            } else {
                $elvd_guru_notice = '<div class="alert alert-success">' . esc_html__('Profil berhasil disimpan.', 'elearning-vd') . '</div>';
            }
        }
    } elseif ('akun' === $elvd_guru_action) {
        $nonce = sanitize_text_field(wp_unslash((string) ($_POST['elvd_akun_nonce'] ?? '')));

        if (! wp_verify_nonce($nonce, 'elvd_update_akun')) {
            $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Sesi form tidak valid. Silakan coba lagi.', 'elearning-vd') . '</div>';
        } elseif (! $elvd_can_edit) {
            $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin untuk mengubah akun.', 'elearning-vd') . '</div>';
        } else {
            $elvd_akun_email    = sanitize_email((string) wp_unslash($_POST['elvd_akun_email'] ?? ''));
            $elvd_akun_username = sanitize_user((string) wp_unslash($_POST['elvd_akun_username'] ?? ''), false);
            $elvd_akun_pass     = (string) wp_unslash($_POST['elvd_akun_password'] ?? '');
            $elvd_akun_pass2    = (string) wp_unslash($_POST['elvd_akun_password2'] ?? '');
            $elvd_akun_data     = ['ID' => $elvd_guru_id];
            $elvd_akun_errors   = [];

            if ('' !== $elvd_akun_pass) {
                if ($elvd_akun_pass !== $elvd_akun_pass2) {
                    $elvd_akun_errors[] = __('Konfirmasi password tidak cocok.', 'elearning-vd');
                } elseif (strlen($elvd_akun_pass) < 8) {
                    $elvd_akun_errors[] = __('Password minimal 8 karakter.', 'elearning-vd');
                } else {
                    $elvd_akun_data['user_pass'] = $elvd_akun_pass;
                }
            }

            if ('' !== $elvd_akun_email && $elvd_akun_email !== (string) $elvd_guru->user_email) {
                $elvd_akun_data['user_email'] = $elvd_akun_email;
            }

            if ('' !== $elvd_akun_username && $elvd_akun_username !== $elvd_guru->user_login) {
                $existing = get_user_by('login', $elvd_akun_username);
                if ($existing && $existing->ID !== $elvd_guru_id) {
                    $elvd_akun_errors[] = __('Username sudah digunakan.', 'elearning-vd');
                } else {
                    // wp_update_user() tidak mengubah user_login saat update (WP core). Update langsung via SQL.
                    $login_updated = $wpdb->update($wpdb->users, ['user_login' => $elvd_akun_username], ['ID' => $elvd_guru_id]);

                    if (false === $login_updated) {
                        $elvd_akun_errors[] = __('Gagal mengubah username.', 'elearning-vd');
                    } else {
                        clean_user_cache($elvd_guru_id);
                        $elvd_guru->user_login = $elvd_akun_username;
                    }
                }
            }

            if ([] !== $elvd_akun_errors) {
                $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html(implode(' ', $elvd_akun_errors)) . '</div>';
            } else {
                $elvd_akun_saved = wp_update_user($elvd_akun_data);

                if ($elvd_akun_saved instanceof WP_Error) {
                    $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html($elvd_akun_saved->get_error_message()) . '</div>';
                } else {
                    $elvd_guru_notice = '<div class="alert alert-success">' . esc_html__('Akun berhasil diperbarui.', 'elearning-vd') . '</div>';
                }
            }
        }
    } elseif ('foto' === $elvd_guru_action) {
        $nonce = sanitize_text_field(wp_unslash((string) ($_POST['elvd_foto_nonce'] ?? '')));

        if (! wp_verify_nonce($nonce, 'elvd_upload_foto')) {
            $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Sesi form tidak valid. Silakan coba lagi.', 'elearning-vd') . '</div>';
        } elseif (! $elvd_can_edit) {
            $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin untuk mengubah foto.', 'elearning-vd') . '</div>';
        } elseif (
            empty($_FILES['elvd_guru_foto'])
            || ! is_array($_FILES['elvd_guru_foto'])
            || UPLOAD_ERR_OK !== (int) ($_FILES['elvd_guru_foto']['error'] ?? UPLOAD_ERR_NO_FILE)
        ) {
            $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html__('Pilih file foto terlebih dahulu.', 'elearning-vd') . '</div>';
        } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $upload = wp_handle_upload(
                $_FILES['elvd_guru_foto'],
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
                $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html($upload['error']) . '</div>';
            } else {
                $file = $_FILES['elvd_guru_foto'];
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
                    $elvd_guru_notice = '<div class="alert alert-danger">' . esc_html($attachment_id->get_error_message()) . '</div>';
                } else {
                    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, (string) ($upload['file'] ?? '')));
                    update_user_meta($elvd_guru_id, 'elvd_foto', absint($attachment_id));
                    $elvd_guru_notice = '<div class="alert alert-success">' . esc_html__('Foto berhasil diubah.', 'elearning-vd') . '</div>';
                }
            }
        }
    }
}

$elvd_guru_data = [];

if ($elvd_guru_valid && $elvd_guru instanceof WP_User) {
    $elvd_foto_id = absint(get_user_meta($elvd_guru->ID, 'elvd_foto', true));

    $elvd_guru_data = [
        'id' => (int) $elvd_guru->ID,
        'nama' => '' !== trim((string) $elvd_guru->display_name) ? $elvd_guru->display_name : $elvd_guru->user_login,
        'email' => $elvd_guru->user_email,
        'foto' => $elvd_foto_id > 0 ? (string) wp_get_attachment_image_url($elvd_foto_id, 'thumbnail') : '',
    ];

    foreach ($elvd_profile_fields as $elvd_field_key => $elvd_field) {
        $elvd_target = (string) ($elvd_field['target'] ?? 'meta');

        if ('display_name' === $elvd_target) {
            $elvd_guru_data[$elvd_field_key] = (string) $elvd_guru->display_name;
            continue;
        }

        if ('user_email' === $elvd_target) {
            $elvd_guru_data[$elvd_field_key] = (string) $elvd_guru->user_email;
            continue;
        }

        $elvd_meta_key = (string) ($elvd_field['meta_key'] ?? ('elvd_' . $elvd_field_key));
        $elvd_guru_data[$elvd_field_key] = (string) get_user_meta($elvd_guru->ID, $elvd_meta_key, true);
    }
}
?>

<div x-show="active === 'guru-profil'" x-data="{
    guru: <?php echo esc_attr(wp_json_encode($elvd_guru_data)); ?>,
    canEdit: <?php echo $elvd_can_edit ? 'true' : 'false'; ?>,
    profileBaseUrl: <?php echo esc_attr(wp_json_encode($elvd_guru_base_url)); ?>,
    activeTab: <?php echo esc_attr(wp_json_encode($elvd_profile_tab)); ?>,
    setTab(tab) {
        if (history.pushState) {
            history.pushState({}, '', this.profileBaseUrl + '/' + this.guru.id + '/' + tab);
        }

        this.activeTab = tab;
    },
    fieldValue(key) {
        return this.guru[key] || '-';
    },
    initials() {
        return this.guru.nama ? this.guru.nama.charAt(0).toUpperCase() : '?';
    }
}">
    <?php
    if ('' !== $elvd_guru_notice) {
        echo $elvd_guru_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    ?>

    <template x-if="guru.id">
        <div class="elvd-table-panel">
            <div class="elvd-resource-toolbar">
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url($elvd_guru_back_url); ?>">
                    &larr; <?php echo esc_html__('Kembali ke Daftar Guru', 'elearning-vd'); ?>
                </a>
            </div>

            <div class="p-4">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3 mb-4">
                    <div class="elvd-avatar" aria-hidden="true">
                        <img x-show="guru.foto" :src="guru.foto" alt="">
                        <span x-show="!guru.foto" x-text="initials()"></span>
                    </div>
                    <div>
                        <h2 class="h4 mb-1" x-text="guru.nama || '-'"></h2>
                        <div class="text-muted" x-text="guru.email || '-'"></div>
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
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" :class="{ active: activeTab === 'akun' }" @click="setTab('akun')" x-show="canEdit">
                            <?php echo esc_html__('Akun', 'elearning-vd'); ?>
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
                        <input type="hidden" name="elvd_guru_action" value="save">
                        <input type="hidden" name="elvd_save_guru_nonce" value="<?php echo esc_attr(wp_create_nonce('elvd_save_guru')); ?>">

                        <?php foreach ($elvd_profile_fields as $elvd_field_key => $elvd_field) : ?>
                            <?php
                            if (! empty($elvd_field['readonly'])) {
                                continue;
                            }

                            $elvd_input_type = (string) ($elvd_field['type'] ?? 'text');
                            $elvd_input_required = ! empty($elvd_field['required']) ? ' required' : '';
                            ?>
                            <div class="<?php echo esc_attr((string) ($elvd_field['wrapper_class'] ?? 'col-md-6')); ?>">
                                <label class="form-label" for="elvd-guru-<?php echo esc_attr($elvd_field_key); ?>">
                                    <?php echo esc_html((string) ($elvd_field['label'] ?? $elvd_field_key)); ?>
                                </label>
                                <?php if ('textarea' === $elvd_input_type) : ?>
                                    <textarea class="form-control" id="elvd-guru-<?php echo esc_attr($elvd_field_key); ?>" name="elvd_guru[<?php echo esc_attr($elvd_field_key); ?>]" rows="3" <?php echo $elvd_input_required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                                                                                                                                                                                ?>><?php echo esc_textarea((string) ($elvd_guru_data[$elvd_field_key] ?? '')); ?></textarea>
                                <?php else : ?>
                                    <input type="<?php echo esc_attr($elvd_input_type); ?>" class="form-control" id="elvd-guru-<?php echo esc_attr($elvd_field_key); ?>" name="elvd_guru[<?php echo esc_attr($elvd_field_key); ?>]" value="<?php echo esc_attr((string) ($elvd_guru_data[$elvd_field_key] ?? '')); ?>" <?php echo $elvd_input_required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
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
                            <img x-show="guru.foto" :src="guru.foto" alt="">
                            <span x-show="!guru.foto" x-text="initials()"></span>
                        </div>
                        <form method="post" enctype="multipart/form-data" class="flex-grow-1">
                            <input type="hidden" name="elvd_guru_action" value="foto">
                            <input type="hidden" name="elvd_foto_nonce" value="<?php echo esc_attr(wp_create_nonce('elvd_upload_foto')); ?>">
                            <label class="form-label" for="elvd-guru-foto"><?php echo esc_html__('Pilih foto baru', 'elearning-vd'); ?></label>
                            <input type="file" class="form-control mb-3" id="elvd-guru-foto" name="elvd_guru_foto" accept="image/jpeg,image/png,image/webp" required>
                            <button type="submit" class="btn btn-primary elvd-action-button">
                                <?php echo esc_html__('Unggah Foto', 'elearning-vd'); ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div x-show="activeTab === 'akun'" x-cloak>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="elvd_guru_action" value="akun">
                        <input type="hidden" name="elvd_akun_nonce" value="<?php echo esc_attr(wp_create_nonce('elvd_update_akun')); ?>">

                        <div class="col-md-6">
                            <label class="form-label" for="elvd-akun-email"><?php echo esc_html__('Email', 'elearning-vd'); ?></label>
                            <input type="email" class="form-control" id="elvd-akun-email" name="elvd_akun_email" value="<?php echo esc_attr((string) ($elvd_guru->user_email ?? '')); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="elvd-akun-username"><?php echo esc_html__('Username', 'elearning-vd'); ?></label>
                            <input type="text" class="form-control" id="elvd-akun-username" name="elvd_akun_username" value="<?php echo esc_attr((string) ($elvd_guru->user_login ?? '')); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="elvd-akun-password"><?php echo esc_html__('Password Baru', 'elearning-vd'); ?></label>
                            <input type="password" class="form-control" id="elvd-akun-password" name="elvd_akun_password" minlength="8" autocomplete="new-password">
                            <div class="form-text"><?php echo esc_html__('Kosongkan jika tidak ingin mengubah.', 'elearning-vd'); ?></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="elvd-akun-password2"><?php echo esc_html__('Konfirmasi Password', 'elearning-vd'); ?></label>
                            <input type="password" class="form-control" id="elvd-akun-password2" name="elvd_akun_password2" minlength="8" autocomplete="new-password">
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary elvd-action-button">
                                <?php echo esc_html__('Perbarui Akun', 'elearning-vd'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>

    <div x-show="!guru.id" x-cloak>
        <div class="alert alert-danger"><?php echo esc_html__('Data guru tidak ditemukan.', 'elearning-vd'); ?></div>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url($elvd_guru_back_url); ?>">
            &larr; <?php echo esc_html__('Kembali ke Daftar Guru', 'elearning-vd'); ?>
        </a>
    </div>
</div>