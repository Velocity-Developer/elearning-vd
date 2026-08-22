<?php

defined('ABSPATH') || exit;

function elvd_rest_list_items(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;

    $resource = elvd_get_resource_from_request($request);
    if (is_wp_error($resource)) {
        return new WP_REST_Response($resource, 404);
    }

    $table = elvd_table_name($resource['table']);

    $page = max(1, absint($request->get_param('page') ?: 1));
    $per_page = min(100, max(1, absint($request->get_param('per_page') ?: 20)));
    $offset = ($page - 1) * $per_page;

    $where = '';

    foreach (['quiz_id', 'siswa_id', 'tugas_id', 'user_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id', 'tahun_ajaran_id'] as $field) {
        $value = $request->get_param($field);

        if (null === $value || '' === $value || ! isset($resource['fields'][$field])) {
            continue;
        }

        $where .= $where ? ' AND ' : 'WHERE ';
        $where .= $wpdb->prepare(esc_sql($field) . ' = %d', absint($value));
    }

    if ('elvd_pengerjaan_quiz' === $resource['table'] && ! elvd_can_manage_rest()) {
        $siswa_id = get_current_user_id();
        $where .= $where ? ' AND ' : 'WHERE ';
        $where .= $wpdb->prepare(esc_sql('siswa_id') . ' = %d', $siswa_id);
    }

    if ('elvd_jadwal_pelajaran' === $resource['table']) {
        $table_mapel = elvd_table_name('elvd_mata_pelajaran');

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, mp.id AS mata_pelajaran_id, mp.nama AS mata_pelajaran_nama, mp.kode_warna AS mata_pelajaran_kode_warna
                 FROM {$table} t
                 LEFT JOIN {$table_mapel} mp ON mp.id = t.mata_pelajaran_id
                 {$where}
                 ORDER BY t.id DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    } else {
        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset),
            ARRAY_A
        );
    }

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");

    if ('elvd_jadwal_pelajaran' === $resource['table']) {
        foreach ($items as &$row) {
            $row['mata_pelajaran'] = [
                'id' => isset($row['mata_pelajaran_id']) ? (int) $row['mata_pelajaran_id'] : 0,
                'nama' => isset($row['mata_pelajaran_nama']) ? (string) $row['mata_pelajaran_nama'] : '',
                'kode_warna' => $row['mata_pelajaran_kode_warna'] !== null ? (string) $row['mata_pelajaran_kode_warna'] : null,
            ];

            unset(
                $row['mata_pelajaran_id'],
                $row['mata_pelajaran_nama'],
                $row['mata_pelajaran_kode_warna']
            );
        }
        unset($row);
    }

    if ('elvd_pengerjaan_quiz' === $resource['table']) {
        foreach ($items as &$row) {
            $user = get_userdata((int) ($row['siswa_id'] ?? 0));
            $row['siswa_name'] = $user ? (string) $user->display_name : '';
        }
        unset($row);
    }

    if ('elvd_pengerjaan_tugas' === $resource['table']) {
        foreach ($items as &$row) {
            $user = get_userdata((int) ($row['user_id'] ?? 0));
            $row['user_name'] = $user ? (string) $user->display_name : '';
        }
        unset($row);
    }

    $response = new WP_REST_Response($items);
    $response->header('X-WP-Total', (string) $total);
    $response->header('X-WP-TotalPages', (string) max(1, (int) ceil($total / $per_page)));

    return $response;
}
