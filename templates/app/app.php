<?php

defined('ABSPATH') || exit;

/**
 * @var array<string, mixed> $config
 */


if (! is_user_logged_in()) {
    return '<div class="alert alert-warning">' . esc_html__('Silakan login untuk mengakses elearning.', 'elearning-vd') . '</div>';
}

wp_enqueue_style('elvd-bootstrap');
wp_enqueue_script('elvd-alpine');

$config = [
    'restUrl' => esc_url_raw(rest_url(ELVD_REST_NAMESPACE)),
    'nonce' => wp_create_nonce('wp_rest'),
    'isManager' => elvd_can_manage_rest(),
];
?>

<div
    class="elvd-app container my-4"
    x-data="{
        tabs: ['tahun-ajaran', 'kelas', 'mata-pelajaran', 'jadwal-pelajaran'],
        labels: {
            'tahun-ajaran': 'Tahun Ajaran',
            'kelas': 'Kelas',
            'mata-pelajaran': 'Mata Pelajaran',
            'jadwal-pelajaran': 'Jadwal Pelajaran'
        },
        active: 'tahun-ajaran',
        items: [],
        loading: false,
        config: <?php echo wp_json_encode($config); ?>,
        init() { this.load(); },
        load() {
            this.loading = true;
            fetch(`${this.config.restUrl}/${this.active}`, {
                headers: { 'X-WP-Nonce': this.config.nonce }
            })
            .then((response) => response.json())
            .then((data) => { this.items = Array.isArray(data) ? data : []; })
            .finally(() => { this.loading = false; });
        },
        select(tab) {
            this.active = tab;
            this.load();
        }
    }">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h2 class="h4 mb-0"><?php echo esc_html__('Elearning VD', 'elearning-vd'); ?></h2>
        <div class="btn-group" role="tablist" aria-label="<?php echo esc_attr__('Menu Elearning', 'elearning-vd'); ?>">
            <template x-for="tab in tabs" :key="tab">
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="active === tab ? 'btn-primary' : 'btn-outline-primary'"
                    @click="select(tab)"
                    x-text="labels[tab]"></button>
            </template>
        </div>
    </div>

    <div class="table-responsive border rounded">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('ID', 'elearning-vd'); ?></th>
                    <th scope="col"><?php echo esc_html__('Nama', 'elearning-vd'); ?></th>
                    <th scope="col"><?php echo esc_html__('Detail', 'elearning-vd'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr x-show="loading">
                    <td colspan="3"><?php echo esc_html__('Memuat data...', 'elearning-vd'); ?></td>
                </tr>
                <template x-for="item in items" :key="item.id">
                    <tr>
                        <td x-text="item.id"></td>
                        <td x-text="item.nama || item.hari || '-'"></td>
                        <td x-text="Object.entries(item).filter(([key]) => !['id','nama','created_at','updated_at'].includes(key)).map(([key, value]) => `${key}: ${value ?? '-'}`).join(' | ')"></td>
                    </tr>
                </template>
                <tr x-show="!loading && items.length === 0">
                    <td colspan="3"><?php echo esc_html__('Belum ada data.', 'elearning-vd'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>