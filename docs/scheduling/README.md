# Dokumentasi Modul Penjadwalan Satpam

Modul penjadwalan mengelola shift, pola rotasi, regu, periode jadwal, generator
jadwal, penyesuaian manual, dan kalender monitoring.

Status implementasi: **Task 01–08 selesai** (foundation, master shift, pola
rotasi, periode + snapshot, generator, penyesuaian manual, kalender, dan QA
akhir). Tidak ada perubahan schema pada Task 06–08.

## Prasyarat

- PHP 8.3, Composer, Node.js + npm.
- Database produksi target: **MySQL 8.4**.
- Database automated test: **SQLite in-memory** (`phpunit.xml`).
- Backend: Laravel 13, PHPUnit 12, Laravel Pint.
- Frontend: Inertia + Vue 3, Vite 8, Tailwind 4.

## Perintah Penting

```bash
composer install
npm install
php artisan migrate
npm run build

php artisan test
php vendor/bin/pint --test
```

Menjalankan hanya test penjadwalan:

```bash
php artisan test tests/Feature/Scheduling
php artisan test tests/Feature/Admin/ScheduleGenerationTest.php
php artisan test tests/Feature/Admin/ScheduleAdjustmentTest.php
php artisan test tests/Feature/Admin/ScheduleCalendarControllerTest.php
```

## Struktur Dokumentasi

| Dokumen | Isi |
|---|---|
| `USER_GUIDE.md` | Panduan operasional admin dari master data sampai monitoring. |
| `SYSTEM_FLOW.md` | Alur sistem, komponen, algoritma generator, dan batas transaksi. |
| `BUSINESS_RULES.md` | Aturan bisnis dan validasi yang wajib dipatuhi. |
| `DATABASE_DESIGN.md` | Rancangan tabel, kolom, relasi, index, dan constraint. |
| `IMPLEMENTATION_PLAN.md` | Keputusan arsitektur dan rencana implementasi per task. |
| `ERD.md` | Diagram relasi entitas. |
| `TODO.md` | Status pekerjaan, backlog, dan future development. |
| `QA_REPORT.md` | Hasil audit, bug yang diperbaiki, test, dan batasan akhir. |

## Alur Singkat

1. Siapkan unit, pos jaga, personel security, shift, dan pola rotasi.
2. Siapkan regu dan anggota regu.
3. Buat periode jadwal (snapshot anggota otomatis/manual/salin).
4. Preview lalu generate jadwal.
5. Sesuaikan jadwal tertentu secara manual bila perlu.
6. Pantau melalui Kalender & Monitoring.

## Batasan Penting

- **CRUD Regu dan Anggota Regu tersedia melalui menu Data Regu**
  (`/admin/security-team`): tambah/pindah/akhiri anggota dengan tanggal efektif,
  riwayat tidak dihapus. Lihat `USER_GUIDE.md`.
- Verifikasi eksekusi langsung terhadap MySQL 8.4 belum dilakukan pada lingkungan
  ini; lihat `QA_REPORT.md`.
- Tidak ada fitur revisi jadwal, publish/unpublish, absensi, atau patroli pada
  modul ini. Modul absensi tersedia terpisah dan didokumentasikan pada
  `docs/attendance/`.
