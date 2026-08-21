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
        timeRange(item) {
            const start = item.jam_mulai || '-';
            const end = item.jam_selesai || '-';
            return `${start} - ${end}`;
        }
    }">
    <div class="elvd-table-panel" x-show="config.currentRole === 'siswa'">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1"><?php echo esc_html__('Jadwal Pelajaran', 'elearning-vd'); ?></h2>
                <p class="text-muted mb-0"><?php echo esc_html__('Jadwal pelajaran Anda ditampilkan per hari.', 'elearning-vd'); ?></p>
            </div>
            <span class="badge text-bg-primary" x-text="className(kelasId)"></span>
        </div>

        <div class="alert alert-warning" x-show="config.currentRole !== 'siswa'">
            <?php echo esc_html__('Halaman ini hanya untuk siswa.', 'elearning-vd'); ?>
        </div>

        <div class="alert alert-warning" x-show="config.currentRole === 'siswa' && !kelasId">
            <?php echo esc_html__('Kelas siswa belum diatur.', 'elearning-vd'); ?>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="row g-3" x-show="config.currentRole === 'siswa' && kelasId">
            <template x-for="dayGroup in schedulesByDay()" :key="dayGroup.day">
                <div class="col-12 col-lg-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header fw-semibold" x-text="dayGroup.day"></div>
                        <div class="card-body">
                            <div x-show="loading" class="text-muted small">
                                <?php echo esc_html__('Memuat jadwal...', 'elearning-vd'); ?>
                            </div>

                            <template x-if="!loading && dayGroup.items.length === 0">
                                <div class="text-muted small"><?php echo esc_html__('Tidak ada jadwal.', 'elearning-vd'); ?></div>
                            </template>

                            <div class="d-flex flex-column gap-2" x-show="!loading && dayGroup.items.length > 0">
                                <template x-for="item in dayGroup.items" :key="item.id">
                                    <div class="border rounded p-3">
                                        <div class="fw-semibold" x-text="subjectName(item.mata_pelajaran_id)"></div>
                                        <div class="small text-muted" x-text="teacherName(item.guru_id)"></div>
                                        <div class="small text-muted" x-text="className(item.kelas_id)"></div>
                                        <div class="small mt-1" x-text="timeRange(item)"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>