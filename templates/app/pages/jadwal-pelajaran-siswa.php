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
?>

<div
    x-show="active === 'jadwal-pelajaran-siswa'"
    x-data="{
        schedules: [],
        classes: [],
        subjects: [],
        teachers: [],
        loading: false,
        error: '',
        kelasId: Number(config.siswaKelasId || 0),
        days: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
        init() {
            this.fetchRelations();
            this.fetchSchedules();
        },
        fetchSchedules() {
            if (config.currentRole !== 'siswa' || !this.kelasId) {
                this.schedules = [];
                return;
            }

            this.loading = true;
            this.error = '';

            fetch(`${config.restUrl}/jadwal-pelajaran?per_page=100`, {
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
                this.schedules = items
                    .filter((item) => Number(item.kelas_id) === this.kelasId)
                    .sort((a, b) => {
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
        fetchRelations() {
            Promise.all([
                fetch(`${config.restUrl}/kelas?per_page=100`, { headers: { 'X-WP-Nonce': config.nonce } }),
                fetch(`${config.restUrl}/mata-pelajaran?per_page=100`, { headers: { 'X-WP-Nonce': config.nonce } })
            ])
            .then((responses) => {
                responses.forEach((response) => {
                    if (!response.ok) {
                        throw new Error('Gagal memuat data pendukung jadwal.');
                    }
                });

                return Promise.all(responses.map((response) => response.json()));
            })
            .then(([classes, subjects]) => {
                this.classes = Array.isArray(classes) ? classes : [];
                this.subjects = Array.isArray(subjects) ? subjects : [];
            })
            .catch((error) => {
                this.error = error.message || 'Gagal memuat data pendukung jadwal.';
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
        className(id) {
            const classItem = this.classes.find((item) => Number(item.id) === Number(id));
            return classItem ? classItem.nama : '-';
        },
        subjectName(id) {
            const subject = this.subjects.find((item) => Number(item.id) === Number(id));
            return subject ? subject.nama : '-';
        },
        teacherName(id) {
            const teacher = this.teachers.find((item) => Number(item.id) === Number(id));
            return teacher ? teacher.nama : '-';
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

        <div class="text-center mb-3">
            <h2 class="h5 mb-1"><?php echo esc_html__('Jadwal Pelajaran', 'elearning-vd'); ?></h2>
            <p class="text-muted mb-0">
                <span x-text="className(kelasId)"></span>
            </p>
        </div>

        <div class="alert alert-warning" x-show="config.currentRole !== 'siswa'">
            <?php echo esc_html__('Halaman ini hanya untuk siswa.', 'elearning-vd'); ?>
        </div>

        <div class="alert alert-warning" x-show="config.currentRole === 'siswa' && !kelasId">
            <?php echo esc_html__('Kelas siswa belum diatur.', 'elearning-vd'); ?>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="table-responsive" x-show="config.currentRole === 'siswa' && kelasId">
            <table class="table align-top mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col" class="text-center"><?php echo esc_html__('Waktu', 'elearning-vd'); ?></th>
                        <template x-for="day in days" :key="day">
                            <th scope="col" class="text-center" x-text="day"></th>
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
                                <td>
                                    <template x-if="scheduleByDayAndTime(day, slot.key)">
                                        <div>
                                            <div class="fw-semibold" x-text="subjectName(scheduleByDayAndTime(day, slot.key).mata_pelajaran_id)"></div>
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