# Project Overview

Plugin elearning-vd adalah plugin elearning untuk sekolah sd, smp, sma. dengan fitur:
- Siswa
- Guru
- Tahun Ajaran
- Kelas
- Mata Pelajaran
- Jadwal Pelajaran
- Tugas
- Materi
- Essay
- Quiz

# Tech Stack
- Wordpress
- Bootstrap 5
- Alpine.js
- WordPress REST API

# Aturan
- Plugin ini harus diinstal di Wordpress.
- Menggunakan Bootstrap 5 untuk desain.
- Jika style class bootstrap 5 tidak ditersedia, maka buat class sendiri di assets/main.css, beri prefix elvd-.
- Menggunakan Alpine.js untuk interaksi.
- Menggunakan WordPress REST API untuk komunikasi.
- custom table gunakan prefix elvd_.
- custom post types gunakan prefix elvd_.
- custom meta post gunakan prefix elvd_.
- custom meta user gunakan prefix elvd_.

# Custom User Role
- Siswa
- Guru

# Custom Post Types
- Tugas (elvd_tugas)
- Materi (elvd_materi)
- Quiz (elvd_quiz) | pilihan ganda dan essay
- Quiz Question (elvd_quiz_question) | pertanyaan quiz berelasi dengan quiz.

# Custom Table
- tahun_ajaran (elvd_tahun_ajaran)
- kelas (elvd_kelas)
- mata_pelajaran (elvd_mata_pelajaran)
- jadwal_pelajaran (elvd_jadwal_pelajaran)
- pengerjaan_quiz (elvd_pengerjaan_quiz)
- pengerjaan_tugas (elvd_pengerjaan_tugas)
