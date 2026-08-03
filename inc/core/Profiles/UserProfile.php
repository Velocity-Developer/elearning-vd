<?php

declare(strict_types=1);

namespace ElearningVD\Profiles;

use WP_Error;
use WP_User;

defined('ABSPATH') || exit;

abstract class UserProfile
{
    abstract public static function role(): string;

    abstract public static function role_label(): string;

    /**
     * @return array<string, array<string, mixed>>
     */
    abstract protected static function default_fields(): array;

    public static function render_current_user_form(): string
    {
        if (! is_user_logged_in()) {
            return '<div class="alert alert-warning">' . esc_html__('Silakan login untuk mengisi profil.', 'elearning-vd') . '</div>';
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        if (! $user instanceof WP_User) {
            return '<div class="alert alert-danger">' . esc_html__('User tidak ditemukan.', 'elearning-vd') . '</div>';
        }

        if (! self::can_manage_profile($user)) {
            return '<div class="alert alert-info">' . sprintf(
                esc_html__('Form ini hanya untuk profil %s.', 'elearning-vd'),
                esc_html(static::role_label())
            ) . '</div>';
        }

        $notice = '';

        if ('POST' === strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) && isset($_POST[static::nonce_action()])) {
            $notice = self::handle_submission($user);
        }

        ob_start();
        do_action('elvd_before_profile_form', static::role(), $user);
        echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <form method="post" class="elvd-profile-form border rounded p-3 mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h3 class="h5 mb-0">
                    <?php
                    printf(
                        esc_html__('Profil %s', 'elearning-vd'),
                        esc_html(static::role_label())
                    );
                    ?>
                </h3>
                <button type="submit" class="btn btn-primary btn-sm">
                    <?php echo esc_html__('Simpan Profil', 'elearning-vd'); ?>
                </button>
            </div>

            <?php wp_nonce_field(static::nonce_action(), static::nonce_action()); ?>

            <div class="row g-3">
                <?php foreach (static::fields() as $key => $field) : ?>
                    <div class="<?php echo esc_attr((string) ($field['wrapper_class'] ?? 'col-md-6')); ?>">
                        <?php static::render_field($key, $field, $user); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php do_action('elvd_profile_form_fields', static::role(), $user); ?>
            <?php do_action('elvd_' . static::role() . '_profile_form_fields', $user); ?>
        </form>
        <?php
        do_action('elvd_after_profile_form', static::role(), $user);

        return (string) ob_get_clean();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        $registry = new ProfileFields(static::default_fields());

        do_action('elvd_register_profile_fields', $registry, static::role(), static::class);
        do_action('elvd_register_' . static::role() . '_profile_fields', $registry, static::class);

        $fields = apply_filters('elvd_profile_fields', $registry->all(), static::role(), static::class);
        $fields = apply_filters('elvd_' . static::role() . '_profile_fields', $fields, static::class);

        return is_array($fields) ? self::normalize_fields($fields) : [];
    }

    private static function can_manage_profile(WP_User $user): bool
    {
        return in_array(static::role(), (array) $user->roles, true) || current_user_can('manage_options');
    }

    private static function handle_submission(WP_User $user): string
    {
        $nonce = sanitize_text_field(wp_unslash((string) ($_POST[static::nonce_action()] ?? '')));

        if (! wp_verify_nonce($nonce, static::nonce_action())) {
            return '<div class="alert alert-danger">' . esc_html__('Sesi form tidak valid. Silakan coba lagi.', 'elearning-vd') . '</div>';
        }

        if (! self::can_manage_profile($user)) {
            return '<div class="alert alert-danger">' . esc_html__('Anda tidak memiliki izin untuk menyimpan profil ini.', 'elearning-vd') . '</div>';
        }

        $user_data = ['ID' => $user->ID];

        foreach (static::fields() as $key => $field) {
            if (! empty($field['readonly'])) {
                continue;
            }

            $value = self::posted_value($key, $field);

            if ('display_name' === ($field['target'] ?? 'meta')) {
                $user_data['display_name'] = $value;
                continue;
            }

            if ('user_email' === ($field['target'] ?? 'meta')) {
                $user_data['user_email'] = $value;
                continue;
            }

            update_user_meta($user->ID, self::meta_key($key, $field), $value);
        }

        if (count($user_data) > 1) {
            $updated = wp_update_user($user_data);

            if ($updated instanceof WP_Error) {
                return '<div class="alert alert-danger">' . esc_html($updated->get_error_message()) . '</div>';
            }
        }

        do_action('elvd_profile_saved', static::role(), $user->ID, static::fields());
        do_action('elvd_' . static::role() . '_profile_saved', $user->ID, static::fields());

        return '<div class="alert alert-success">' . esc_html__('Profil berhasil disimpan.', 'elearning-vd') . '</div>';
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function render_field(string $key, array $field, WP_User $user): void
    {
        $type = (string) ($field['type'] ?? 'text');
        $id = 'elvd_' . static::role() . '_' . sanitize_key($key);
        $name = 'elvd_profile[' . esc_attr($key) . ']';
        $value = self::field_value($key, $field, $user);
        $required = ! empty($field['required']) ? ' required' : '';
        $placeholder = isset($field['placeholder']) ? ' placeholder="' . esc_attr((string) $field['placeholder']) . '"' : '';

        ?>
        <label for="<?php echo esc_attr($id); ?>" class="form-label">
            <?php echo esc_html((string) ($field['label'] ?? $key)); ?>
        </label>
        <?php if ('textarea' === $type) : ?>
            <textarea
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($name); ?>"
                class="form-control"
                rows="<?php echo esc_attr((string) ($field['rows'] ?? 3)); ?>"
                <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            ><?php echo esc_textarea($value); ?></textarea>
        <?php elseif ('select' === $type) : ?>
            <select
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($name); ?>"
                class="form-select"
                <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            >
                <?php foreach ((array) ($field['options'] ?? []) as $option_value => $option_label) : ?>
                    <option value="<?php echo esc_attr((string) $option_value); ?>" <?php selected((string) $value, (string) $option_value); ?>>
                        <?php echo esc_html((string) $option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else : ?>
            <input
                id="<?php echo esc_attr($id); ?>"
                type="<?php echo esc_attr($type); ?>"
                name="<?php echo esc_attr($name); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="form-control"
                <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            >
        <?php endif; ?>
        <?php if (! empty($field['description'])) : ?>
            <div class="form-text"><?php echo esc_html((string) $field['description']); ?></div>
        <?php endif; ?>
        <?php
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function field_value(string $key, array $field, WP_User $user): string
    {
        if ('display_name' === ($field['target'] ?? 'meta')) {
            return (string) $user->display_name;
        }

        if ('user_email' === ($field['target'] ?? 'meta')) {
            return (string) $user->user_email;
        }

        return (string) get_user_meta($user->ID, self::meta_key($key, $field), true);
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function posted_value(string $key, array $field): string
    {
        $values = isset($_POST['elvd_profile']) && is_array($_POST['elvd_profile'])
            ? wp_unslash($_POST['elvd_profile'])
            : [];
        $raw = $values[$key] ?? '';
        $type = (string) ($field['type'] ?? 'text');

        if ('textarea' === $type) {
            return sanitize_textarea_field((string) $raw);
        }

        if ('email' === $type) {
            return sanitize_email((string) $raw);
        }

        if ('number' === $type) {
            return (string) absint($raw);
        }

        if ('select' === $type) {
            $options = array_map('strval', array_keys((array) ($field['options'] ?? [])));
            $value = sanitize_key((string) $raw);

            return in_array($value, $options, true) ? $value : '';
        }

        return sanitize_text_field((string) $raw);
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function meta_key(string $key, array $field): string
    {
        return (string) ($field['meta_key'] ?? ('elvd_' . sanitize_key($key)));
    }

    private static function nonce_action(): string
    {
        return 'elvd_save_' . static::role() . '_profile';
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, array<string, mixed>>
     */
    private static function normalize_fields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $key => $field) {
            if (! is_string($key) || ! is_array($field)) {
                continue;
            }

            $normalized[sanitize_key($key)] = $field;
        }

        return $normalized;
    }
}
