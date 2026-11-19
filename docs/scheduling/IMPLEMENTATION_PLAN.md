# Implementation Plan — Penjadwalan Satpam (Task 01)

## 1. Kondisi Project Existing

- Laravel `^13.17`, Inertia `^3.7`, PHP `^8.3` (runtime 8.3.28).
- Frontend memakai Inertia + Blade app shell; controller admin berada di `app/Http/Controllers/Admin`.
- Testing memakai PHPUnit 12 dengan SQLite in-memory (`phpunit.xml`).
- Database lokal default SQLite; koneksi MySQL/MariaDB/PostgreSQL/SQL Server tersedia di `config/database.php`.
- **Target kompatibilitas produksi: MySQL 8.4**. SQLite in-memory tetap dipakai untuk automated test.

## 2. Database Existing (tidak diubah)

| Tabel | Kolom penting | Catatan |
|---|---|---|
| `units` | `parent_id`, `unit_code`, `unit_name`, `unit_level` (`uid/up/ulp`), `is_active` | Hierarki unit; `descendantIds()` dipakai untuk scope admin. |
| `users` | `unit_id` nullable, `username`, `role` (`superadmin/admin/security`), `is_active` | Entitas autentikasi. |
| `security_posts` | `post_code`, `name`, `unit_id` | Pos jaga milik satu unit. |
| `patrol_points` | `qr_code`, `name`, `security_post_id` | Titik patroli milik pos. |
| `securities` | `user_id` unique, `registration_number`, identitas/KTA | Personel satpam; **tidak punya** `unit_id`/`security_post_id`. |

## 3. Relasi Master Data Existing

- `Unit` 1—N `User` (`users.unit_id`).
- `Unit` 1—N `SecurityPost` (`security_posts.unit_id`).
- `SecurityPost` 1—N `PatrolPoint` (`patrol_points.security_post_id`).
- `User` 1—1 `Security` (`securities.user_id` unique).
- **Security ↔ Unit**: tidak langsung; melalui `securities.user_id -> users.id -> users.unit_id`.
- **Security ↔ SecurityPost**: belum ada relasi langsung. Pos ditentukan oleh regu (`security_teams.security_post_id`).
- **Regu / shift / pola / jadwal**: belum tersedia sebelum Task 01.
- **Authorization**: middleware `role` tersedia. Route penjadwalan (`shift`, `schedule-pattern`, `schedule-period`, `schedule-calendar`) memakai `role:admin,superadmin`; controller tetap menerapkan unit scope (`superadmin` = semua; `admin/security` = unit sendiri + turunannya).

## 4. Keputusan Arsitektur (disetujui)

1. **Opsi A**: tidak menambah `unit_id`/`security_post_id` ke `securities`.
   - Unit satpam = `securities.user.unit_id`.
   - Pos satpam = pos milik regu.
   - Validasi: unit satpam harus berada dalam scope unit pos regu (`post.unit.descendantIds()`), konsisten dengan pola scope existing.
2. **MySQL 8.4** sebagai target produksi. Migration memakai tipe/index/FK standar yang kompatibel MySQL 8.4 dan SQLite.
3. **Overlap periode**: dilarang untuk `security_team_id` yang sama pada semua status (`draft`, `active`, `completed`), rentang tanggal inklusif. Periode baru boleh mulai `end_date + 1 hari`.
4. **Audit user**: `created_by`/`updated_by` memakai FK `users.id` dengan `restrictOnDelete`; user yang masih direferensikan dinonaktifkan, bukan dihapus.
5. **Relasi Eloquent aditif** pada `Unit`, `SecurityPost`, `Security`, dan `User`; tidak mengubah kolom/behavior existing.
6. **Snapshot periode** (`schedule_period_members`) menyimpan `security_id` dan pola secara eksplisit. Jadwal dan snapshot lama tidak dibaca ulang dari `security_team_members` atau master pola.
7. **Jadwal aktual** disimpan di `schedules`; perubahan master pola tidak mengubah baris jadwal yang sudah ada.

## 5. Database Baru

### 5.1 Master Regu

- **`security_teams`**: `security_post_id` (FK restrict), `name`, `code`, `description`, `is_active`, timestamps.
  - Unique: `(security_post_id, code)`, `(security_post_id, name)`.
- **`security_team_members`**: `security_team_id` (FK restrict), `security_id` (FK restrict), `joined_at`, `left_at` nullable, timestamps.
  - Index: `(security_id, left_at)`, `(security_team_id, left_at)`.
  - Unique: `(security_team_id, security_id, joined_at)`.
  - Semantik tanggal: `joined_at` = hari pertama aktif; `left_at` = hari terakhir aktif (inklusif). `null` = masih aktif.
  - Riwayat tidak dihapus saat pindah regu; `SecurityTeam::assignMember()` menutup keanggotaan aktif lama lalu membuat baris baru dalam transaksi + row lock.

### 5.2 Master Shift & Pola

- **`shifts`**: `unit_id` (FK restrict), `name`, `code`, `start_time` nullable, `end_time` nullable, `end_day_offset` default 0, `color` nullable, `is_active`, timestamps.
  - Unique: `(unit_id, code)`, `(unit_id, name)`.
  - Shift malam memakai `end_day_offset = 1`.
- **`schedule_patterns`**: `unit_id` (FK restrict), `name`, `description`, `cycle_length`, `is_active`, timestamps.
  - Unique: `(unit_id, name)`.
- **`schedule_pattern_details`**: `schedule_pattern_id` (FK cascade), `day_order`, `shift_id` (FK restrict), timestamps.
  - Unique: `(schedule_pattern_id, day_order)`.
  - Validasi model: shift dan pola harus satu unit.

### 5.3 Periode & Snapshot

- **`schedule_periods`**: `unit_id`, `security_post_id`, `security_team_id`, `name`, `start_date`, `end_date`, `status` (`draft/active/completed`), `created_by` (FK restrict), timestamps.
  - Index: `(security_team_id, start_date, end_date)`, `(unit_id, status)`, `(security_post_id, status)`.
  - Validasi model: post/regu harus satu unit dengan periode; tanggal selesai >= mulai; overlap regu ditolak.
- **`schedule_period_members`**: `schedule_period_id` (FK cascade), `security_id` (FK restrict), `schedule_pattern_id` nullable (FK restrict), `start_offset` default 0, timestamps.
  - Unique: `(schedule_period_id, security_id)`.
  - Snapshot independen; `copyMembersFrom()` hanya menyalin baris snapshot periode sebelumnya.

### 5.4 Jadwal Aktual

- **`schedules`**: `schedule_period_id` (FK restrict), `schedule_period_member_id` (FK restrict), `security_id` (FK restrict), `shift_id` nullable (FK restrict), `schedule_pattern_id` nullable (FK restrict), `pattern_day_order` nullable, `date`, `source` (`system_generated/adjustment`), `notes`, `created_by` (FK restrict), `updated_by` nullable (FK restrict), timestamps.
  - Unique: `(security_id, date)` — mencegah jadwal ganda per satpam per tanggal.
  - Index: `(schedule_period_id, date)`, `(schedule_period_member_id, date)`, `shift_id`, `schedule_pattern_id`.
  - Validasi model: shift/pola satu unit dengan periode; anggota periode harus dari periode yang sama dan security harus sama.

## 6. Arsitektur Penjadwalan

1. Admin menentukan **shift** dan **pola rotasi** per unit.
2. Admin membuat **regu** pada pos jaga dan menetapkan anggota beserta tanggal efektif.
3. Admin membuat **periode jadwal** per regu. Saat periode baru dibuat, snapshot anggota diambil dari anggota regu yang aktif pada tanggal mulai, dapat diisi manual, atau disalin dari periode sebelumnya (`copyMembersFrom`) lalu disesuaikan.
4. **Generator** (Task 05) membaca `schedule_period_members`, `schedule_pattern_details`, dan `start_offset`, lalu menulis baris `schedules` dengan `source = system_generated`. Default hanya mengisi jadwal yang belum ada; regenerate eksplisit hanya mengganti baris rotation dan mempertahankan baris manual.
5. **Manual adjustment** (Task 06+) mengubah/menambah baris `schedules` dengan `source = adjustment` tanpa mengubah master pola.
6. Pembacaan jadwal lama selalu memakai snapshot periode dan baris `schedules`, bukan keanggotaan regu saat ini.

## 7. Batasan Task 01 (historis)

Tidak dibuat pada tahap Task 01:

- UI/CRUD Master Shift, Pola Rotasi, Regu, Periode, Kalender.
- Schedule generator, manual adjustment, publish/unpublish.
- `attendances`, `patrol_sessions`, `patrol_logs`, `schedule_revisions`.

Catatan: Master Shift (Task 02), Pola Rotasi (Task 03), Periode (Task 04),
Generator (Task 05), Manual Adjustment (Task 06), Kalender (Task 07), dan
CRUD Regu/Anggota Regu (Task 09) sudah selesai.

## 8. Rencana Implementasi Menu (Task Berikutnya)

| Menu | Status | Catatan |
|---|---|---|
| Master Shift | Completed (Task 02) | CRUD per unit, validasi kode unik, dukungan shift malam, aktivasi/nonaktivasi, proteksi hapus. |
| Master Pola Rotasi | Completed (Task 03) | CRUD pola + editor siklus berurutan, validasi shift aktif satu unit, transaksi, proteksi histori. |
| Regu & Anggota | Completed (Task 09) | CRUD regu per pos, tambah/pindah/akhiri anggota, riwayat tidak dihapus, proteksi pos/hapus jika sudah direferensikan, regu nonaktif tidak dapat dipakai periode baru. |
| Periode Jadwal | Completed (Task 04) | CRUD periode, auto-snapshot/anggota manual, salin snapshot periode sebelumnya, validasi overlap, kunci periode berjadwal. |
| Schedule Generator | Completed (Task 05) | Preview, generate hanya jadwal kosong, regenerate rotation eksplisit, proteksi jadwal manual, validasi all-or-nothing, transaksi. |
| Manual Adjustment | Completed (Task 06) | Edit shift baris jadwal, `source = adjustment`, notes wajib, lock periode `completed`, aman terhadap regenerate. |
| Kalender Jadwal | Completed (Task 07) | Kalender bulanan + daftar, filter unit/pos/regu/satpam/status, monitoring source/notes/updater, `can_edit`. |

## 9. Testing

- Framework: PHPUnit 12, SQLite in-memory (`php artisan test`).
- Cakupan Task 01: relasi unit/shift, pola/detail, pos/regu, regu/anggota, perpindahan anggota, snapshot periode, perbedaan November/Desember, proteksi snapshot & jadwal lama, validasi lintas unit, FK/unique, overlap periode.
- Cakupan Task 02: CRUD shift, validasi kode/jam, dukungan shift malam, toggle aktif, proteksi hapus, unit scope, dan authorization.
- Cakupan Task 03: CRUD pola rotasi, editor siklus, validasi detail/shift aktif, transaksi simpan, proteksi perubahan dan hapus, unit scope.
- Cakupan Task 04: CRUD periode, auto-snapshot anggota aktif, snapshot manual, salin snapshot, validasi pola/offset, overlap, kunci periode berjadwal, unit scope, dan authorization.
- Cakupan Task 05: preview generate, rotasi multi-siklus dan offset berbeda, generate idempotent, regenerate rotation dengan perbandingan, proteksi jadwal manual, isolasi periode lain, validasi konfigurasi per anggota, rollback transaksi, unit scope, dan authorization.
- Cakupan Task 06: penyesuaian manual, validasi shift/notes, proteksi snapshot, penolakan shift sama, lock periode completed, integrasi regenerate, unit scope.
- Cakupan Task 07: kalender bulanan/daftar, filter, scope, `can_edit`, penanda libur, konsistensi baris kalender dengan `schedules`.
- Cakupan Task 08: E2E alur penuh, snapshot November vs Desember, isolasi regenerate, perubahan master tidak mengubah jadwal lama, proteksi hapus master, dan regresi seluruh suite.
- Cakupan Task 09: CRUD regu, validasi kode/nama unik per pos, toggle status, tambah/pindah/akhiri anggota, riwayat & satu keanggotaan aktif, proteksi pos/hapus, regu nonaktif tidak dapat menerima anggota atau dipakai periode baru, peringatan jadwal berjalan, preservasi jadwal/snapshot, unit scope, dan authorization.
- Hasil akhir Task 08: **144 test lulus, 823 assertions**, Laravel Pint lulus, dan frontend build berhasil.
- Hasil akhir Task 09: **168 test lulus, 954 assertions**, Laravel Pint lulus, dan frontend build berhasil (1000 modul).
