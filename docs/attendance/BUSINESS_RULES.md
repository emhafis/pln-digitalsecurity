# Aturan Bisnis - Absensi Satpam

Setiap aturan mencantumkan lokasi penegakannya agar mudah diaudit.

## A. Sumber Data dan Snapshot

**BR-A01 - Satu jadwal menghasilkan tepat satu data absensi.**
Unique `attendance_records.schedule_id`.
Penegakan: migration `attendance_records`, `AttendanceGenerator`.

**BR-A02 - Jenis shift menentukan perilaku, bukan nama atau kode.**
`shifts.type = working` membutuhkan absensi; `day_off` langsung menjadi Libur.
Penegakan: `AttendanceGenerator::upsertRecord()`,
`AttendanceActivityService::assertWorking()`.

**BR-A03 - Snapshot menyimpan salinan data operasional.**
Nama Satpam, unit, pos, regu, shift, waktu jadwal, koordinat pos, dan nilai
pengaturan disalin ke `attendance_records` saat record dibuat/disinkronkan.
Perubahan master setelah snapshot terkunci tidak mengubah record.
Penegakan: `AttendanceGenerator::buildSnapshot()`, `AttendanceRecord::canSyncSnapshot()`.

**BR-A04 - Snapshot terkunci permanen setelah aktivitas.**
Kunci diisi saat check-in, check-out, koreksi disetujui, penetapan Tidak Hadir,
atau pembuatan record Libur.
Penegakan: seluruh service absensi mengisi `snapshot_locked_at`.

**BR-A05 - Sinkronisasi snapshot hanya saat aman.**
Generator hanya menyinkronkan bila belum terkunci, belum ada check-in/check-out,
belum ada koreksi diterapkan, dan tidak ada koreksi `pending`.
Penegakan: `AttendanceRecord::canSyncSnapshot()`.

**BR-A06 - Koreksi pending tidak mengunci, tetapi menahan sinkronisasi.**
Penegakan: `AttendanceRecord::canSyncSnapshot()`.

## B. Hari Operasional dan Waktu

**BR-B01 - `work_date` adalah tanggal mulai operasional dalam WIB.**
Shift lintas tengah malam tetap memakai tanggal mulai sebagai `work_date`.
Penegakan: `AttendanceGenerator::buildSnapshot()`,
`AttendanceClock::scheduledStart()`.

**BR-B02 - Shift kerja wajib memiliki jam mulai, jam selesai, dan offset hari
yang valid (0 atau 1).**
Penegakan: `AttendanceReadinessChecker::workingTimeIssue()`.

**BR-B03 - Shift lintas tengah malam didukung.**
`end_day_offset = 1` menambah satu hari pada waktu selesai.
Penegakan: `AttendanceClock::scheduledEnd()`.

**BR-B04 - Check-in dibuka 15 menit sebelum shift dan ditutup pada waktu
selesai shift (inklusif).**
Nilai dapat diubah melalui Pengaturan Absensi.
Penegakan: `AttendanceActivityService::checkIn()`.

**BR-B05 - Check-in dianggap tepat waktu sampai `start + 5 menit`; setelah itu
terlambat.**
Penegakan: `AttendanceRecord::isLate()`.

**BR-B06 - Check-out hanya dapat dilakukan pada
`[shift_end - toleransi_checkout_awal, shift_end + batas_tutup_checkout]`.**
Tidak ada check-out lebih awal bila toleransi checkout awal = 0.
Penegakan: `AttendanceActivityService::checkOut()`.

**BR-B07 - Tidak Hadir ditetapkan bila belum ada check-in efektif dan waktu
telah melewati `shift_end + batas_tutup_checkout`.**
Record yang sudah check-in tetapi belum check-out tetap Sedang Bertugas.
Penegakan: `AttendanceStatusEvaluator::markAbsent()`.

**BR-B08 - Batas waktu bersifat inklusif pada batas atas.**
Penegakan: perbandingan `greaterThan` pada evaluator dan service aktivitas.

## C. Lokasi Perangkat

**BR-C01 - Koordinat perangkat wajib dikirim dari perangkat.**
Penegakan: validasi request dan `AttendanceActivityService::validateGps()`.

**BR-C02 - Jarak dihitung di backend menggunakan Haversine.**
Jarak dari frontend tidak dipercaya.
Penegakan: `App\Support\GeoDistance::meters()`.

**BR-C03 - Jarak perangkat harus <= radius pada snapshot record.**
Penegakan: `AttendanceActivityService::validateGps()`.

**BR-C04 - Akurasi lokasi perangkat harus <= batas akurasi pada snapshot.**
Penegakan: `AttendanceActivityService::validateGps()`.

**BR-C05 - Pos Jaga tanpa koordinat tidak boleh menghasilkan record kerja.**
Kesiapan periode gagal dan anomaly `missing_post_coordinates` dibuat.
Penegakan: `AttendanceReadinessChecker`, `AttendanceGenerator`.

## D. Kesiapan dan Pembuatan Data

**BR-D01 - Pengaturan Absensi global yang tidak valid memblokir seluruh
pembuatan data absensi.**
Penegakan: `AttendanceReadinessChecker::checkGlobalSettings()`,
`AttendanceGenerator::generateAll()`.

**BR-D02 - Masalah pada satu periode hanya memblokir periode tersebut.**
Penegakan: `AttendanceGenerator::generateAll()` dan `generatePeriod()`.

**BR-D03 - Pembuatan data absensi bersifat all-or-nothing per periode.**
Tidak boleh ada record parsial saat periode belum siap.
Penegakan: transaksi + pengecekan ulang kesiapan di `generatePeriod()`.

**BR-D04 - Pembuatan data absensi idempoten.**
Dijalankan berulang tidak membuat duplikat dan tidak mengubah snapshot terkunci.
Penegakan: unique `schedule_id`, `upsertRecord()`.

**BR-D05 - Periode tanpa jadwal tidak diproses.**
Penegakan: `AttendanceGenerator::generateAll()`.

**BR-D06 - Generator dan scheduler memakai service yang sama.**
Penegakan: `AttendanceGenerateCommand`, `routes/console.php`.

## E. Data Bermasalah (Anomali)

**BR-E01 - Satu baris per kombinasi `(schedule_id, anomaly_type)`.**
Penegakan: unique portabel pada migration.

**BR-E02 - Anomali yang masih terjadi diperbarui `detected_at`-nya.**
Anomali yang sudah tidak terdeteksi diisi `resolved_at`, `resolved_by`, dan
`resolution_note`.
Penegakan: `AttendanceGenerator::syncAnomalies()`.

**BR-E03 - Anomali global (pengaturan tidak valid) tidak disimpan di tabel
anomali.**
Hanya ditampilkan pada halaman Kesiapan Absensi.
Penegakan: `AttendanceReadinessChecker`, `AttendanceGenerator`.

## F. Koreksi Absensi

**BR-F01 - Maksimal satu koreksi `pending` per record.**
Dijaga dengan transaksi + row lock; bukan partial unique index.
Penegakan: `AttendanceCorrectionService::request()`.

**BR-F02 - Riwayat koreksi bersifat append-only.**
Koreksi yang sudah diputuskan tidak diubah atau dihapus.
Penegakan: service hanya membuat baris baru dan mengisi keputusan sekali.

**BR-F03 - Status hasil koreksi hanya boleh `in_progress`, `completed`,
`absent`, atau `day_off`.**
`requested_status` tidak boleh `scheduled` atau `pending`.
Penegakan: `AttendanceRecord::CORRECTABLE_STATUSES`, validasi controller dan
service.

**BR-F04 - Status dinamis memerlukan status tujuan eksplisit.**
Bila status efektif saat ini masih `scheduled`/`pending`, koreksi wajib
menyertakan `requested_status`.
Penegakan: `AttendanceCorrectionService::targetStatus()`.

**BR-F05 - Nilai koreksi disimpan terpisah dari bukti asli.**
Waktu asli, koordinat, dan akurasi tidak ditimpa; hasil koreksi masuk ke kolom
`corrected_*`.
Penegakan: `AttendanceCorrectionService::approve()`.

**BR-F06 - Keputusan koreksi mencatat peninjau, waktu, dan catatan.**
Penegakan: kolom `reviewed_by`, `reviewed_at`, `review_note`; snapshot
`before_*` dan `applied_*`.

**BR-F07 - Koreksi yang disetujui mengunci snapshot.**
Penegakan: `AttendanceCorrectionService::approve()`.

**BR-F08 - Satpam hanya dapat mengajukan koreksi untuk record miliknya.**
Penegakan: `App\Http\Controllers\App\AttendanceController::ownRecord()`.

**BR-F09 - Admin Unit hanya dapat memutuskan koreksi dalam lingkup unitnya
(termasuk unit turunan); Superadmin tanpa batas.**
Admin Unit boleh menyetujui pengajuannya sendiri.
Penegakan: `ResolvesAttendanceScope`, `AttendanceCorrectionController`.

## G. Hak Akses

**BR-G01 - Pengaturan Absensi hanya untuk Superadmin.**
Penegakan: route middleware `role:superadmin`.

**BR-G02 - Kesiapan, data absensi, dan pengajuan koreksi untuk Admin Unit dan
Superadmin.**
Penegakan: route middleware `role:admin,superadmin`.

**BR-G03 - Halaman absensi aplikasi hanya untuk Satpam.**
Penegakan: route middleware `role:security`.

**BR-G04 - Lingkup unit Admin Unit mencakup unit miliknya dan seluruh unit
turunannya.**
Penegakan: `Unit::descendantIds()`, `ResolvesAttendanceScope`.
