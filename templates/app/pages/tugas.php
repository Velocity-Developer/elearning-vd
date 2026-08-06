<?php

defined('ABSPATH') || exit;
?>

<div
    x-show="active === 'tugas'"
    x-data='{
        tasks: [],
        classes: [],
        subjects: [],
        restUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url("wp/v2/elvd_tugas")))); ?>,
        mediaUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url("wp/v2/media")))); ?>,
        loadingTasks: false,
        loadingRelations: false,
        uploadingFile: false,
        saving: false,
        modalOpen: false,
        error: "",
        form: {
            id: null,
            judul: "",
            konten: "",
            kelas_id: "",
            mata_pelajaran_id: "",
            deadline: "",
            instruksi: "",
            file_url: ""
        },
        init() {
            this.fetchTasks();
            this.fetchRelations();
        },
        fetchTasks() {
            this.loadingTasks = true;
            this.error = "";

            fetch(`${this.restUrl}?per_page=100`, {
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
            .then(([classes, subjects]) => {
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
                kelas_id: "",
                mata_pelajaran_id: "",
                deadline: "",
                instruksi: ""
            };
        },
        openCreate() {
            this.resetForm();
            this.error = "";
            this.modalOpen = true;
        },
        openEdit(item) {
            this.form = {
                id: Number(item.id),
                judul: this.titleOf(item),
                konten: this.contentOf(item),
                kelas_id: this.metaValue(item, "elvd_kelas_id") ? String(this.metaValue(item, "elvd_kelas_id")) : "",
                mata_pelajaran_id: this.metaValue(item, "elvd_mata_pelajaran_id") ? String(this.metaValue(item, "elvd_mata_pelajaran_id")) : "",
                deadline: this.metaValue(item, "elvd_deadline"),
                instruksi: this.metaValue(item, "elvd_instruksi")
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
        className(id) {
            const classItem = this.classes.find((item) => Number(item.id) === Number(id));

            return classItem ? classItem.nama : "-";
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
    @keydown.escape.window="closeModal()">
    <div class="elvd-table-panel">
        <div class="elvd-resource-toolbar">
            <div></div>
            <button
                type="button"
                class="btn btn-primary elvd-action-button"
                x-show="config.isManager"
                @click="openCreate()">
                <?php echo esc_html__('Tambah Tugas', 'elearning-vd'); ?>
            </button>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Judul', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Deadline', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Instruksi', 'elearning-vd'); ?></th>
                        <th scope="col" class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingTasks">
                        <td colspan="6"><?php echo esc_html__('Memuat data tugas...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="item in tasks" :key="item.id">
                        <tr>
                            <td>
                                <strong x-text="titleOf(item)"></strong>
                                <div class="elvd-subtext" x-show="contentOf(item)" x-text="shortDescription(contentOf(item))"></div>
                                <div class="elvd-subtext" x-show="hasFile(item)">
                                    <a
                                        :href="metaValue(item, 'elvd_file_url')"
                                        target="_blank"
                                        rel="noopener"
                                        x-text="metaValue(item, 'elvd_file_url')"></a>
                                </div>
                            </td>
                            <td x-text="className(metaValue(item, 'elvd_kelas_id'))"></td>
                            <td x-text="subjectName(metaValue(item, 'elvd_mata_pelajaran_id'))"></td>
                            <td x-text="deadlineLabel(metaValue(item, 'elvd_deadline'))"></td>
                            <td x-text="shortDescription(metaValue(item, 'elvd_instruksi'))"></td>
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
                    <tr x-show="!loadingTasks && tasks.length === 0">
                        <td colspan="6"><?php echo esc_html__('Belum ada tugas.', 'elearning-vd'); ?></td>
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
                        <div class="col-md-6">
                            <label class="form-label" for="elvd-tugas-kelas"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-tugas-kelas" x-model="form.kelas_id" required :disabled="loadingRelations">
                                <option value="" x-text="loadingRelations ? 'Memuat kelas...' : 'Pilih kelas'"></option>
                                <template x-for="classItem in classes" :key="classItem.id">
                                    <option :value="String(classItem.id)" x-text="classItem.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-6">
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