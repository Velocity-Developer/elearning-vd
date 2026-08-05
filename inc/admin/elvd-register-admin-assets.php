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

    wp_register_style(
        'elvd-admin',
        ELVD_PLUGIN_URL . '/assets/admin.css',
        [],
        ELVD::VERSION,
        'all'
    );

    wp_enqueue_style('elvd-admin');

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

    $('.elvd-add-profile-field').on('click', function (event) {
        event.preventDefault();

        var option = $(this).data('field-option');
        var $tbody = $($(this).data('target') + ' tbody');
        var index = $tbody.find('tr').length;
        var row = ''
            + '<tr>'
            + '<td><input type="text" name="' + option + '[' + index + '][key]" value="" class="regular-text" required></td>'
            + '<td><input type="text" name="' + option + '[' + index + '][label]" value="" class="regular-text" required></td>'
            + '<td><select name="' + option + '[' + index + '][type]">'
            + '<option value="text">Text</option>'
            + '<option value="email">Email</option>'
            + '<option value="number">Angka</option>'
            + '<option value="date">Tanggal</option>'
            + '<option value="tel">Telepon</option>'
            + '<option value="textarea">Textarea</option>'
            + '</select></td>'
            + '<td><select name="' + option + '[' + index + '][target]">'
            + '<option value="meta">User Meta</option>'
            + '<option value="display_name">Nama Tampilan User</option>'
            + '<option value="user_email">Email User</option>'
            + '</select></td>'
            + '<td><label><input type="checkbox" name="' + option + '[' + index + '][required]" value="1"> Ya</label></td>'
            + '<td><button type="button" class="button elvd-remove-profile-field">Hapus</button></td>'
            + '</tr>';

        $tbody.append(row);
    });

    $('.elvd-profile-fields').on('click', '.elvd-remove-profile-field', function (event) {
        event.preventDefault();

        $(this).closest('tr').remove();
    });
});
JS;
}
