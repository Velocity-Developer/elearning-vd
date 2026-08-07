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
    x-show="active === 'jadwal-pelajaran'"
    x-data="{
        schedules: [],
        classes: [],
        subjects: [],
        years: [],
        teachers: <?php echo esc_attr(wp_json_encode($elvd_guru_options)); ?>,
        loadingSchedules: false,
        loadingRelations: false,
        saving: false,
        modalOpen: false,
        error: '',
        filters: {
            guru_id: '',
            mata_pelajaran_id: '',
            tahun_ajaran_id: ''
        },
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
            this.fetchSchedules();
            this.fetchRelations();
        },
        fetchSchedules() {
            this.loadingSchedules = true;
            this.error = '';

            fetch(`${config.restUrl}/jadwal-pelajaran?per_page=100`, {
                headers: { 'X-WP-Nonce': config.nonce }
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
                    headers: { 'X-WP-Nonce': config.nonce }
                }),
                fetch(`${config.restUrl}/mata-pelajaran?per_page=100`, {
                    headers: { 'X-WP-Nonce': config.nonce }
                }),
                fetch(`${config.restUrl}/tahun-ajaran?per_page=100`, {
                    headers: { 'X-WP-Nonce': config.nonce }
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
                const matchTeacher = !this.filters.guru_id || Number(item.guru_id) === Number(this.filters.guru_id);
                const matchSubject = !this.filters.mata_pelajaran_id || Number(item.mata_pelajaran_id) === Number(this.filters.mata_pelajaran_id);
                const matchYear = !this.filters.tahun_ajaran_id || Number(item.tahun_ajaran_id) === Number(this.filters.tahun_ajaran_id);

                return matchTeacher && matchSubject && matchYear;
            });
        },
        syncItems() {
            this.$dispatch('elvd-items-updated', { items: this.filteredSchedules() });
        },
        resetFilters() {
            this.filters = {
                guru_id: '',
                mata_pelajaran_id: '',
                tahun_ajaran_id: ''
            };
            this.syncItems();
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
            const url = isEdit
                ? `${config.restUrl}/jadwal-pelajaran/${this.form.id}`
                : `${config.restUrl}/jadwal-pelajaran`;

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
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => {
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
        }
    }"
    @keydown.escape.window="closeModal()">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar align-items-end">
            <div class="row g-2 flex-grow-1">
                <div class="col-md-3">
                    <label class="form-label" for="elvd-filter-jadwal-tahun"><?php echo esc_html__('Filter Tahun Ajaran', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-filter-jadwal-tahun" x-model="filters.tahun_ajaran_id" @change="syncItems()" :disabled="loadingRelations">
                        <option value="" x-text="loadingRelations ? 'Memuat tahun...' : 'Semua tahun'"></option>
                        <template x-for="year in years" :key="year.id">
                            <option :value="String(year.id)" x-text="year.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="elvd-filter-jadwal-guru"><?php echo esc_html__('Filter Guru', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-filter-jadwal-guru" x-model="filters.guru_id" @change="syncItems()">
                        <option value=""><?php echo esc_html__('Semua guru', 'elearning-vd'); ?></option>
                        <template x-for="teacher in teachers" :key="teacher.id">
                            <option :value="String(teacher.id)" x-text="teacher.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="elvd-filter-jadwal-mapel"><?php echo esc_html__('Filter Mapel', 'elearning-vd'); ?></label>
                    <select class="form-select" id="elvd-filter-jadwal-mapel" x-model="filters.mata_pelajaran_id" @change="syncItems()" :disabled="loadingRelations">
                        <option value="" x-text="loadingRelations ? 'Memuat mapel...' : 'Semua mapel'"></option>
                        <template x-for="subject in subjects" :key="subject.id">
                            <option :value="String(subject.id)" x-text="subject.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary" @click="resetFilters()">
                        <?php echo esc_html__('Reset Filter', 'elearning-vd'); ?>
                    </button>
                </div>
            </div>
            <button
                type="button"
                class="btn btn-primary elvd-action-button"
                x-show="config.isManager"
                @click="openCreate()">
                <?php echo esc_html__('Tambah Jadwal', 'elearning-vd'); ?>
            </button>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Guru', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Hari', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Jam', 'elearning-vd'); ?></th>
                        <th scope="col" class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingSchedules">
                        <td colspan="7"><?php echo esc_html__('Memuat data jadwal pelajaran...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="item in filteredSchedules()" :key="item.id">
                        <tr>
                            <td>
                                <strong x-text="className(item.kelas_id)"></strong>
                            </td>
                            <td x-text="subjectName(item.mata_pelajaran_id)"></td>
                            <td x-text="teacherName(item.guru_id)"></td>
                            <td x-text="yearName(item.tahun_ajaran_id)"></td>
                            <td x-text="item.hari || '-'"></td>
                            <td x-text="timeRange(item)"></td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary elvd-row-action"
                                    x-show="config.isManager"
                                    @click="openEdit(item)">
                                    <?php echo esc_html__('Edit', 'elearning-vd'); ?>
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
                    <button type="button" class="btn btn-outline-secondary" @click="closeModal()">
                        <?php echo esc_html__('Batal', 'elearning-vd'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary elvd-action-button" :disabled="saving">
                        <span x-show="!saving"><?php echo esc_html__('Simpan', 'elearning-vd'); ?></span>
                        <span x-show="saving"><?php echo esc_html__('Menyimpan...', 'elearning-vd'); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>