<?php

defined('ABSPATH') || exit;
?>

<div
    x-show="active === 'materi'"
    x-data='{
        matters: [],
        classes: [],
        subjects: [],
        years: [],
        restUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url("wp/v2/elvd_materi")))); ?>,
        mediaUrl: <?php echo esc_attr(wp_json_encode(untrailingslashit(rest_url("wp/v2/media")))); ?>,
        loadingMatters: false,
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
            tahun_ajaran_id: "",
            file_url: ""
        },
        init() {
            this.fetchMatters();
            this.fetchRelations();
        },
        fetchMatters() {
            this.loadingMatters = true;
            this.error = "";

            fetch(`${this.restUrl}?per_page=100`, {
                headers: { "X-WP-Nonce": config.nonce }
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Gagal memuat data materi.");
                }

                return response.json();
            })
            .then((data) => {
                this.matters = Array.isArray(data) ? data : [];
                this.$dispatch("elvd-items-updated", { items: this.matters });
            })
            .catch((error) => {
                this.error = error.message || "Gagal memuat data materi.";
            })
            .finally(() => {
                this.loadingMatters = false;
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
                }),
                fetch(`${config.restUrl}/tahun-ajaran?per_page=100`, {
                    headers: { "X-WP-Nonce": config.nonce }
                })
            ])
            .then((responses) => {
                responses.forEach((response) => {
                    if (!response.ok) {
                        throw new Error("Gagal memuat data pilihan materi.");
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
                this.error = error.message || "Gagal memuat data pilihan materi.";
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
                tahun_ajaran_id: "",
                file_url: ""
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
                tahun_ajaran_id: this.metaValue(item, "elvd_tahun_ajaran_id") ? String(this.metaValue(item, "elvd_tahun_ajaran_id")) : "",
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
                    elvd_kelas_id: this.form.kelas_id ? Number(this.form.kelas_id) : 0,
                    elvd_mata_pelajaran_id: this.form.mata_pelajaran_id ? Number(this.form.mata_pelajaran_id) : 0,
                    elvd_tahun_ajaran_id: this.form.tahun_ajaran_id ? Number(this.form.tahun_ajaran_id) : 0,
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
                    throw new Error(data?.message || "Gagal menyimpan data materi.");
                }

                if (isEdit) {
                    this.matters = this.matters.map((item) => Number(item.id) === Number(data.id) ? data : item);
                } else {
                    this.matters = [data, ...this.matters];
                }

                this.$dispatch("elvd-items-updated", { items: this.matters });
                this.modalOpen = false;
                this.resetForm();
            })
            .catch((error) => {
                this.error = error.message || "Gagal menyimpan data materi.";
            })
            .finally(() => {
                this.saving = false;
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
        subjectName(id) {
            const subject = this.subjects.find((item) => Number(item.id) === Number(id));

            return subject ? subject.nama : "-";
        },
        yearName(id) {
            const year = this.years.find((item) => Number(item.id) === Number(id));

            return year ? year.nama : "-";
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
                <?php echo esc_html__('Tambah Materi', 'elearning-vd'); ?>
            </button>
        </div>

        <div class="alert alert-danger" x-show="error" x-text="error"></div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 elvd-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Judul', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></th>
                        <th scope="col"><?php echo esc_html__('Konten', 'elearning-vd'); ?></th>
                        <th scope="col" class="text-end"><?php echo esc_html__('Aksi', 'elearning-vd'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingMatters">
                        <td colspan="6"><?php echo esc_html__('Memuat data materi...', 'elearning-vd'); ?></td>
                    </tr>
                    <template x-for="item in matters" :key="item.id">
                        <tr>
                            <td>
                                <strong x-text="titleOf(item)"></strong>
                                <div class="elvd-subtext" x-show="hasFile(item)">
                                    <a
                                        :href="metaValue(item, 'elvd_file_url')"
                                        target="_blank"
                                        rel="noopener"
                                        x-text="metaValue(item, 'elvd_file_url')"></a>
                                </div>
                            </td>
                            <td x-text="yearName(metaValue(item, 'elvd_tahun_ajaran_id'))"></td>
                            <td x-text="className(metaValue(item, 'elvd_kelas_id'))"></td>
                            <td x-text="subjectName(metaValue(item, 'elvd_mata_pelajaran_id'))"></td>
                            <td x-text="shortDescription(contentOf(item))"></td>
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
                    <tr x-show="!loadingMatters && matters.length === 0">
                        <td colspan="6"><?php echo esc_html__('Belum ada materi.', 'elearning-vd'); ?></td>
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
                    <h2 class="modal-title" x-text="form.id ? 'Edit Materi' : 'Tambah Materi'"></h2>
                    <button
                        type="button"
                        class="btn-close"
                        aria-label="<?php echo esc_attr__('Tutup', 'elearning-vd'); ?>"
                        @click="closeModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="elvd-materi-judul"><?php echo esc_html__('Judul Materi', 'elearning-vd'); ?></label>
                        <input
                            type="text"
                            class="form-control"
                            id="elvd-materi-judul"
                            x-model="form.judul"
                            required
                            maxlength="200"
                            placeholder="<?php echo esc_attr__('Contoh: Bab 1 - Bilangan', 'elearning-vd'); ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="elvd-materi-tahun-ajaran"><?php echo esc_html__('Tahun Ajaran', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-materi-tahun-ajaran" x-model="form.tahun_ajaran_id" required :disabled="loadingRelations" @change="onYearChange()">
                                <option value="" x-text="loadingRelations ? 'Memuat tahun ajaran...' : 'Pilih tahun ajaran'"></option>
                                <template x-for="year in years" :key="year.id">
                                    <option :value="String(year.id)" x-text="year.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="elvd-materi-kelas"><?php echo esc_html__('Kelas', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-materi-kelas" x-model="form.kelas_id" required :disabled="!form.tahun_ajaran_id || loadingRelations">
                                <option value="" x-text="filteredClasses().length ? 'Pilih kelas' : 'Pilih tahun ajaran dahulu'"></option>
                                <template x-for="classItem in filteredClasses()" :key="classItem.id">
                                    <option :value="String(classItem.id)" x-text="classItem.nama"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="elvd-materi-mapel"><?php echo esc_html__('Mata Pelajaran', 'elearning-vd'); ?></label>
                            <select class="form-select" id="elvd-materi-mapel" x-model="form.mata_pelajaran_id" required :disabled="loadingRelations">
                                <option value="" x-text="loadingRelations ? 'Memuat mapel...' : 'Pilih mata pelajaran'"></option>
                                <template x-for="subject in subjects" :key="subject.id">
                                    <option :value="String(subject.id)" x-text="subject.nama"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label" for="elvd-materi-file"><?php echo esc_html__('Berkas Materi', 'elearning-vd'); ?></label>
                        <div class="input-group">
                            <input
                                type="url"
                                class="form-control"
                                id="elvd-materi-file"
                                x-model="form.file_url"
                                placeholder="<?php echo esc_attr__('Tempel URL berkas di sini...', 'elearning-vd'); ?>">
                            <button
                                type="button"
                                class="btn btn-outline-secondary elvd-text-button"
                                @click="$refs.materiFileInput.click()"
                                :disabled="uploadingFile">
                                <span x-show="!uploadingFile"><?php echo esc_html__('Pilih Berkas', 'elearning-vd'); ?></span>
                                <span x-show="uploadingFile"><?php echo esc_html__('Mengunggah...', 'elearning-vd'); ?></span>
                            </button>
                        </div>
                        <input
                            type="file"
                            class="d-none"
                            x-ref="materiFileInput"
                            @change="uploadFile($event)">
                        <div class="form-text">
                            <?php echo esc_html__('Isi URL manual atau upload berkas (PDF/doc/gambar).', 'elearning-vd'); ?>
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="elvd-materi-konten"><?php echo esc_html__('Konten Materi', 'elearning-vd'); ?></label>
                        <textarea
                            class="form-control"
                            id="elvd-materi-konten"
                            x-model="form.konten"
                            rows="5"
                            placeholder="<?php echo esc_attr__('Isi ringkasan materi di sini.', 'elearning-vd'); ?>"></textarea>
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