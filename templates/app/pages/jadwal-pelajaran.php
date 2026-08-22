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

<script>
    window.elvdJadwalPelajaranConfig = {
        schedules: [],
        classes: [],
        subjects: [],
        years: [],
        teachers: <?php echo wp_json_encode($elvd_guru_options); ?>,
        loadingSchedules: false,
        loadingRelations: false,
        saving: false,
        modalOpen: false,
        error: '',
        viewMode: 'day',
        page: 1,
        perPage: 20,
        filters: {
            kelas_id: '',
            guru_id: '',
            mata_pelajaran_id: '',
            tahun_ajaran_id: ''
        },
        urlKelas: '',
        urlGuru: '',
        urlMapel: '',
        urlTahun: '',
        form: {
            id: null,
            kelas_id: '',
            mata_pelajaran_id: '',
            guru_id: '',
            tahun_ajaran_id: '',
            hari: '',
            jam_mulai: '',
            jam_selesai: ''
        },
        days: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
        init() {
            this.applyUrlFilters();
            this.fetchSchedules();
            this.fetchRelations();
            this.$watch('filters.kelas_id', () => {
                this.syncUrl();
                this.fetchSchedules();
            });
            this.$watch('filters.guru_id', () => {
                this.syncUrl();
                this.fetchSchedules();
            });
            this.$watch('filters.mata_pelajaran_id', () => {
                this.syncUrl();
                this.fetchSchedules();
            });
            this.$watch('filters.tahun_ajaran_id', () => {
                this.syncUrl();
                this.fetchSchedules();
            });
        },
        applyUrlFilters() {
            const params = new URLSearchParams(window.location.search);

            this.urlKelas = params.get('kelas') || '';
            this.urlGuru = params.get('guru') || '';
            this.urlMapel = params.get('mapel') || '';
            this.urlTahun = params.get('tahun') || '';
        },
        applyFiltersFromUrl() {
            if (this.urlKelas) {
                this.filters.kelas_id = this.urlKelas;
            }
            if (this.urlGuru) {
                this.filters.guru_id = this.urlGuru;
            } else if (config.currentRole === 'guru') {
                // Guru: default filter ke dirinya sendiri.
                this.filters.guru_id = String(config.userId);
            }
            if (this.urlMapel) {
                this.filters.mata_pelajaran_id = this.urlMapel;
            }
            if (this.urlTahun) {
                this.filters.tahun_ajaran_id = this.urlTahun;
                return;
            }

            const activeYear = this.years.find((year) => year.status === 'aktif');
            if (activeYear) {
                this.filters.tahun_ajaran_id = String(activeYear.id);
            }
        },
        syncUrl() {
            const url = new URL(window.location.href);
            const params = url.searchParams;
            const map = {
                kelas: this.filters.kelas_id,
                guru: this.filters.guru_id,
                mapel: this.filters.mata_pelajaran_id,
                tahun: this.filters.tahun_ajaran_id
            };

            Object.entries(map).forEach(([key, value]) => {
                if (value) {
                    params.set(key, value);
                } else {
                    params.delete(key);
                }
            });

            window.history.replaceState({}, '', url);
        },
        fetchSchedules() {
            this.loadingSchedules = true;
            this.error = '';

            const perPage = this.viewMode === 'day' ? 1000 : 100;
            const params = new URLSearchParams({
                per_page: String(perPage)
            });

            if (this.filters.kelas_id) {
                params.set('kelas_id', this.filters.kelas_id);
            }

            if (this.filters.guru_id) {
                params.set('guru_id', this.filters.guru_id);
            }

            if (this.filters.mata_pelajaran_id) {
                params.set('mata_pelajaran_id', this.filters.mata_pelajaran_id);
            }

            if (this.filters.tahun_ajaran_id) {
                params.set('tahun_ajaran_id', this.filters.tahun_ajaran_id);
            }

            fetch(`${config.restUrl}/jadwal-pelajaran?${params.toString()}`, {
                    headers: {
                        'X-WP-Nonce': config.nonce
                    }
                })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Gagal memuat data jadwal pelajaran.');
                    }

                    return response.json();
                })
                .then((data) => {
                    this.schedules = Array.isArray(data) ? data : [];
                    this.syncItems();
                })
                .catch((error) => {
                    this.error = error.message || 'Gagal memuat data jadwal pelajaran.';
                })
                .finally(() => {
                    this.loadingSchedules = false;
                });
        },
        fetchRelations() {
            this.loadingRelations = true;

            Promise.all([
                    fetch(`${config.restUrl}/kelas?per_page=100`, {
                        headers: {
                            'X-WP-Nonce': config.nonce
                        }
                    }),
                    fetch(`${config.restUrl}/mata-pelajaran?per_page=100`, {
                        headers: {
                            'X-WP-Nonce': config.nonce
                        }
                    }),
                    fetch(`${config.restUrl}/tahun-ajaran?per_page=100`, {
                        headers: {
                            'X-WP-Nonce': config.nonce
                        }
                    })
                ])
                .then((responses) => {
                    responses.forEach((response) => {
                        if (!response.ok) {
                            throw new Error('Gagal memuat data pilihan jadwal.');
                        }
                    });

                    return Promise.all(responses.map((response) => response.json()));
                })
                .then(([classes, subjects, years]) => {
                    this.classes = Array.isArray(classes) ? classes : [];
                    this.subjects = Array.isArray(subjects) ? subjects : [];
                    this.years = Array.isArray(years) ? years : [];
                    this.applyFiltersFromUrl();
                })
                .catch((error) => {
                    this.error = error.message || 'Gagal memuat data pilihan jadwal.';
                })
                .finally(() => {
                    this.loadingRelations = false;
                });
        },
        filteredSchedules() {
            return this.schedules.filter((item) => {
                const matchClass = !this.filters.kelas_id || Number(item.kelas_id) === Number(this.filters.kelas_id);
                const matchTeacher = !this.filters.guru_id || Number(item.guru_id) === Number(this.filters.guru_id);
                const matchSubject = !this.filters.mata_pelajaran_id || Number(item.mata_pelajaran_id) === Number(this.filters.mata_pelajaran_id);
                const matchYear = !this.filters.tahun_ajaran_id || Number(item.tahun_ajaran_id) === Number(this.filters.tahun_ajaran_id);

                return matchClass && matchTeacher && matchSubject && matchYear;
            });
        },
        totalPages() {
            return Math.max(1, Math.ceil(this.filteredSchedules().length / this.perPage));
        },
        pageItems() {
            const start = (this.page - 1) * this.perPage;

            return this.filteredSchedules().slice(start, start + this.perPage);
        },
        schedulesByDay() {
            return this.days.map((day) => ({
                day,
                items: this.filteredSchedules()
                    .filter((item) => item.hari === day)
                    .sort((a, b) => String(a.jam_mulai || '').localeCompare(String(b.jam_mulai || '')))
            }));
        },
        dayScheduleSlots() {
            const slots = [];

            this.filteredSchedules().forEach((item) => {
                const slot = this.timeRange(item);
                if (slot !== '-' && !slots.includes(slot)) {
                    slots.push(slot);
                }
            });

            return slots.sort((a, b) => String(a).localeCompare(String(b)));
        },
        dayScheduleItem(dayGroup, slot) {
            return dayGroup.items.find((item) => this.timeRange(item) === slot) || null;
        },
        goPage(p) {
            this.page = Math.min(Math.max(1, p), this.totalPages());
        },
        resetPage() {
            this.page = 1;
        },
        changeViewMode(mode) {
            this.viewMode = mode;
            this.resetPage();
            this.fetchSchedules();
        },
        syncItems() {
            this.$dispatch('elvd-items-updated', {
                items: this.filteredSchedules()
            });
        },
        resetFilters() {
            this.filters = {
                kelas_id: '',
                guru_id: '',
                mata_pelajaran_id: '',
                tahun_ajaran_id: ''
            };
            this.resetPage();
            this.fetchSchedules();
        },
        resetForm() {
            this.form = {
                id: null,
                kelas_id: '',
                mata_pelajaran_id: '',
                guru_id: '',
                tahun_ajaran_id: '',
                hari: '',
                jam_mulai: '',
                jam_selesai: ''
            };
        },
        openCreate() {
            this.resetForm();
            this.error = '';
            this.modalOpen = true;
        },
        openEdit(item) {
            this.form = {
                id: item.id,
                kelas_id: item.kelas_id ? String(item.kelas_id) : '',
                mata_pelajaran_id: item.mata_pelajaran_id ? String(item.mata_pelajaran_id) : '',
                guru_id: item.guru_id ? String(item.guru_id) : '',
                tahun_ajaran_id: item.tahun_ajaran_id ? String(item.tahun_ajaran_id) : '',
                hari: item.hari || '',
                jam_mulai: this.timeValue(item.jam_mulai),
                jam_selesai: this.timeValue(item.jam_selesai)
            };
            this.error = '';
            this.modalOpen = true;
        },
        closeModal() {
            if (this.saving) {
                return;
            }

            this.modalOpen = false;
        },
        submitForm() {
            this.saving = true;
            this.error = '';

            const isEdit = Boolean(this.form.id);
            const url = isEdit ?
                `${config.restUrl}/jadwal-pelajaran/${this.form.id}` :
                `${config.restUrl}/jadwal-pelajaran`;

            fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce
                    },
                    body: JSON.stringify({
                        kelas_id: Number(this.form.kelas_id),
                        mata_pelajaran_id: Number(this.form.mata_pelajaran_id),
                        guru_id: Number(this.form.guru_id),
                        tahun_ajaran_id: this.form.tahun_ajaran_id ? Number(this.form.tahun_ajaran_id) : 0,
                        hari: this.form.hari,
                        jam_mulai: this.form.jam_mulai,
                        jam_selesai: this.form.jam_selesai
                    })
                })
                .then((response) => response.json().then((data) => ({
                    response,
                    data
                })))
                .then(({
                    response,
                    data
                }) => {
                    if (!response.ok) {
                        throw new Error(data?.message || 'Gagal menyimpan data jadwal pelajaran.');
                    }

                    if (isEdit) {
                        this.schedules = this.schedules.map((item) => Number(item.id) === Number(data.id) ? data : item);
                    } else {
                        this.schedules = [data, ...this.schedules];
                    }

                    this.syncItems();
                    this.modalOpen = false;
                    this.resetForm();
                })
                .catch((error) => {
                    this.error = error.message || 'Gagal menyimpan data jadwal pelajaran.';
                })
                .finally(() => {
                    this.saving = false;
                });
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
        yearName(id) {
            const year = this.years.find((item) => Number(item.id) === Number(id));

            return year ? year.nama : '-';
        },
        timeValue(value) {
            if (!value) {
                return '';
            }

            return String(value).slice(0, 5);
        },
        timeRange(item) {
            const start = this.timeValue(item.jam_mulai);
            const end = this.timeValue(item.jam_selesai);

            return start && end ? `${start} - ${end}` : '-';
        },
        deleteSchedule(item) {
            if (!window.confirm('Yakin hapus jadwal pelajaran ini?')) {
                return;
            }

            fetch(`${config.restUrl}/jadwal-pelajaran/${item.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': config.nonce
                    }
                })
                .then((response) => response.json().then((data) => ({
                    response,
                    data
                })))
                .then(({
                    response
                }) => {
                    if (!response.ok) {
                        throw new Error('Gagal menghapus jadwal pelajaran.');
                    }

                    this.schedules = this.schedules.filter((schedule) => Number(schedule.id) !== Number(item.id));
                    this.syncItems();
                })
                .catch((error) => {
                    this.error = error.message || 'Gagal menghapus jadwal pelajaran.';
                });
        }
    };
</script>

<div
    x-show="active === 'jadwal-pelajaran'"
    x-data="window.elvdJadwalPelajaranConfig"
    @keydown.escape.window="closeModal()">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar align-items-end">
            <div class="row g-2 flex-grow-1">
                <div class="col-md-3">
                    <label class="form-label" for="elvd-filter-jadwal-kelas"><?php echo esc_html__('Filter Kelas', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-filter-jadwal-kelas" x-model="filters.kelas_id" @change="resetPage()" :disabled="loadingRelations">
                        <option value="" x-text="loadingRelations ? 'Memuat kelas...' : 'Semua kelas'"></option>
                        <template x-for="classItem in classes" :key="classItem.id">
                            <option :value="String(classItem.id)" x-text="classItem.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="elvd-filter-jadwal-tahun"><?php echo esc_html__('Filter Tahun Ajaran', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-filter-jadwal-tahun" x-model="filters.tahun_ajaran_id" @change="resetPage()" :disabled="loadingRelations">
                        <option value="" x-text="loadingRelations ? 'Memuat tahun...' : 'Semua tahun'"></option>
                        <template x-for="year in years" :key="year.id">
                            <option :value="String(year.id)" x-text="year.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="elvd-filter-jadwal-guru"><?php echo esc_html__('Filter Guru', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-filter-jadwal-guru" x-model="filters.guru_id" @change="resetPage()">
                        <option value=""><?php echo esc_html__('Semua guru', 'elearning-vd'); ?></option>
                        <template x-for="teacher in teachers" :key="teacher.id">
                            <option :value="String(teacher.id)" x-text="teacher.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="elvd-filter-jadwal-mapel"><?php echo esc_html__('Filter Mapel', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-filter-jadwal-mapel" x-model="filters.mata_pelajaran_id" @change="resetPage()" :disabled="loadingRelations">
                        <option value="" x-text="loadingRelations ? 'Memuat mapel...' : 'Semua mapel'"></option>
                        <template x-for="subject in subjects" :key="subject.id">
                            <option :value="String(subject.id)" x-text="subject.nama"></option>
                        </template>
                    </select>
                </div>
            </div>
        </div>

        <div class="mb-3 d-flex justify-content-between">
            <div class="form-check form-switch me-2">
                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="elvd-jadwal-view-mode"
                    :checked="viewMode === 'day'"
                    @change="viewMode = $event.target.checked ? 'day' : 'table'">
                <label class="form-check-label small" for="elvd-jadwal-view-mode">
                    <span x-text="viewMode === 'day' ? 'Per Hari' : 'Tabel'"></span>
                </label>
            </div>
            <div class="d-flex justify-content-end">
                <button
                    type="button"
                    class="btn btn-primary elvd-action-button"
                    x-show="config.currentRole === 'administrator'"
                    @click="openCreate()">
                    <?php echo esc_html__('Tambah Jadwal', 'elearning-vd'); ?>
                </button>
                <button type="button" class="btn btn-secondary ms-1" @click="resetFilters()">
                    <?php echo esc_html__('Reset Filter', 'elearning-vd'); ?>
                </button>
            </div>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="table-responsive" x-show="viewMode === 'table'">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Guru', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Hari', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Jam', 'elearning-vd'); ?></th>
                        <th scope="col" class="text-end" x-show="config.currentRole === 'administrator'"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingSchedules">
                        <td colspan="7"><?php echo esc_html__('Memuat data jadwal pelajaran...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="item in pageItems()" :key="item.id">
                        <tr>
                            <td class="d-flex align-items-center">
                                <span class="text-white px-2 rounded-circle me-1" style="width: 20px; height: 20px;" x-bind:style="{'backgroundColor': item.mata_pelajaran.kode_warna || '#dddddd'}"></span>
                                <span class="fw-medium" x-text="item.mata_pelajaran.nama"></span>
                            </td>
                            <td>
                                <div class="fw-medium" x-text="className(item.kelas_id)"></div>
                                <span class="small" x-text="yearName(item.tahun_ajaran_id)"></span>
                            </td>
                            <td x-text="teacherName(item.guru_id)"></td>
                            <td x-text="item.hari || '-'"></td>
                            <td x-text="timeRange(item)"></td>
                            <td class="text-end" x-show="config.currentRole === 'administrator'">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary elvd-row-action"
                                    x-show="config.currentRole === 'administrator'"
                                    @click="openEdit(item)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger elvd-row-action"
                                    x-show="config.currentRole === 'administrator'"
                                    @click="deleteSchedule(item)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loadingSchedules && filteredSchedules().length === 0">
                        <td colspan="7"><?php echo esc_html__('Belum ada jadwal pelajaran sesuai filter.', 'elearning-vd'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="table-responsive" x-show="viewMode === 'day'">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col" class="text-center"><?php echo esc_html__('Jam', 'elearning-vd'); ?></th>
                        <template x-for="dayGroup in schedulesByDay()" :key="dayGroup.day">
                            <th scope="col" class="text-center" x-text="dayGroup.day"></th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingSchedules">
                        <td :colspan="days.length + 1"><?php echo esc_html__('Memuat data jadwal pelajaran...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-if="!loadingSchedules && filteredSchedules().length > 0">
                        <template x-for="slot in dayScheduleSlots()" :key="`day-row-${slot}`">
                            <tr>
                                <td class="align-top text-nowrap fw-semibold" x-text="slot"></td>
                                <template x-for="dayGroup in schedulesByDay()" :key="`${dayGroup.day}-${slot}`">
                                    <td class="align-top">
                                        <template x-if="dayScheduleItem(dayGroup, slot)">
                                            <div class="elvd-day-schedule-item" x-data="{ item: dayScheduleItem(dayGroup, slot) }">
                                                <div class="fw-semibold" x-text="item.mata_pelajaran.nama"></div>
                                                <div class="small text-muted" x-text="className(item.kelas_id)"></div>
                                                <div class="small text-muted" x-text="teacherName(item.guru_id)"></div>
                                                <div class="elvd-day-schedule-actions" x-show="config.currentRole === 'administrator'">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-primary elvd-row-action"
                                                        @click="openEdit(item)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-danger elvd-row-action"
                                                        @click="deleteSchedule(item)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="!dayScheduleItem(dayGroup, slot)">
                                            <span class="text-muted small">-</span>
                                        </template>
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </template>
                    <tr x-show="!loadingSchedules && filteredSchedules().length === 0">
                        <td :colspan="days.length + 1"><?php echo esc_html__('Belum ada jadwal pelajaran sesuai filter.', 'elearning-vd'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3"
            x-show="viewMode === 'table' && !loadingSchedules && totalPages() > 1"
            aria-label="<?php echo esc_attr__('Paginasi jadwal pelajaran', 'elearning-vd'); ?>">
            <small class="text-muted">
                <?php echo esc_html__('Halaman', 'elearning-vd'); ?> <span x-text="page"></span> / <span x-text="totalPages()"></span>
            </small>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item" :class="page === 1 ? 'disabled' : ''">
                    <a class="page-link" href="#" @click.prevent="goPage(page - 1)">
                        <?php echo esc_html__('Sebelumnya', 'elearning-vd'); ?>
                    </a>
                </li>
                <template x-for="p in totalPages()" :key="p">
                    <li class="page-item" :class="page === p ? 'active' : ''">
                        <a class="page-link" href="#" @click.prevent="goPage(p)" x-text="p"></a>
                    </li>
                </template>
                <li class="page-item" :class="page === totalPages() ? 'disabled' : ''">
                    <a class="page-link" href="#" @click.prevent="goPage(page + 1)">
                        <?php echo esc_html__('Berikutnya', 'elearning-vd'); ?>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <div class="modal-backdrop fade show" x-show="modalOpen" x-cloak></div>
    <div
        class="modal fade show elvd-modal"
        tabindex="-1"
        role="dialog"
        aria-modal="true"
        x-show="modalOpen"
        x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" @submit.prevent="submitForm()">
                <div class="modal-header">
                    <h2 class="modal-title" x-text="form.id ? 'Edit Jadwal Pelajaran' : 'Tambah Jadwal Pelajaran'"></h2>
                    <button
                        type="button"
                        class="btn-close"
                        aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>"
                        @click="closeModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-jadwal-kelas"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-jadwal-kelas" x-model="form.kelas_id" required :disabled="loadingRelations">
                                <option value="" x-text="loadingRelations ? 'Memuat kelas...' : 'Pilih kelas'"></option>
                                <template x-for="classItem in classes" :key="classItem.id">
                                    <option :value="String(classItem.id)" x-text="classItem.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-jadwal-mapel"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-jadwal-mapel" x-model="form.mata_pelajaran_id" required :disabled="loadingRelations">
                                <option value="" x-text="loadingRelations ? 'Memuat mapel...' : 'Pilih mata pelajaran'"></option>
                                <template x-for="subject in subjects" :key="subject.id">
                                    <option :value="String(subject.id)" x-text="subject.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-jadwal-guru"><?php echo esc_html__('Guru', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-jadwal-guru" x-model="form.guru_id" required>
                                <option value=""><?php echo esc_html__('Pilih guru', 'elearning-vd'); ?></option>
                                <template x-for="teacher in teachers" :key="teacher.id">
                                    <option :value="String(teacher.id)" x-text="teacher.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-jadwal-tahun"><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-jadwal-tahun" x-model="form.tahun_ajaran_id" :disabled="loadingRelations">
                                <option value="" x-text="loadingRelations ? 'Memuat tahun...' : 'Pilih tahun ajaran'"></option>
                                <template x-for="year in years" :key="year.id">
                                    <option :value="String(year.id)" x-text="year.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-jadwal-hari"><?php echo esc_html__('Hari', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-jadwal-hari" x-model="form.hari" required>
                                <option value=""><?php echo esc_html__('Pilih hari', 'elearning-vd'); ?></option>
                                <template x-for="day in days" :key="day">
                                    <option :value="day" x-text="day"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-jadwal-jam-mulai"><?php echo esc_html__('Jam Mulai', 'elearning-vd'); ?></label>
                            <input
                                type="time"
                                class="form-control"
                                id="elvd-jadwal-jam-mulai"
                                x-model="form.jam_mulai"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-jadwal-jam-selesai"><?php echo esc_html__('Jam Selesai', 'elearning-vd'); ?></label>
                            <input
                                type="time"
                                class="form-control"
                                id="elvd-jadwal-jam-selesai"
                                x-model="form.jam_selesai"
                                required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeModal()">
                        <i class="bi bi-x-lg me-1"></i><?php echo esc_html__('Batal', 'elearning-vd'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary elvd-action-button" :disabled="saving">
                        <span x-show="!saving"><i class="bi bi-check-lg me-1"></i><?php echo esc_html__('Simpan', 'elearning-vd'); ?></span>
                        <span x-show="saving"><?php echo esc_html__('Menyimpan...', 'elearning-vd'); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>