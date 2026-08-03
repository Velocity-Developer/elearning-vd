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

$route_page = sanitize_key((string) get_query_var(ELVD::APP_PAGE_QUERY_VAR, ''));
$default_page = 'tahun-ajaran';
$page_file = '';

if ('' !== $route_page) {
    $candidate_page_file = ELVD_PLUGIN_DIR . 'templates/app/pages/' . $route_page . '.php';

    if (file_exists($candidate_page_file)) {
        $page_file = $candidate_page_file;
    } else {
        status_header(404);
        nocache_headers();
    }
}

$active_page = '' !== $route_page ? $route_page : $default_page;
?>

<div
    class="elvd-app container-fluid my-4 px-3 px-lg-4"
    x-data='{
        tabs: ["tahun-ajaran", "kelas", "mata-pelajaran", "jadwal-pelajaran", "guru", "siswa", "quiz"],
        labels: {
            "tahun-ajaran": "Tahun Ajaran",
            "kelas": "Kelas",
            "mata-pelajaran": "Mata Pelajaran",
            "jadwal-pelajaran": "Jadwal Pelajaran",
            "guru": "Guru",
            "siswa": "Siswa",
            "quiz": "Quiz"
        },
        defaultLabel: <?php echo esc_attr(wp_json_encode(__('Elearning VD', 'elearning-vd'))); ?>,
        active: <?php echo esc_attr(wp_json_encode($active_page)); ?>,
        appRoute: <?php echo esc_attr(wp_json_encode(untrailingslashit(ELVD::app_route()))); ?>,
        items: [],
        loading: false,
        config: <?php echo esc_attr(wp_json_encode($config)); ?>,
        init() { this.load(); },
        load() {
            this.loading = true;
            fetch(`${this.config.restUrl}/${this.active}`, {
                headers: { "X-WP-Nonce": this.config.nonce }
            })
            .then((response) => response.json())
            .then((data) => { this.items = Array.isArray(data) ? data : []; })
            .finally(() => { this.loading = false; });
        },
        select(tab) {
            this.active = tab;
            window.location.href = `${this.appRoute}/${tab}/`;
        }
    }'>
    <div class="row g-4">
        <aside class="col-12 col-lg-3 col-xl-2">
            <div class="border rounded bg-white shadow-sm">
                <div class="border-bottom p-3">
                    <h2 class="h5 mb-1"><?php echo esc_html__('Elearning VD', 'elearning-vd'); ?></h2>
                    <p class="small text-muted mb-0"><?php echo esc_html__('Dashboard sekolah', 'elearning-vd'); ?></p>
                </div>
                <nav class="nav nav-pills flex-column gap-1 p-2" aria-label="<?php echo esc_attr__('Menu Elearning', 'elearning-vd'); ?>">
                    <template x-for="tab in tabs" :key="tab">
                        <button
                            type="button"
                            class="nav-link text-start"
                            :class="active === tab ? 'active' : 'text-dark'"
                            @click="select(tab)"
                            x-text="labels[tab]"></button>
                    </template>
                </nav>
            </div>
        </aside>

        <section class="col-12 col-lg-9 col-xl-10">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <p class="small text-uppercase text-muted mb-1"><?php echo esc_html__('Dashboard', 'elearning-vd'); ?></p>
                    <h1 class="h3 mb-0" x-text="labels[active] || defaultLabel"></h1>
                </div>
                <div class="badge text-bg-light border px-3 py-2" x-show="!loading">
                    <span x-text="items.length"></span>
                    <?php echo esc_html__('data', 'elearning-vd'); ?>
                </div>
            </div>

            <?php
            if ('' !== $route_page && '' === $page_file) {
            ?>
                <div class="alert alert-warning mb-3">
                    <?php echo esc_html__('Halaman elearning tidak ditemukan.', 'elearning-vd'); ?>
                </div>
            <?php
            } elseif ('' !== $page_file) {
                require $page_file;
            }
            ?>

            <div class="table-responsive border rounded bg-white shadow-sm">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
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
        </section>
    </div>
</div>
