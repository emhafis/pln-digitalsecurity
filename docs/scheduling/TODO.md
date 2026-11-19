# TODO — Penjadwalan Satpam

## Completed (Task 01)

- [x] Audit project existing (Laravel 13, PHP 8.3, Inertia, SQLite dev, MySQL 8.4 target).
- [x] Audit migration dan model master data: `units`, `users`, `security_posts`, `patrol_points`, `securities`.
- [x] Dokumentasi relasi master data dan gap Security ↔ Pos.
- [x] Migration `security_teams`.
- [x] Migration `security_team_members`.
- [x] Migration `shifts`.
- [x] Migration `schedule_patterns`.
- [x] Migration `schedule_pattern_details`.
- [x] Migration `schedule_periods`.
- [x] Migration `schedule_period_members` (snapshot periode).
- [x] Migration `schedules` (jadwal aktual).
- [x] Model dan relasi Eloquent untuk seluruh tabel baru.
- [x] Relasi aditif pada `Unit`, `SecurityPost`, `Security`, `User`.
- [x] Factory pendukung testing.
- [x] Validasi lintas unit untuk shift, pola, periode, snapshot, dan jadwal.
- [x] Validasi overlap periode regu (semua status, inklusif).
- [x] Fondasi salin snapshot anggota periode (`copyMembersFrom`).
- [x] Automated tests Task 01.
- [x] Perbaikan test lama `ExampleTest` agar sesuai perilaku redirect ke login.
- [x] Dokumentasi `IMPLEMENTATION_PLAN.md`, `ERD.md`, `TODO.md`.

## Completed (Task 02)

- [x] CRUD Master Shift per unit (index/create/store/show/edit/update/delete).
- [x] Aktivasi dan nonaktivasi shift (`PATCH /admin/shift/{id}/toggle`).
- [x] Validasi kode unik per unit, jam kerja, dan shift lintas tengah malam.
- [x] Proteksi hapus shift yang sudah dipakai pola rotasi atau jadwal.
- [x] Unit scope dan role authorization (`admin`, `superadmin`).
- [x] UI Inertia/Vue konsisten dengan modul existing.
- [x] Automated tests Task 02 dan Laravel Pint lulus.

## Completed (Task 03)

- [x] CRUD Master Pola Rotasi per unit (index/create/store/show/edit/update/delete).
- [x] Editor urutan siklus dan preview pola.
- [x] Validasi nama unik per unit, jumlah detail sesuai `cycle_length`, urutan hari lengkap tanpa duplikat.
- [x] Validasi shift harus aktif dan berasal dari unit yang sama.
- [x] Penyimpanan pola + detail dalam transaksi database.
- [x] Proteksi hapus pola yang sudah dipakai periode/jadwal dan proteksi perubahan `cycle_length`/unit.
- [x] Perubahan detail pola tidak mengubah jadwal yang sudah tersimpan.
- [x] Unit scope, role authorization, dan UI konsisten dengan Task 02.
- [x] Automated tests Task 03 dan Laravel Pint lulus.

## Completed (Task 04)

- [x] CRUD Periode Jadwal per unit (index/create/store/show/edit/update).
- [x] Auto-snapshot anggota regu yang aktif pada tanggal mulai periode.
- [x] Input snapshot anggota eksplisit beserta pola rotasi dan `start_offset`.
- [x] Salin snapshot anggota dari periode sebelumnya tanpa menyalin jadwal.
- [x] Validasi rentang tanggal, overlap regu semua status, pos/regu satu unit, pola aktif satu unit, offset dalam siklus, anggota unik, dan scope unit satpam.
- [x] Periode dengan jadwal mengunci unit, pos, regu, tanggal, dan snapshot anggota; hanya nama dan status yang dapat diubah.
- [x] Unit scope, role authorization, filter, pencarian, dan UI konsisten dengan Task 02/03.
- [x] Automated tests Task 04 (23 test) dan Laravel Pint lulus.

## Completed (Task 05)

- [x] Preview generate jadwal sebelum penyimpanan (ringkasan, anggota, pola, offset, baris tanggal-shift).
- [x] Generate otomatis dari snapshot anggota + pola rotasi + `start_offset` + detail shift.
- [x] Rumus urutan pola 1-based: `((start_offset + selisih_hari) % cycle_length) + 1`.
- [x] Generate default hanya membuat jadwal yang belum tersedia; jadwal existing tidak diubah.
- [x] Regenerate eksplisit khusus jadwal `source = system_generated` dengan preview perbandingan shift lama/baru.
- [x] Jadwal `source = adjustment` tidak pernah ditimpa atau dihapus generator.
- [x] Validasi all-or-nothing: snapshot kosong, pola/detail/shift tidak valid atau nonaktif, offset di luar siklus, konflik jadwal, dan alasan per anggota.
- [x] Generate dan regenerate berjalan dalam transaksi database serta tidak menyentuh periode lain.
- [x] Unit scope, role authorization, UI Inertia/Vue, automated tests Task 05, dan Laravel Pint lulus.

## Completed (Task 06)

- [x] Penyesuaian manual shift satu satpam pada satu tanggal.
- [x] Route `PUT /admin/schedule-period/{id}/schedule/{scheduleId}` (`admin.schedule-period.schedule.update`).
- [x] Validasi shift aktif + unit periode, notes wajib, snapshot anggota, periode terkunci jika completed.
- [x] `source = adjustment`, `updated_by`, `notes` tersimpan; rotasi metadata tetap.
- [x] UI tabel jadwal + modal edit + konfirmasi di halaman detail periode.
- [x] Integrasi generator: jadwal manual tetap aman saat regenerate.
- [x] Automated tests Task 06 (13 test) dan Laravel Pint lulus.

## Completed (Task 07)

- [x] Route kalender `GET /admin/schedule-calendar` (`admin.schedule-calendar.index`).
- [x] Halaman kalender bulanan + tampilan daftar.
- [x] Filter bulan, unit, pos jaga, regu, satpam, dan status periode.
- [x] Navigasi bulan sebelumnya/berikutnya.
- [x] Menampilkan shift, waktu, source, notes, updater, status periode, dan `can_edit`.
- [x] Legenda shift dan penanda libur heuristik (`libur`/`off`/`lepas`).
- [x] Aksi edit mengarah ke detail periode; tidak ada mekanisme edit baru.
- [x] Unit scope, role authorization, dan menu sidebar “Kalender & Monitoring”.
- [x] Automated tests Task 07 (9 test) dan Laravel Pint lulus.

## Completed (Task 08 — Final QA)

- [x] Audit akhir Task 01–07 (route, middleware, unit scope, transaksi, FK/unique/index, proteksi hapus).
- [x] E2E test alur penjadwalan lengkap A–L (`SchedulingEndToEndTest`, 4 test, 101 assertions).
- [x] Perbaikan BUG-01: kalender kehilangan hari terakhir bulan (`whereBetween` → `whereDate`).
- [x] Regression seluruh test suite: 144 test, 823 assertions lulus.
- [x] Laravel Pint lulus.
- [x] Frontend build berhasil.
- [x] Dokumentasi `README.md`, `USER_GUIDE.md`, `SYSTEM_FLOW.md`, `BUSINESS_RULES.md`, `DATABASE_DESIGN.md`, `QA_REPORT.md`.
- [x] Sinkronisasi `TODO.md`, `IMPLEMENTATION_PLAN.md`, `ERD.md`.

## Completed (Task 09 — CRUD Regu & Anggota Regu)

- [x] CRUD Master Regu per pos jaga (index/create/store/show/edit/update/delete).
- [x] Aktivasi/nonaktivasi regu (`PATCH /admin/security-team/{id}/toggle`).
- [x] Tambah anggota, pindah anggota dengan tanggal efektif, dan akhiri keanggotaan.
- [x] Riwayat keanggotaan tidak dihapus; perpindahan memakai `SecurityTeam::assignMember()`.
- [x] Pos jaga terkunci dan regu tidak dapat dihapus jika sudah memiliki riwayat anggota atau periode jadwal.
- [x] Regu nonaktif tidak dapat menerima anggota baru atau dipakai untuk periode jadwal baru; periode lama tetap dapat diubah.
- [x] Peringatan informatif jika satpam masih memiliki jadwal pada periode `draft`/`active`; jadwal dan snapshot tidak diubah.
- [x] Unit scope, role authorization, filter, pencarian, dan menu sidebar "Data Regu".
- [x] Automated tests Task 09 (24 test, 131 assertions); regresi suite: 168 test, 954 assertions lulus.
- [x] Laravel Pint lulus dan frontend build berhasil.

## In Progress

- Tidak ada.

## Pending

- Tidak ada.

## Future Development

- [ ] Authorization berbasis Policy/Gate dan helper unit scope bersama.
- [ ] Hardening konkurensi generator (row lock/advisory lock + test konkurensi).
- [ ] Constraint database untuk satu keanggotaan regu aktif.
- [ ] Validasi transisi status periode (`completed` final).
- [ ] Flag eksplisit hari libur pada shift.
- [ ] Input warna shift dan pemakaian warna di kalender.
- [ ] Opsi satpam kalender dari seluruh unit scope, bukan hanya snapshot.
- [ ] Attendance.
- [ ] Patrol sessions.
- [ ] Patrol logs.
- [ ] Schedule revisions / audit trail.
- [ ] Publish/unpublish periode.
- [ ] Notifikasi jadwal.
- [ ] Export jadwal (PDF/Excel).
- [ ] Dashboard monitoring kepatuhan jadwal.
- [ ] Optimasi query dan caching untuk kalender besar.
- [ ] Verifikasi eksekusi langsung MySQL 8.4 di staging.
