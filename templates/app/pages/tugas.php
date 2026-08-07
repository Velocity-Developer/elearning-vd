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
    x-show="active === 'tugas'"
    x-data='{
        tasks: [],
        classes: [],
        subjects: [],
        years: [],
        teachers: <?php echo esc_attr(wp_json_encode($elvd_guru_options)); ?>,
        restUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url("wp/v2/elvd_tugas")))); ?>,
        mediaUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url("wp/v2/media")))); ?>,
        loadingTasks: false,
        loadingRelations: false,
        uploadingFile: false,
        saving: false,
        modalOpen: false,
        preview: null,
        openPreview(item) {
            this.preview = item;
        },
        closePreview() {
            this.preview = null;
        },
        error: "",
        filters: {
            judul: "",
            mata_pelajaran_id: "",
            kelas_id: "",
            guru_id: ""
        },
        page: 1,
        perPage: 20,
        totalPages() {
            return Math.max(1, Math.ceil(this.filteredTasks().length / this.perPage));
        },
        pageItems() {
            const start = (this.page - 1) * this.perPage;

            return this.filteredTasks().slice(start, start + this.perPage);
        },
        goPage(p) {
            this.page = Math.min(Math.max(1, p), this.totalPages());
        },
        resetPage() {
            this.page = 1;
        },
        filteredTasks() {
            const judul = this.filters.judul.trim().toLowerCase();

            return this.tasks.filter((item) => {
                if (config.currentRole === "guru" && Number(item.author) !== Number(config.userId)) {
                    return false;
                }
                if (config.currentRole === "siswa" && Number(this.metaValue(item, "elvd_kelas_id")) !== Number(config.siswaKelasId)) {
                    return false;
                }

                const matchJudul = !judul || this.titleOf(item).toLowerCase().includes(judul);
                const matchMapel = !this.filters.mata_pelajaran_id || Number(this.metaValue(item, "elvd_mata_pelajaran_id")) === Number(this.filters.mata_pelajaran_id);
                const matchKelas = !this.filters.kelas_id || Number(this.metaValue(item, "elvd_kelas_id")) === Number(this.filters.kelas_id);
                const matchGuru = !this.filters.guru_id || Number(item.author) === Number(this.filters.guru_id);

                return matchJudul && matchMapel && matchKelas && matchGuru;
            });
        },
        form: {
            id: null,
            judul: "",
            konten: "",
            tahun_ajaran_id: "",
            kelas_id: "",
            mata_pelajaran_id: "",
            deadline: "",
            instruksi: "",
            file_url: ""
        },
        init() {
            this.fetchTasks();
            this.fetchRelations();
            this.$watch("filters.judul", () => this.resetPage());
            this.$watch("filters.mata_pelajaran_id", () => this.resetPage());
            this.$watch("filters.kelas_id", () => this.resetPage());
            this.$watch("filters.guru_id", () => this.resetPage());
        },
        fetchTasks() {
            this.loadingTasks = true;
            this.error = "";

            const params = new URLSearchParams({ per_page: "100", _embed: "true" });

            if (config.currentRole === "guru" && config.userId) {
                params.set("author", String(config.userId));
            }

            fetch(`${this.restUrl}?${params.toString()}`, {
                headers: { "X-WP-Nonce": config.nonce }
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Gagal memuat data tugas.");
                }

                return response.json();
            })
            .then((data) => {
                this.tasks = Array.isArray(data) ? data : [];
                this.$dispatch("elvd-items-updated", { items: this.tasks });
            })
            .catch((error) => {
                this.error = error.message || "Gagal memuat data tugas.";
            })
            .finally(() => {
                this.loadingTasks = false;
            });
        },
        fetchRelations() {
            this.loadingRelations = true;

            Promise.all([
                fetch(`${config.restUrl}/tahun-ajaran?per_page=100`, {
                    headers: { "X-WP-Nonce": config.nonce }
                }),
                fetch(`${config.restUrl}/kelas?per_page=100`, {
                    headers: { "X-WP-Nonce": config.nonce }
                }),
                fetch(`${config.restUrl}/mata-pelajaran?per_page=100`, {
                    headers: { "X-WP-Nonce": config.nonce }
                })
            ])
            .then((responses) => {
                responses.forEach((response) => {
                    if (!response.ok) {
                        throw new Error("Gagal memuat data pilihan tugas.");
                    }
                });

                return Promise.all(responses.map((response) => response.json()));
            })
            .then(([years, classes, subjects]) => {
                this.years = Array.isArray(years) ? years : [];
                this.classes = Array.isArray(classes) ? classes : [];
                this.subjects = Array.isArray(subjects) ? subjects : [];
            })
            .catch((error) => {
                this.error = error.message || "Gagal memuat data pilihan tugas.";
            })
            .finally(() => {
                this.loadingRelations = false;
            });
        },
        metaValue(item, key) {
            return item.meta && item.meta[key] ? item.meta[key] : "";
        },
        titleOf(item) {
            return (item.title && (item.title.rendered || item.title.raw)) ? (item.title.rendered || item.title.raw) : "-";
        },
        authorName(item) {
            if (item._embedded && item._embedded.author && item._embedded.author[0]) {
                return item._embedded.author[0].display_name || "-";
            }

            return "-";
        },
        contentOf(item) {
            if (item.content && item.content.raw) {
                return item.content.raw;
            }

            if (item.content && item.content.rendered) {
                return this.plain_text(item.content.rendered);
            }

            return "";
        },
        plain_text(html) {
            const div = document.createElement("div");
            div.innerHTML = html;
            return div.textContent || div.innerText || "";
        },
        shortDescription(value) {
            if (!value) {
                return "-";
            }

            return value.length > 140 ? `${value.slice(0, 140)}...` : value;
        },
        resetForm() {
            this.form = {
                id: null,
                judul: "",
                konten: "",
                tahun_ajaran_id: "",
                kelas_id: "",
                mata_pelajaran_id: "",
                deadline: "",
                instruksi: "",
                file_url: ""
            };
        },
        openCreate() {
            this.resetForm();
            this.applyDefaultCreate();
            this.error = "";
            this.modalOpen = true;
        },
        applyDefaultCreate() {
            if (this.years.length === 0 || this.classes.length === 0) {
                return;
            }

            if (!this.form.tahun_ajaran_id) {
                const aktif = this.years.find((year) => year.status === "aktif");

                if (aktif) {
                    this.form.tahun_ajaran_id = String(aktif.id);
                }
            }

            if (this.form.tahun_ajaran_id) {
                const optionClasses = this.classes.filter((item) => Number(item.tahun_ajaran_id) === Number(this.form.tahun_ajaran_id));

                if (optionClasses.length > 0) {
                    this.form.kelas_id = String(optionClasses[0].id);
                }
            }
        },
        openEdit(item) {
            this.form = {
                id: Number(item.id),
                judul: this.titleOf(item),
                konten: this.contentOf(item),
                tahun_ajaran_id: this.metaValue(item, "elvd_tahun_ajaran_id") ? String(this.metaValue(item, "elvd_tahun_ajaran_id")) : "",
                kelas_id: this.metaValue(item, "elvd_kelas_id") ? String(this.metaValue(item, "elvd_kelas_id")) : "",
                mata_pelajaran_id: this.metaValue(item, "elvd_mata_pelajaran_id") ? String(this.metaValue(item, "elvd_mata_pelajaran_id")) : "",
                deadline: this.metaValue(item, "elvd_deadline"),
                instruksi: this.metaValue(item, "elvd_instruksi"),
                file_url: this.metaValue(item, "elvd_file_url")
            };
            this.error = "";
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
            this.error = "";

            const isEdit = Boolean(this.form.id);
            const url = isEdit
                ? `${this.restUrl}/${this.form.id}`
                : this.restUrl;

            const payload = {
                title: this.form.judul,
                content: this.form.konten,
                status: "publish",
                meta: {
                    elvd_tahun_ajaran_id: this.form.tahun_ajaran_id ? Number(this.form.tahun_ajaran_id) : 0,
                    elvd_kelas_id: this.form.kelas_id ? Number(this.form.kelas_id) : 0,
                    elvd_mata_pelajaran_id: this.form.mata_pelajaran_id ? Number(this.form.mata_pelajaran_id) : 0,
                    elvd_deadline: this.form.deadline,
                    elvd_instruksi: this.form.instruksi,
                    elvd_file_url: this.form.file_url
                }
            };

            fetch(url, {
                method: isEdit ? "PUT" : "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": config.nonce
                },
                body: JSON.stringify(payload)
            })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => {
                if (!response.ok) {
                    throw new Error(data?.message || "Gagal menyimpan data tugas.");
                }

                if (isEdit) {
                    this.tasks = this.tasks.map((item) => Number(item.id) === Number(data.id) ? data : item);
                } else {
                    this.tasks = [data, ...this.tasks];
                }

                this.$dispatch("elvd-items-updated", { items: this.tasks });
                this.modalOpen = false;
                this.resetForm();
            })
            .catch((error) => {
                this.error = error.message || "Gagal menyimpan data tugas.";
            })
            .finally(() => {
                this.saving = false;
            });
        },
        deleteTask(item) {
            if (!window.confirm("Yakin hapus tugas ini?")) {
                return;
            }

            fetch(`${this.restUrl}/${item.id}`, {
                method: "DELETE",
                headers: { "X-WP-Nonce": config.nonce }
            })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response }) => {
                if (!response.ok) {
                    throw new Error("Gagal menghapus tugas.");
                }

                this.tasks = this.tasks.filter((task) => Number(task.id) !== Number(item.id));
                this.$dispatch("elvd-items-updated", { items: this.tasks });

                if (this.preview && Number(this.preview.id) === Number(item.id)) {
                    this.closePreview();
                }
            })
            .catch((error) => {
                this.error = error.message || "Gagal menghapus tugas.";
            });
        },
        className(id) {
            const classItem = this.classes.find((item) => Number(item.id) === Number(id));

            return classItem ? classItem.nama : "-";
        },
        filteredClasses() {
            return this.classes.filter((item) => Number(item.tahun_ajaran_id) === Number(this.form.tahun_ajaran_id));
        },
        onYearChange() {
            this.form.kelas_id = "";
        },
        yearName(id) {
            const year = this.years.find((item) => Number(item.id) === Number(id));

            return year ? year.nama : "-";
        },
        subjectName(id) {
            const subject = this.subjects.find((item) => Number(item.id) === Number(id));

            return subject ? subject.nama : "-";
        },
        deadlineLabel(value) {
            return value ? value : "-";
        },
        hasFile(item) {
            return Boolean(this.metaValue(item, "elvd_file_url"));
        },
        uploadFile(event) {
            const file = event.target.files && event.target.files[0];

            if (!file) {
                return;
            }

            this.uploadingFile = true;
            this.error = "";

            const formData = new FormData();
            formData.append("file", file);

            fetch(this.mediaUrl, {
                method: "POST",
                headers: { "X-WP-Nonce": config.nonce },
                body: formData
            })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => {
                if (!response.ok) {
                    throw new Error(data?.message || "Gagal mengunggah berkas.");
                }

                this.form.file_url = data.source_url || (data.guid && data.guid.rendered) || "";
            })
            .catch((error) => {
                this.error = error.message || "Gagal mengunggah berkas.";
            })
            .finally(() => {
                this.uploadingFile = false;
                event.target.value = "";
            });
        }
    }'
    @keydown.escape.window="closeModal(); closePreview()">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar align-items-start flex-wrap">
            <div class="row g-2 flex-grow-1">
                <div class="col-md-4">
                    <input
                        type="search"
                        class="form-control elvd-filter-input"
                        x-model="filters.judul"
                        placeholder="<?php echo esc_attr__('Cari judul tugas', 'elearning-vd'); ?>">
                </div>
                <div class="col-md-4">
                    <select class="form-select elvd-filter-input" x-model="filters.mata_pelajaran_id" :disabled="loadingRelations">
                        <option value="" x-text="loadingRelations ? 'Memuat mapel...' : 'Semua mapel'"></option>
                        <template x-for="subject in subjects" :key="subject.id">
                            <option :value="String(subject.id)" x-text="subject.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select elvd-filter-input" x-model="filters.kelas_id" :disabled="loadingRelations">
                        <option value="" x-text="loadingRelations ? 'Memuat kelas...' : 'Semua kelas'"></option>
                        <template x-for="classItem in classes" :key="classItem.id">
                            <option :value="String(classItem.id)" x-text="classItem.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-4" x-show="config.currentRole === 'administrator' || config.currentRole === 'siswa'">
                    <select class="form-select elvd-filter-input" x-model="filters.guru_id">
                        <option value=""><?php echo esc_html__('Semua guru', 'elearning-vd'); ?></option>
                        <template x-for="teacher in teachers" :key="teacher.id">
                            <option :value="String(teacher.id)" x-text="teacher.nama"></option>
                        </template>
                    </select>
                </div>
            </div>
            <button
                type="button"
                class="btn btn-primary elvd-action-button"
                x-show="config.isManager"
                @click="openCreate()">
                <i class="bi bi-plus-lg me-1"></i><?php echo esc_html__('Tambah Tugas', 'elearning-vd'); ?>
            </button>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Judul', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('MaPel', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Deadline', 'elearning-vd'); ?></th>
                        <th scope="col" class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingTasks">
                        <td colspan="6"><?php echo esc_html__('Memuat data tugas...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="item in pageItems()" :key="item.id">
                        <tr>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-link p-0 fw-normal text-start"
                                    @click="openPreview(item)"
                                    x-text="titleOf(item)">
                                </button>
                                <small
                                    class="d-block text-muted"
                                    x-show="config.currentRole === 'administrator' || config.currentRole === 'siswa'">
                                    <?php echo esc_html__('Penulis', 'elearning-vd'); ?>: <span x-text="authorName(item)"></span>
                                </small>
                            </td>
                            <td x-text="className(metaValue(item, 'elvd_kelas_id'))"></td>
                            <td x-text="subjectName(metaValue(item, 'elvd_mata_pelajaran_id'))"></td>
                            <td x-text="deadlineLabel(metaValue(item, 'elvd_deadline'))"></td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-info elvd-row-action"
                                    x-show="config.isManager"
                                    @click="openEdit(item)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger elvd-row-action"
                                    x-show="config.currentRole === 'administrator' || Number(item.author) === Number(config.userId)"
                                    @click="deleteTask(item)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loadingTasks && filteredTasks().length === 0">
                        <td colspan="7"><?php echo esc_html__('Belum ada tugas.', 'elearning-vd'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3"
            x-show="!loadingTasks && totalPages() > 1"
            aria-label="<?php echo esc_attr__('Paginasi tugas', 'elearning-vd'); ?>">
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
                    <h2 class="modal-title" x-text="form.id ? 'Edit Tugas' : 'Tambah Tugas'"></h2>
                    <button
                        type="button"
                        class="btn-close"
                        aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>"
                        @click="closeModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="elvd-tugas-judul"><?php echo esc_html__('Judul Tugas', 'elearning-vd'); ?></label>
                        <input
                            type="text"
                            class="form-control"
                            id="elvd-tugas-judul"
                            x-model="form.judul"
                            required
                            maxlength="200"
                            placeholder="<?php echo esc_attr__('Contoh: Tugas 1 - Eksponen', 'elearning-vd'); ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="elvd-tugas-tahun-ajaran"><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-tugas-tahun-ajaran" x-model="form.tahun_ajaran_id" required :disabled="loadingRelations" @change="onYearChange()">
                                <option value="" x-text="loadingRelations ? 'Memuat tahun ajaran...' : 'Pilih tahun ajaran'"></option>
                                <template x-for="year in years" :key="year.id">
                                    <option :value="String(year.id)" x-text="year.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="elvd-tugas-kelas"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-tugas-kelas" x-model="form.kelas_id" required :disabled="!form.tahun_ajaran_id || loadingRelations">
                                <option value="" x-text="filteredClasses().length ? 'Pilih kelas' : 'Pilih tahun ajaran dahulu'"></option>
                                <template x-for="classItem in filteredClasses()" :key="classItem.id">
                                    <option :value="String(classItem.id)" x-text="classItem.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="elvd-tugas-mapel"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-tugas-mapel" x-model="form.mata_pelajaran_id" required :disabled="loadingRelations">
                                <option value="" x-text="loadingRelations ? 'Memuat mapel...' : 'Pilih mata pelajaran'"></option>
                                <template x-for="subject in subjects" :key="subject.id">
                                    <option :value="String(subject.id)" x-text="subject.nama"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label" for="elvd-tugas-deadline"><?php echo esc_html__('Deadline', 'elearning-vd'); ?></label>
                        <input
                            type="date"
                            class="form-control"
                            id="elvd-tugas-deadline"
                            x-model="form.deadline">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="elvd-tugas-instruksi"><?php echo esc_html__('Instruksi', 'elearning-vd'); ?></label>
                        <textarea
                            class="form-control"
                            id="elvd-tugas-instruksi"
                            x-model="form.instruksi"
                            rows="3"
                            placeholder="<?php echo esc_attr__('Petunjuk pengerjaan tugas.', 'elearning-vd'); ?>"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="elvd-tugas-file"><?php echo esc_html__('File Tugas', 'elearning-vd'); ?></label>
                        <div class="input-group">
                            <input
                                type="url"
                                class="form-control"
                                id="elvd-tugas-file"
                                x-model="form.file_url"
                                placeholder="<?php echo esc_attr__('Tempel URL file di sini...', 'elearning-vd'); ?>">
                            <button
                                type="button"
                                class="btn btn-outline-secondary elvd-text-button"
                                @click="$refs.tugasFileInput.click()"
                                :disabled="uploadingFile">
                                <span x-show="!uploadingFile"><?php echo esc_html__('Pilih File', 'elearning-vd'); ?></span>
                                <span x-show="uploadingFile"><?php echo esc_html__('Mengunggah...', 'elearning-vd'); ?></span>
                            </button>
                        </div>
                        <input
                            type="file"
                            class="d-none"
                            x-ref="tugasFileInput"
                            @change="uploadFile($event)">
                        <div class="form-text">
                            <?php echo esc_html__('Isi URL manual atau upload file (PDF/doc/gambar).', 'elearning-vd'); ?>
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="elvd-tugas-konten"><?php echo esc_html__('Konten Tugas', 'elearning-vd'); ?></label>
                        <textarea
                            class="form-control"
                            id="elvd-tugas-konten"
                            x-model="form.konten"
                            rows="5"
                            placeholder="<?php echo esc_attr__('Isi detail tugas di sini.', 'elearning-vd'); ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" @click="closeModal()">
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

    <div class="modal-backdrop fade show" x-show="preview" x-cloak></div>
    <div
        class="modal fade show elvd-modal"
        tabindex="-1"
        role="dialog"
        aria-modal="true"
        x-show="preview"
        x-cloak>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" x-text="preview ? titleOf(preview) : ''"></h2>
                    <button
                        type="button"
                        class="btn-close"
                        aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>"
                        @click="closePreview()"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-3">
                        <dt class="col-sm-4"><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></dt>
                        <dd class="col-sm-8" x-text="preview ? yearName(metaValue(preview, 'elvd_tahun_ajaran_id')) : '-'"></dd>
                        <dt class="col-sm-4"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></dt>
                        <dd class="col-sm-8" x-text="preview ? className(metaValue(preview, 'elvd_kelas_id')) : '-'"></dd>
                        <dt class="col-sm-4"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></dt>
                        <dd class="col-sm-8" x-text="preview ? subjectName(metaValue(preview, 'elvd_mata_pelajaran_id')) : '-'"></dd>
                        <dt class="col-sm-4"><?php echo esc_html__('Deadline', 'elearning-vd'); ?></dt>
                        <dd class="col-sm-8" x-text="preview ? deadlineLabel(metaValue(preview, 'elvd_deadline')) : '-'"></dd>
                    </dl>
                    <div class="mb-2" x-show="preview && metaValue(preview, 'elvd_instruksi')">
                        <strong><?php echo esc_html__('Instruksi', 'elearning-vd'); ?></strong>
                        <p class="mb-0 mt-1 text-muted" x-text="preview ? metaValue(preview, 'elvd_instruksi') : ''"></p>
                    </div>
                    <div class="mb-2">
                        <strong><?php echo esc_html__('Konten', 'elearning-vd'); ?></strong>
                        <p class="mb-0 mt-1 text-muted" x-text="preview ? (contentOf(preview) || '-') : '-'"></p>
                    </div>
                    <div x-show="preview && hasFile(preview)">
                        <strong><?php echo esc_html__('Berkas', 'elearning-vd'); ?></strong>
                        <div class="mt-1">
                            <a
                                :href="preview ? metaValue(preview, 'elvd_file_url') : '#'"
                                target="_blank"
                                rel="noopener"
                                x-text="preview ? metaValue(preview, 'elvd_file_url') : ''"></a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" @click="closePreview()">
                        <i class="bi bi-x-lg me-1"></i><?php echo esc_html__('Tutup', 'elearning-vd'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>