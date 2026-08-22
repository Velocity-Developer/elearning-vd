<?php

defined('ABSPATH') || exit;

$elvd_guru_options = array_map(
    static function (WP_User $user): array {
        return [
            'id' => (int) $user->ID,
            'nama' => '' !== trim($user->display_name) ? $user->display_name : $user->user_login,
        ];
    },
    get_users(
        [
            'role' => 'guru',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]
    )
);

global $wpdb;

$elvd_tahun_aktif = (string) $wpdb->get_var(
    $wpdb->prepare(
        'SELECT nama FROM ' . elvd_table_name('elvd_tahun_ajaran') . ' WHERE status = %s ORDER BY mulai DESC, id DESC LIMIT 1',
        'aktif'
    )
);
?>

<div
    x-show="active === 'jadwal-pelajaran-siswa'"
    x-data="{
        schedules: [],
        loading: false,
        error: '',
        kelasId: Number(config.siswaKelasId || 0),
        days: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
        init() {
            this.fetchSchedules();
        },
        fetchSchedules() {
            if (config.currentRole !== 'siswa' || !this.kelasId) {
                this.schedules = [];
                return;
            }

            this.loading = true;
            this.error = '';

            const params = new URLSearchParams({
                per_page: '100',
                kelas_id: String(this.kelasId)
            });

            fetch(`${config.restUrl}/jadwal-pelajaran?${params.toString()}`, {
                headers: { 'X-WP-Nonce': config.nonce }
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat jadwal pelajaran.');
                }

                return response.json();
            })
            .then((data) => {
                const items = Array.isArray(data) ? data : [];
                this.schedules = items.sort((a, b) => {
                    const dayOrder = this.days.indexOf(a.hari || '') - this.days.indexOf(b.hari || '');

                    if (dayOrder !== 0) {
                        return dayOrder;
                    }

                    return String(a.jam_mulai || '').localeCompare(String(b.jam_mulai || ''));
                });
            })
            .catch((error) => {
                this.error = error.message || 'Gagal memuat jadwal pelajaran.';
            })
            .finally(() => {
                this.loading = false;
            });
        },
        schedulesByDay() {
            return this.days.map((day) => ({
                day,
                items: this.schedules.filter((item) => item.hari === day)
            }));
        },
        timeSlots() {
            const slots = this.schedules.map((item) => ({
                key: `${item.jam_mulai || ''}-${item.jam_selesai || ''}`,
                jam_mulai: item.jam_mulai || '',
                jam_selesai: item.jam_selesai || ''
            }));

            return slots
                .filter((slot, index, list) => list.findIndex((entry) => entry.key === slot.key) === index)
                .sort((a, b) => String(a.jam_mulai).localeCompare(String(b.jam_mulai)));
        },
        scheduleByDayAndTime(day, slotKey) {
            return this.schedules.find((item) => item.hari === day && `${item.jam_mulai || ''}-${item.jam_selesai || ''}` === slotKey) || null;
        },
        className() {
            if (!this.kelasId) {
                return '-';
            }

            return config.siswaKelasNama || `Kelas #${this.kelasId}`;
        },
        subjectName(item) {
            return item?.mata_pelajaran?.nama || '-';
        },
        subjectColor(item) {
            return item?.mata_pelajaran?.kode_warna || '#f1f1f1';
        },
        subjectTextColor(item) {
            return item?.mata_pelajaran?.kode_warna ? '#ffffff' : '#333333';
        },
        formatTime(value) {
            if (!value) {
                return '-';
            }

            return String(value).slice(0, 5);
        },
        timeRange(item) {
            return `${this.formatTime(item.jam_mulai)} - ${this.formatTime(item.jam_selesai)}`;
        },
        timeRangeFromSlot(slot) {
            return `${this.formatTime(slot.jam_mulai)} - ${this.formatTime(slot.jam_selesai)}`;
        }
    }">
    <div class="elvd-table-panel" x-show="config.currentRole === 'siswa'">

        <div class="text-center mb-4">
            <h2 class="h4 mb-1"><?php echo esc_html__('Jadwal Pelajaran', 'elearning-vd'); ?></h2>
            <p class="text-muted mb-1">
                <span x-text="className()"></span>
            </p>
            <p class="text-muted mb-0"><?php echo esc_html($elvd_tahun_aktif ?: '-'); ?></p>
        </div>

        <div class="d-flex justify-content-end mb-3" x-show="config.currentRole === 'siswa' && kelasId">
            <a
                class="btn btn-danger"
                href="<?php echo esc_url(add_query_arg('elvd_download_jadwal', 'pdf')); ?>">
                <?php echo esc_html__('Download PDF', 'elearning-vd'); ?>
            </a>
        </div>

        <div class="alert alert-warning" x-show="config.currentRole !== 'siswa'">
            <?php echo esc_html__('Halaman ini hanya untuk siswa.', 'elearning-vd'); ?>
        </div>

        <div class="alert alert-warning" x-show="config.currentRole === 'siswa' && !kelasId">
            <?php echo esc_html__('Kelas siswa belum diatur.', 'elearning-vd'); ?>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="table-responsive" x-show="config.currentRole === 'siswa' && kelasId">
            <table class="table table-bordered align-top mb-0 elvd-table">
                <thead class="thead-dark">
                    <tr>
                        <th scope="col" class="text-center bg-dark text-white align-middle"><?php echo esc_html__('Waktu', 'elearning-vd'); ?></th>
                        <template x-for="day in days" :key="day">
                            <th scope="col" class="text-center bg-dark text-white align-middle" x-text="day"></th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loading">
                        <td :colspan="days.length + 1"><?php echo esc_html__('Memuat jadwal...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="slot in timeSlots()" :key="slot.key">
                        <tr x-show="!loading">
                            <td class="text-nowrap" x-text="timeRangeFromSlot(slot)"></td>
                            <template x-for="day in days" :key="`${slot.key}-${day}`">
                                <td class="p-0 text-center align-middle" :style="`background-color: ${subjectColor(scheduleByDayAndTime(day, slot.key))}; color: ${subjectTextColor(scheduleByDayAndTime(day, slot.key))};`">
                                    <template x-if="scheduleByDayAndTime(day, slot.key)">
                                        <div class="p-1" style="min-height: 1rem;min-width: 8rem;">
                                            <span class="fw-semibold" x-text="subjectName(scheduleByDayAndTime(day, slot.key))"></span>
                                        </div>
                                    </template>
                                    <template x-if="!scheduleByDayAndTime(day, slot.key)">
                                        <div class="text-muted small">-</div>
                                    </template>
                                </td>
                            </template>
                        </tr>
                    </template>
                    <tr x-show="!loading && schedules.length === 0">
                        <td :colspan="days.length + 1"><?php echo esc_html__('Belum ada jadwal pelajaran.', 'elearning-vd'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>