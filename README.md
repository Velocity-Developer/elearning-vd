# elearning-vd

Plugin WordPress elearning untuk sekolah SD, SMP, dan SMA.

## Instalasi

### Via zip (rilis)

1. Unduh `elearning-vd.zip` dari halaman [Releases](https://github.com/Velocity-Developer/elearning-vd/releases).
2. Buka **wp-admin → Plugins → Add New → Upload Plugin**.
3. Pilih file zip, klik **Install Now**, lalu **Activate**.

### Via Composer

```bash
composer require velocitydeveloper/elearning-vd
```

### Via clone / manual

```bash
cd wp-content/plugins
git clone https://github.com/Velocity-Developer/elearning-vd.git
```

Lalu aktifkan plugin dari **wp-admin → Plugins**.

### Setelah Aktif

- Plugin otomatis membuat:
  - Custom user roles: `guru` dan `siswa`.
  - Custom post types: Tugas, Materi, Quiz, Quiz Question.
  - Custom database tables (tahun ajaran, kelas, mata pelajaran, jadwal, pengerjaan).
  - Halaman **Elearning** (`/elearning/`) dengan shortcode `[elvd_app]`.
- Buka **Settings → Elearning** untuk konfigurasi update otomatis dari GitHub (opsional).

## Development

### Persyaratan

- PHP >= 7.4
- WordPress >= 6.0
- Node.js >= 18 (untuk build)

### Build

Build menghasilkan file zip di `dist/` untuk rilis:

```bash
npm run build
```

### Struktur Plugin

| Direktori | Keterangan |
|---|---|
| `inc/core/` | Kelas utama, autoloader PSR-4 (`ElearningVD\`) |
| `inc/db/` | Migrasi dan helper custom table |
| `inc/restapi/` | REST API routes (`elvd/v1`) |
| `inc/admin/` | Menu admin dan settings |
| `inc/shortcodes/` | Shortcode `[elvd_app]` dan `[elvd_form_login]` |
| `inc/post-provider/` | Registrasi custom post types dan meta |
| `templates/` | Template halaman dan views aplikasi |
| `assets/` | CSS, JS frontend dan admin |
| `seed/` | Seeder untuk data demo |

### Rilis

Push perubahan versi di `package.json` ke branch `main`. GitHub Actions otomatis:
1. Menjalankan `npm run build`.
2. Membuat GitHub Release dengan file zip.
