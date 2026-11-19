# Aturan Bisnis — Penjadwalan Satpam

Setiap aturan mencantumkan lokasi penegakannya agar mudah diaudit.

## A. Master Shift

**BR-01 — Kode dan nama shift unik per unit.**
Unique `(unit_id, code)` dan `(unit_id, name)`.
Penegakan: migration `shifts`, validasi `ShiftController`.

**BR-02 — Jam shift opsional, tetapi harus berpasangan dan format `H:i`.**
Jika diisi, `start_time` dan `end_time` wajib valid. `end_day_offset = 1` untuk
shift yang berakhir keesokan hari.
Penegakan: `ShiftController::validateTimeRange()`, migration.

**BR-03 — Shift yang sudah dipakai tidak boleh dihapus.**
Dipakai berarti direferensikan `schedule_pattern_details` atau `schedules`.
Penegakan: `ShiftController::isReferenced()` + `QueryException`, FK
`restrictOnDelete`.

**BR-04 — Perubahan unit shift ditolak jika shift sudah dipakai.**
Penegakan: `ShiftController::update()`.

## B. Pola Rotasi

**BR-05 — Nama pola unik per unit.**
Unique `(unit_id, name)`.
Penegakan: migration, validasi controller.

**BR-06 — Detail pola harus lengkap dan berurutan.**
Jumlah detail = `cycle_length`; `day_order` unik dan berurutan `1..N`.
Penegakan: `SchedulePatternController::validateDetails()`, unique
`(schedule_pattern_id, day_order)`.

**BR-07 — Shift pada detail pola harus aktif dan satu unit dengan pola.**
Penegakan: validasi `Rule::exists('shifts')->where(unit_id, is_active)`.

**BR-08 — Pola yang sudah dipakai mengunci `cycle_length` dan unit.**
Detail shift masih boleh diubah, tetapi perubahan detail tidak mengubah baris
`schedules` yang sudah ada.
Penegakan: `SchedulePatternController::update()`; jadwal menyimpan snapshot
metadata sendiri.

**BR-09 — Pola yang sudah dipakai tidak boleh dihapus.**
Penegakan: `isReferenced()` + FK restrict.

## C. Regu dan Anggota

**BR-10 — Kode dan nama regu unik per pos jaga.**
Unique `(security_post_id, code)` dan `(security_post_id, name)`.

**BR-11 — Satpam hanya boleh memiliki satu keanggotaan regu aktif.**
`assignMember()` menutup keanggotaan aktif lama dengan
`left_at = joined_at_baru - 1` dalam transaksi + row lock.
Catatan: aturan ini ditegakkan di aplikasi, **bukan** partial unique index di
database.

**BR-12 — Tanggal keanggotaan inklusif.**
`joined_at` = hari pertama aktif; `left_at` = hari terakhir aktif; `null` =
masih aktif. `left_at` tidak boleh sebelum `joined_at`.

**BR-13 — Anggota regu harus berada dalam scope unit pos jaga.**
Penegakan: `SecurityTeam::acceptsSecurity()`.

**BR-14 — Perpindahan anggota menyimpan riwayat.**
Baris lama tidak dihapus; hanya `left_at` diisi.

## D. Periode Jadwal

**BR-15 — Pos dan regu harus satu unit dengan periode.**
Penegakan: validasi `Rule::exists` pada `SchedulePeriodController`.

**BR-16 — `end_date >= start_date`.**
Penegakan: validasi `after_or_equal:start_date` dan validasi model.

**BR-17 — Periode untuk regu yang sama tidak boleh overlap, semua status.**
Rentang tanggal inklusif. Periode baru boleh mulai `end_date + 1`.
Penegakan: `SchedulePeriod::overlapping()` dan
`SchedulePeriodController::assertNoOverlap()`, termasuk saat update.

**BR-18 — Snapshot anggota bersifat independen.**
Snapshot menyimpan `security_id`, pola, dan `start_offset`. Perubahan
keanggotaan regu atau master pola tidak mengubah snapshot lama.
Penegakan: struktur `schedule_period_members` tanpa FK ke
`security_team_members`.

**BR-19 — Anggota snapshot unik per periode.**
Unique `(schedule_period_id, security_id)`.

**BR-20 — Pola anggota snapshot harus aktif, satu unit, dan `start_offset`
dalam siklus.**
`0 <= start_offset < cycle_length`.
Penegakan: `resolveMembers()`, `validatePeriodConfiguration()`.

**BR-21 — Periode yang sudah memiliki jadwal mengunci struktur.**
Unit, pos, regu, tanggal, dan snapshot tidak boleh berubah. Hanya nama dan
status yang dapat diubah.
Penegakan: `assertLockedFieldsUnchanged()` dan
`assertLockedMembersUnchanged()`.

**BR-22 — Status periode: `draft`, `active`, `completed`.**
Default `draft`. Perubahan status tidak menghapus jadwal.
Penegakan: enum migration dan validasi status.

## E. Generator

**BR-23 — Rumus urutan pola 1-based.**
`((start_offset + selisih_hari) % cycle_length) + 1`, dihitung dari
`period.start_date`.

**BR-24 — Mode fill hanya membuat jadwal yang belum ada.**
Jadwal existing tidak diubah. Operasi idempotent.
Penegakan: `ScheduleGenerationService::preview()`.

**BR-25 — Mode regenerate hanya menyentuh `source = system_generated` pada periode ini.**
Tidak menyentuh periode lain.
Penegakan: filter `schedule_period_id` + `source`, transaksi.

**BR-26 — Jadwal `source = adjustment` tidak pernah ditimpa atau dihapus generator.**
Penegakan: aksi `keep_manual` pada preview.

**BR-27 — Generate bersifat all-or-nothing.**
Jika ada error konfigurasi/konflik, tidak ada jadwal yang ditulis.
Penegakan: preview error + `ValidationException` sebelum transaksi tulis.

**BR-28 — Konflik jadwal lintas periode ditolak.**
Satpam yang sudah punya jadwal pada tanggal yang sama di periode lain menjadi
error.
Penegakan: pengecekan `existing.schedule_period_id !== period.id`.

**BR-29 — Satu satpam hanya boleh punya satu jadwal per tanggal.**
Unique `(security_id, date)`.
Penegakan: index database.

## F. Penyesuaian Manual

**BR-30 — Penyesuaian manual hanya mengubah shift, source, notes, updated_by.**
Tanggal dan security tetap, sehingga unique `(security_id, date)` aman.
Penegakan: `SchedulePeriodController::updateSchedule()`.

**BR-31 — Shift pengganti harus aktif dan satu unit dengan periode.**
Penegakan: `Rule::exists` dengan `unit_id` dan `is_active`.

**BR-32 — Alasan perubahan wajib diisi.**
Maksimal 1000 karakter.

**BR-33 — Shift pengganti tidak boleh sama dengan shift saat ini.**
Penegakan: perbandingan `shift_id`.

**BR-34 — Baris harus milik periode dan satpam harus anggota snapshot.**
Penegakan: query `schedule_period_id` + validasi
`schedule_period_members`.

**BR-35 — Periode `completed` tidak dapat diubah manual.**
Penegakan: guard status di `updateSchedule()` dan `isLocked` di UI.

## G. Kalender dan Monitoring

**BR-36 — Kalender membaca baris `schedules` aktual.**
Tidak menghitung ulang rotasi; perubahan master tidak mengubah tampilan lama.

**BR-37 — Filter dan scope unit wajib diterapkan.**
Admin hanya melihat unit sendiri + turunan; superadmin semua unit.

**BR-38 — Baris periode `completed` ditandai tidak dapat diedit.**
`can_edit = false`; aksi edit hanya navigasi ke detail periode.

**BR-39 — Penanda libur bersifat heuristik.**
Nama/kode shift mengandung `libur`, `off`, atau `lepas` dianggap libur karena
schema belum memiliki flag khusus.

**BR-40 — Rentang tanggal bulan bersifat inklusif dan lintas database.**
Query memakai `whereDate` agar aman pada SQLite dan MySQL.

## H. Audit dan Penghapusan

**BR-41 — Jejak audit disimpan pada `created_by`/`updated_by`.**
FK `restrictOnDelete`; user yang masih direferensikan tidak boleh dihapus.

**BR-42 — Penghapusan master yang masih direferensikan ditolak.**
Shift, pola, security, pos, regu, dan user dilindungi FK `restrictOnDelete`.
Controller menampilkan pesan error, bukan error 500.

**BR-43 — Snapshot dan jadwal lama tidak dihapus saat master berubah.**
Perubahan master hanya memengaruhi generate/periode berikutnya.
