# Laporan QA Akhir — Modul Penjadwalan (Task 08)

Tanggal: 8 November 2026
Lingkungan: Windows, PHP 8.3, Laravel 13, PHPUnit 12, SQLite in-memory.
Target produksi: MySQL 8.4.

## 1. Ringkasan

Task 08 menjalankan audit akhir Task 01–07, regression test, perbaikan satu bug
yang terbukti, penambahan E2E test, dan pelengkapan dokumentasi. Tidak ada
perubahan schema dan tidak ada fitur baru di luar ruang lingkup penjadwalan.

Hasil akhir:

- **144 test lulus, 823 assertions**.
- **Laravel Pint lulus**.
- **Frontend build berhasil**.
- **1 bug ditemukan dan diperbaiki** (kalender kehilangan hari terakhir bulan).
- MySQL 8.4 **belum diverifikasi melalui eksekusi langsung**.

## 2. Audit per Task

| Task | Cakupan | Hasil | Catatan |
|---|---|---|---|
| 01 | Fondasi tabel, model, relasi, validasi unit, snapshot, overlap | PASS | Keunikan satu keanggotaan aktif ditegakkan di aplikasi, bukan DB. |
| 02 | CRUD shift, toggle, validasi jam, proteksi hapus | PASS | `color` belum diekspos di CRUD. |
| 03 | CRUD pola + detail, validasi siklus, kunci struktur, proteksi hapus | PASS | Perubahan detail tidak menulis ulang jadwal lama. |
| 04 | CRUD periode, snapshot otomatis/manual/salin, overlap, kunci periode berjadwal | PASS | Status `completed` masih dapat diubah kembali ke status lain melalui edit. |
| 05 | Preview, generate fill, regenerate rotation, proteksi manual, all-or-nothing | PASS | Belum ada row lock pada preview/generate; lihat batasan. |
| 06 | Penyesuaian manual, source adjustment, notes, lock completed | PASS | Validasi snapshot dan shift sudah benar. |
| 07 | Kalender, filter, monitoring, `can_edit` | **FAIL → FIXED** | Bug hari terakhir bulan; sudah diperbaiki dan diuji ulang. |
| 08 | Audit, regression, E2E, dokumentasi | PASS | Lihat dokumen ini. |

Temuan lintas modul:

- Tidak ada Policy/Gate; otorisasi berbasis role middleware + unit scope di
  controller.
- Logika `unitScope()` terduplikasi di beberapa controller.
- Route CRUD admin `SecurityTeam` tersedia (Task 09); keanggotaan dikelola
  melalui endpoint member pada controller yang sama.
- Semua route penjadwalan berada di middleware `role:admin,superadmin` dan
  menerapkan unit scope.

## 3. Bug Ditemukan dan Diperbaiki

### BUG-01 — Kalender tidak menampilkan hari terakhir bulan

- **Lokasi:** `app/Http/Controllers/Admin/ScheduleCalendarController.php`.
- **Penyebab:** query memakai
  `whereBetween('schedules.date', ['YYYY-MM-01', 'YYYY-MM-31'])`. Pada SQLite,
  nilai kolom `date` tersimpan sebagai `YYYY-MM-DD 00:00:00`, sehingga batas
  atas `YYYY-MM-DD` mengecualikan seluruh hari terakhir bulan.
- **Dampak:** kalender kehilangan 1 hari × jumlah anggota pada setiap bulan
  (contoh: 3 anggota × 30 hari tampil 87 baris, seharusnya 90).
- **Perbaikan:** ganti ke `whereDate('schedules.date', '>=', awal)` dan
  `whereDate('schedules.date', '<=', akhir)`.
- **Verifikasi:** E2E test memastikan jumlah baris kalender sama dengan jumlah
  baris `schedules` aktual, termasuk tanggal terakhir.

Tidak ada bug lain yang ditemukan pada audit Task 08. Tidak ada test yang
dihapus atau dilonggarkan.

## 4. File Task 08

### Dibuat

- `tests/Feature/Scheduling/SchedulingEndToEndTest.php`
- `docs/scheduling/README.md`
- `docs/scheduling/USER_GUIDE.md`
- `docs/scheduling/SYSTEM_FLOW.md`
- `docs/scheduling/BUSINESS_RULES.md`
- `docs/scheduling/DATABASE_DESIGN.md`
- `docs/scheduling/QA_REPORT.md`

### Diubah

- `app/Http/Controllers/Admin/ScheduleCalendarController.php` — perbaikan
  BUG-01.
- `docs/scheduling/TODO.md` — status Task 07/08 dan backlog.
- `docs/scheduling/IMPLEMENTATION_PLAN.md` — status implementasi dan hasil test.
- `docs/scheduling/ERD.md` — catatan status dan constraint.

## 5. Cakupan E2E Test Baru

`SchedulingEndToEndTest` (4 test, 101 assertions):

1. Alur penuh: unit → pos → regu + 3 satpam → shift → pola 2-2-2-2 → periode
   November → snapshot → generate (90 baris) → validasi unique → penyesuaian
   manual → regenerate (manual tetap) → kalender sama dengan data aktual.
2. Periode Desember dengan perubahan anggota tidak mengubah snapshot November;
   regenerate November tidak menyentuh baris Desember.
3. Perubahan master pola dan shift tidak mengubah baris jadwal lama.
4. Shift, pola, dan security yang sudah direferensikan tidak dapat dihapus
   melalui HTTP; data tetap ada dan pesan error ditampilkan.

Test lama yang tetap dijalankan:

- `ScheduleGenerationTest` (22 test).
- `ScheduleAdjustmentTest` (13 test).
- `ScheduleCalendarControllerTest` (9 test).
- Seluruh regression suite lain.

## 6. Hasil Automated Test

Perintah:

```bash
php artisan test
```

Hasil:

```
144 passed, 823 assertions
```

Sebelum Task 08: 140 test, 722 assertions.
Tambahan: 4 test, 101 assertions.

## 7. Laravel Pint

```bash
php vendor/bin/pint --test
```

Hasil: **passed**.

## 8. Frontend Build

```bash
npm run build
```

Hasil: **berhasil**.

- Vite 8.3.5.
- 993 modul ditransformasi.
- Build selesai dalam 1,14 detik.
- Aset baru termasuk `SchedulePeriodForm`, `Generate`, `CalendarGrid`,
  `CalendarFilter`, dan `CalendarTable`.

## 9. Verifikasi MySQL 8.4

Status: **belum diverifikasi melalui eksekusi langsung** pada lingkungan ini.

Yang sudah dipastikan:

- Migration memakai tipe dan constraint standar InnoDB/MySQL 8.4.
- Charset `utf8mb4`, collation `utf8mb4_unicode_ci`, strict mode.
- Tidak memakai partial index atau fitur khusus SQLite.
- Query rentang tanggal sudah memakai `whereDate` (portabel).
- Unique/index komposit dan FK restrict sesuai rancangan.

Checklist yang disarankan sebelum produksi:

1. Jalankan `php artisan migrate:fresh` pada MySQL 8.4 staging.
2. Seed minimal: 1 unit, 1 pos, 1 regu, 3 satpam, 3 shift, 1 pola, 1 periode.
3. Ulangi alur E2E secara manual dan bandingkan hasil kalender.
4. Uji kasus tanggal akhir bulan dan shift lintas tengah malam.
5. Jalankan `EXPLAIN` untuk query kalender dan daftar periode.
6. Pastikan `sql_mode` strict dan timezone aplikasi konsisten.

## 10. Batasan dan Rekomendasi

### Batasan saat ini

1. **CRUD Regu/Anggota Regu sudah tersedia (Task 09).** Batasan yang tersisa:
   satu keanggotaan aktif tetap hanya ditegakkan aplikasi, dan perubahan
   keanggotaan tidak mengubah jadwal/snapshot lama (sesuai desain).
2. **Tidak ada Policy/Gate.** Otorisasi berbasis middleware role + unit scope di
   controller; logika scope terduplikasi.
3. **Konkurensi generator belum di-hardening.** `generate()` memakai transaksi,
   tetapi belum mengunci periode/rentang jadwal. Unique
   `(security_id, date)` mencegah duplikasi, namun salah satu transaksi
   bersamaan dapat gagal.
4. **Satu keanggotaan aktif tidak dijamin database.** Hanya ditegakkan oleh
   `assignMember()`.
5. **Status `completed` dapat diubah kembali** melalui edit periode; belum ada
   aturan transisi status eksplisit.
6. **Penanda libur heuristik** dari nama/kode shift (`libur`, `off`, `lepas`).
7. **`shifts.color` belum diekspos** di CRUD; warna kalender dapat kosong.
8. **Opsi satpam pada filter kalender** bergantung pada snapshot periode; bisa
   kosong jika belum ada periode.
9. **Belum ada revisi/histori jadwal** selain `notes` dan `updated_by`.
10. **MySQL 8.4 belum diuji eksekusi langsung.**

### Rekomendasi

1. Tambahkan audit trail khusus untuk perubahan keanggotaan regu bila kebutuhan
   operasional menuntut.
2. Tambahkan Policy/Gate dan satukan helper unit scope untuk mengurangi
   duplikasi.
3. Hardening generator: row lock pada periode/rentang atau advisory lock,
   lalu tambahkan test konkurensi.
4. Pertimbangkan constraint database untuk satu keanggotaan aktif (mis. kolom
   generated + unique) bila didukung target MySQL.
5. Tambahkan validasi transisi status periode (mis. `completed` final).
6. Tambahkan flag eksplisit hari libur pada shift.
7. Lengkapi input warna shift dan pemakaian warna di kalender.
8. Sediakan opsi satpam dari seluruh unit scope, bukan hanya snapshot.
9. Tambahkan audit trail/revisi jadwal bila kebutuhan operasional menuntut.
10. Jalankan checklist MySQL 8.4 di staging sebelum deploy.
