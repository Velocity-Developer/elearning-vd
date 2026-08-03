<?php

defined('ABSPATH') || exit;

function elvd_register_admin_assets(string $hook_suffix): void
{
    unset($hook_suffix);

    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';

    if (ELVD::SETTINGS_MENU_SLUG !== $page) {
        return;
    }

    wp_enqueue_media();

    wp_register_script(
        'elvd-admin-settings',
        '',
        ['jquery'],
        ELVD::VERSION,
        true
    );

    wp_enqueue_script('elvd-admin-settings');
    wp_add_inline_script('elvd-admin-settings', elvd_admin_settings_script());
}

function elvd_admin_settings_script(): string
{
    return <<<'JS'
jQuery(function ($) {
    var frame;
    var $logoId = $('#elvd-school-logo-id');
    var $preview = $('#elvd-school-logo-preview');
    var $removeButton = $('#elvd-remove-school-logo');

    $('#elvd-select-school-logo').on('click', function (event) {
        event.preventDefault();

        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: 'Pilih Logo Sekolah',
            button: {
                text: 'Gunakan Logo'
            },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var imageUrl = attachment.sizes && attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;

            $logoId.val(attachment.id);
            $preview.attr('src', imageUrl).removeClass('hidden');
            $removeButton.removeClass('hidden');
        });

        frame.open();
    });

    $removeButton.on('click', function (event) {
        event.preventDefault();

        $logoId.val('');
        $preview.attr('src', '').addClass('hidden');
        $removeButton.addClass('hidden');
    });
});
JS;
}
