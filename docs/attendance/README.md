# Dokumentasi Modul Attendance

Dokumen ini menjelaskan modul absensi (attendance) pada aplikasi: kegunaan tabel/database, komponen & fungsi, serta alur kerja yang berjalan **manual** maupun **otomatis**. Setiap bagian menyertakan path file terkait.

## 1. Gambaran Umum

- **Tujuan**: membentuk data absensi dari jadwal satpam, melakukan validasi kesiapan data, mencatat check-in/check-out berbasis GPS/geofence, menangani koreksi, dan memantau anomali pembuatan data.
- **Zona waktu**: data waktu disimpan di timezone aplikasi/storage (umumnya UTC) dan ditampilkan/diinput dalam timezone operasional **WIB (`Asia/Jakarta`)**. Konversi terpusat di `app/Support/AttendanceClock.php`.
- **Sumber jadwal**: absensi diturunkan dari `Schedule`/`SchedulePeriod` (modul penjadwalan) melalui proses generator.
- **Scope akses unit**: admin dibatasi ke unitnya + turunannya, superadmin tanpa batas, melalui `app/Http/Controllers/Concerns/ResolvesAttendanceScope.php`.
- **Rute utama**: didefinisikan di `routes/web.php` (grup admin `/admin/attendance-*` dan grup satpam `/app/attendance*`).

**Catatan implementasi (berdasarkan isi repo saat ini):**
- Rute satpam mengacu ke `App\Http\Controllers\App\AttendanceController` (`routes/web.php:19,156-169`), namun file controller `app/Http/Controllers/App/AttendanceController.php` dan folder `app/Http/Controllers/App/` serta halaman `resources/js/Pages/App/Attendance/` tidak ditemukan di workspace saat ini. Flow aktivitas satpam di bawah dideskripsikan dari route yang didefinisikan dan service yang tersedia (`app/Services/AttendanceActivityService.php`, `app/Services/AttendanceCorrectionService.php`).

---

## 2. Struktur Database (Migrasi & Kegunaan Tabel)

### 2.1 `attendance_settings`
- **Migrasi**: `database/migrations/2026_11_11_000001_create_attendance_settings_table.php`
- **Model**: `app/Models/AttendanceSetting.php`
- **Kegunaan**: menyimpan **konfigurasi global tunggal** modul absensi (digunakan sebagai parameter validasi/generate/aktivitas).
- **Kolom utama**:
  - `gps_radius_meters` (default 50): radius geofence dari koordinat pos.
  - `check_in_open_minutes` (default 15): berapa menit sebelum jadwal mulai check-in dibuka.
  - `late_tolerance_minutes` (default 5): toleransi keterlambatan.
  - `early_checkout_tolerance_minutes` (default 0): toleransi check-out lebih awal sebelum shift selesai.
  - `checkout_close_minutes` (default 60): batas waktu setelah shift selesai (penutupan check-out / deadline penandaan absen).
  - `max_gps_accuracy_meters` (default 50): batas akurasi GPS perangkat.
  - `updated_by` (nullable FK → `users`, `nullOnDelete`), timestamps.
- **Perilaku model**: `defaults()`, `current()` (baris aktif pertama), `validationIssues()`/`isValid()` (nilai wajib valid, radius & akurasi maks > 0).

### 2.2 `attendance_records`
- **Migrasi**: `database/migrations/2026_11_11_000002_create_attendance_records_table.php`
- **Model**: `app/Models/AttendanceRecord.php`
- **Kegunaan**: menyimpan **snapshot absensi per jadwal** (1 record per `schedule_id`) beserta data konteks yang dibekukan, bukti aktivitas, koreksi efektif, dan status.
- **Relasi/kunci konteks**: `schedule_id` (unik, restrict), `schedule_period_id`, `security_id`, `security_post_id`, `shift_id`, `unit_id`, `security_team_id`.
- **Snapshot data**: identitas & nama (satpam/NRP, unit, regu, pos, shift), `work_date`, tipe/waktu shift (`shift_type`, `shift_start_time/end_time`, `shift_end_day_offset`), `scheduled_start_at/end_at` (UTC), koordinat pos (`post_latitude/longitude`), salinan parameter setting saat generate (`setting_*`).
- **Bukti aktivitas asli (tidak diubah koreksi)**: `check_in_*` dan `check_out_*` (waktu server, captured_at, lat/long, akurasi, jarak, geofence_valid).
- **Koreksi efektif**: `corrected_check_in_at`, `corrected_check_out_at`, `corrected_status`.
- **Status & penguncian**: `status` (default `scheduled`), `absent_marked_at`, `snapshot_locked_at`, `last_synced_at`, timestamps.
- **Index**: `(security_id, work_date)`, `(schedule_period_id, work_date)`, `(security_post_id, work_date)`, `(unit_id, work_date)`, `status`, `shift_type`, `work_date`, `scheduled_end_at`.
- **Status (konstanta model)**: `scheduled` (Belum Waktunya), `pending` (Menunggu Check-in, dinamis), `in_progress` (Sedang Bertugas), `completed` (Selesai), `absent` (Tidak Hadir), `day_off` (Libur). Status yang dapat dihasilkan koreksi: `in_progress`, `completed`, `absent`, `day_off`.
- **Logika penting model**:
  - `effectiveCheckInAt/Out()`: nilai koreksi meng-override nilai asli.
  - `effectiveStatus($now)`: mengutamakan `corrected_status`, lalu `day_off`, lalu status tersimpan final (`in_progress/completed/absent`), lalu menghitung dinamis `pending` jika sudah melewati waktu buka check-in, selain itu `scheduled`.
  - `isLate()`, `canSyncSnapshot()` (tidak terkunci, tidak ada aktivitas tercatat, tidak ada koreksi diterapkan/pending).

### 2.3 `attendance_corrections`
- **Migrasi**: `database/migrations/2026_11_11_000003_create_attendance_corrections_table.php`
- **Model**: `app/Models/AttendanceCorrection.php`
- **Kegunaan**: workflow **pengajuan dan keputusan koreksi** absensi, dengan jejak audit sebelum/sesudah.
- **Kolom utama**: `attendance_record_id`, `requested_by`, `requested_by_role`, `reason`, `requested_check_in_at/out_at`, `requested_status`, `status` (`pending/approved/rejected`), `reviewed_by/at`, `review_note`, snapshot audit `before_*` dan `applied_*` (check-in/out/status).
- **Index**: `(attendance_record_id,status)`, `requested_by`, `reviewed_by`, `status`, `(attendance_record_id,created_at)`.
- **Aturan bisnis**: koreksi **tidak mengubah** bukti GPS/timestamp asli pada `attendance_records`; hasil persetujuan disimpan ke kolom `corrected_*` record (lihat `AttendanceCorrectionService`).

### 2.4 `attendance_generation_anomalies`
- **Migrasi**: `database/migrations/2026_11_11_000004_create_attendance_generation_anomalies_table.php`
- **Model**: `app/Models/AttendanceGenerationAnomaly.php`
- **Kegunaan**: mencatat **anomali/masalah data** yang terdeteksi saat readiness/generate agar dapat dimonitor dan ditandai selesai otomatis saat data diperbaiki.
- **Kolom utama**: `schedule_id`, `schedule_period_id`, `security_id` (nullable), `work_date` (nullable), `anomaly_type` (50), `details`, `detected_at`, `resolved_at`, `resolved_by` (nullable FK `users`, `nullOnDelete`), `resolution_note`.
- **Constraint/index**: unik `['schedule_id','anomaly_type']` (`attendance_generation_anomalies_schedule_type_unique`), index `resolved_at`, `anomaly_type`, `schedule_period_id`, `work_date`, `security_id`.
- **Tipe anomali (model)**: `missing_shift`, `invalid_shift`, `incomplete_working_time`, `missing_post_coordinates`, `invalid_security_or_membership`, `invalid_schedule_relation` beserta label Bahasa Indonesia (`TYPE_LABELS`).
- **Helper**: `scopeActive()` (`resolved_at` null), `isActive()`, `typeLabel()`.

---

## 3. Komponen Pendukung (Support)

### 3.1 Konversi Waktu
- **File**: `app/Support/AttendanceClock.php`
- **Fungsi**: sentralisasi konversi antara storage timezone dan WIB operasional; penyedia waktu kini (`nowUtc`, `nowWib`, `todayWib`), konversi (`wibToUtc`, `utcToWib`), pembentuk waktu terjadwal shift (`scheduledStart`, `scheduledEnd` dengan `endDayOffset` untuk lintas hari), dan formatter tampil/input WIB (`formatWib`, `formatWibInput`).

### 3.2 Perhitungan Jarak Geofence
- **File**: `app/Support/GeoDistance.php`
- **Fungsi**: `meters(...)` menghitung jarak Haversine (meter) antara koordinat perangkat dan koordinat pos untuk validasi geofence sisi server.

---

## 4. Model & Relasi

| File | Kegunaan |
|---|---|
| `app/Models/AttendanceSetting.php` | Konfigurasi global, default, validasi, relasi `updater`. |
| `app/Models/AttendanceRecord.php` | Snapshot absensi, relasi ke schedule/period/security/post/team/shift/unit & `corrections`, scope/filter, status efektif, flag lock/aktivitas/koreksi, aturan `canSyncSnapshot()`. |
| `app/Models/AttendanceCorrection.php` | Entitas koreksi, status pending/approved/rejected, relasi record/requester/reviewer. |
| `app/Models/AttendanceGenerationAnomaly.php` | Entitas anomali, tipe/label, relasi schedule/period/security/resolver, scope aktif. |

**Model domain terkait (dipakai modul attendance):** `app/Models/Schedule.php`, `app/Models/SchedulePeriod.php` (implisit), `app/Models/Security.php`, `app/Models/SecurityPost.php`, `app/Models/SecurityTeam.php`, `app/Models/Shift.php`, `app/Models/Unit.php`, `app/Models/User.php`.

---

## 5. Service & Fungsi Bisnis

### 5.1 Pemeriksaan Kesiapan (Readiness)
- **File**: `app/Services/AttendanceReadinessChecker.php`
- **Fungsi utama**:
  - `check(?$unitIds, ?$periodId)`: memeriksa `AttendanceSetting` global dan tiap `SchedulePeriod` dalam scope; mengembalikan laporan terstruktur (`ready`, `global`, `periods`, `summary`).
  - `checkGlobalSettings()`: memastikan setting ada dan valid (`validationIssues`).
  - `checkPeriod($period, $globalReady)`: memuat jadwal+relasi, mendeteksi isu per jadwal/periode (periode tanpa jadwal; data satpam/akun/keanggotaan tidak valid; relasi unit/pos/regu tidak valid/tidak aktif; shift hilang/tidak valid/bukan milik unit; jam kerja shift tidak lengkap/offset tidak valid; koordinat pos wajib & valid bila ada jadwal kerja). Menghitung `ready_schedule_count` dan status `ready` periode.
  - Helper: `workingTimeIssue()`, `postHasCoordinates()`, `issuesBySchedule()`, `today()`.
- **Sifat**: read-only (tidak menulis record absensi).

### 5.2 Generator & Sinkronisasi Absensi
- **File**: `app/Services/AttendanceGenerator.php`
- **Fungsi utama**:
  - `generateAll(?$actor, ?$unitIds, ?$periodId)`: menjalankan readiness, jika global tidak siap langsung stop; jika siap iterasi periode dalam scope dan agregasi hasil (`created/synced/unchanged/locked/anomalies/errors`).
  - `generatePeriod($period, $actor)`: berjalan dalam **transaksi DB + lock periode**. Jika tidak siap → sinkronkan anomali dari issues dan kembalikan `ready=false` (tanpa upsert record). Jika siap (dan setting valid) → resolve anomali lama (`syncAnomalies(..., [], ...)`) lalu lock & proses seluruh jadwal periode via `upsertRecord`.
  - `upsertRecord(...)`: buat record baru bila belum ada (set status `day_off` + lock untuk shift libur, atau `scheduled` untuk kerja, set `last_synced_at`); jika ada dan tidak `canSyncSnapshot()` → `locked`; jika bisa sync → perbarui snapshot, pertahankan perilaku day_off/lock, simpan hanya bila berubah.
  - `buildSnapshot(...)`: membangun snapshot (identitas, relasi, waktu terjadwal UTC via `AttendanceClock`, koordinat pos, salinan parameter setting).
  - `syncAnomalies(...)`: `updateOrCreate` anomali aktif dari issues (`schedule_id`+`anomaly_type`), lalu auto-resolve anomali aktif periode yang tidak lagi muncul (set `resolved_at`, `resolved_by`, catatan perbaikan). Return jumlah anomali tersentuh.

### 5.3 Evaluasi Status Tidak Hadir (Absent)
- **File**: `app/Services/AttendanceStatusEvaluator.php`
- **Fungsi utama**:
  - `markAbsent(?$now)`: memproses chunk record kerja `scheduled` tanpa `check_in_at`/`corrected_check_in_at`/`snapshot_locked_at` dan memiliki `scheduled_end_at`; jika melewati deadline (`scheduled_end_at + setting_checkout_close_minutes`) maka set `status=absent`, `absent_marked_at`, `snapshot_locked_at` (dalam transaksi + lock per record).
- **Sifat**: hanya menetapkan absen final; status dinamis (`scheduled/pending`) tidak dipersist oleh service ini.

### 5.4 Aktivitas Check-in / Check-out Satpam
- **File**: `app/Services/AttendanceActivityService.php`
- **Fungsi utama**:
  - `checkIn($record, $payload, $now)` / `checkOut($record, $payload, $now)`: transaksi + lock record; validasi shift kerja & ketersediaan koordinat pos; validasi urutan/duplikasi dan window waktu (buka/tutup berbasis parameter setting & jadwal); validasi GPS server-side (`latitude/longitude/accuracy` valid, akurasi <= batas, jarak <= radius via `GeoDistance`); simpan bukti (`*_at`, `*_captured_at`, koordinat/akurasi/jarak/geofence), set status (`in_progress` / `completed`) dan `snapshot_locked_at`.
  - `parseCapturedAt()`: interpretasi waktu tangkapan (offset-aware → storage tz, tanpa offset → diasumsikan WIB).
- **Validasi waktu (ringkas)**: check-in dibuka `scheduled_start - check_in_open_minutes` s.d. `scheduled_end`; check-out dibuka `scheduled_end - early_checkout_tolerance_minutes` s.d. `scheduled_end + checkout_close_minutes`.

### 5.5 Koreksi Absensi
- **File**: `app/Services/AttendanceCorrectionService.php`
- **Fungsi utama**:
  - `request($record, $actor, $data)`: tolak jika ada koreksi `pending`; normalisasi waktu (WIB→UTC) & status yang diminta; wajib ada minimal satu perubahan; validasi konsistensi waktu-status terhadap target efektif.
  - `approve($correction, $reviewer, $note)`: terapkan ke kolom `corrected_*` record sesuai permintaan, set lock snapshot, simpan audit `before/applied`, tandai koreksi approved.
  - `reject($correction, $reviewer, $note)`: tandai rejected + catatan.
  - Validasi konsistensi: `completed` butuh in & out; `in_progress` butuh in & tanpa out; `absent/day_off` tanpa aktivitas efektif; out >= in.
- **Prinsip**: bukti GPS & timestamp asli pada record tidak diubah.

---

## 6. Scope, Controller, dan Route

### 6.1 Scope Unit
- **File**: `app/Http/Controllers/Concerns/ResolvesAttendanceScope.php`
- **Fungsi**: menentukan scope unit user (`attendanceScope`), pembatasan akses (`assertAttendanceUnit`), aturan validasi unit (`attendanceUnitRules`), filter unit aman (`attendanceFilterUnitId`), daftar unit untuk UI (`attendanceUnits`).

### 6.2 Controller Admin
| File | Route (name) | Kegunaan / Aksi |
|---|---|---|
| `app/Http/Controllers/Admin/AttendanceReadinessController.php` | `GET /admin/attendance/readiness` (`admin.attendance.readiness`) | Tampilkan laporan readiness + daftar anomali aktif (filter scope/unit/periode) ke Inertia `Admin/Attendance/Readiness`. |
| `app/Http/Controllers/Admin/AttendanceGenerationController.php` | `POST /admin/attendance/generate` (`admin.attendance.generate`) | Trigger `AttendanceGenerator::generateAll` (manual), kembalikan flash sukses/error & hasil. |
| `app/Http/Controllers/Admin/AttendanceRecordController.php` | `GET /admin/attendance-record` (`admin.attendance-record.index`), `GET /admin/attendance-record/{id}` (`admin.attendance-record.show`) | Daftar (filter/sort/status efektif, pagination) & detail record + riwayat koreksi + anomali per schedule. Render `Admin/Attendance/Records/Index|Show`. |
| `app/Http/Controllers/Admin/AttendanceCorrectionController.php` | `GET /admin/attendance-correction` (`admin.attendance-correction.index`), `POST /admin/attendance-correction/{id}/approve` (`admin.attendance-correction.approve`), `POST /admin/attendance-correction/{id}/reject` (`admin.attendance-correction.reject`) | Daftar pengajuan koreksi & aksi approve/reject via `AttendanceCorrectionService`. Render `Admin/Attendance/Corrections/Index`. |
| `app/Http/Controllers/Admin/AttendanceSettingController.php` | `GET /admin/attendance-setting` (`admin.attendance-setting.edit`), `PUT /admin/attendance-setting` (`admin.attendance-setting.update`) — **middleware `role:superadmin`** | Lihat/ubah pengaturan global absensi. Render `Admin/AttendanceSetting/Edit`. |

- **Definisi route**: `routes/web.php:92-117` (admin, auth + role admin/superadmin).

### 6.3 Controller & Route Satpam (Aplikasi)
- **Route**: `routes/web.php:153-169` (prefix `/app`, middleware `auth`, `role:security`, `security.profile`).
- **Endpoint yang didefinisikan**: `GET /app/attendance` (`app.attendance.index`), `GET /app/attendance/history` (`app.attendance.history`), `GET /app/attendance/correction` (`app.attendance.correction.index`), `POST /app/attendance/{id}/check-in` (`app.attendance.check-in`), `POST /app/attendance/{id}/check-out` (`app.attendance.check-out`), `POST /app/attendance/{id}/correction` (`app.attendance.correction`).
- **Referensi kode**: `use App\Http\Controllers\App\AttendanceController as AppAttendanceController;` (`routes/web.php:19`) — **file controller tidak ditemukan di repo saat ini** (lihat catatan implementasi di atas). Logika eksekusi check-in/out & request koreksi tersedia di service: `app/Services/AttendanceActivityService.php`, `app/Services/AttendanceCorrectionService.php`.

---

## 7. Command Artisan & Proses Otomatis (Scheduler)

### 7.1 Command
| File | Signature | Kegunaan |
|---|---|---|
| `app/Console/Commands/AttendanceReadinessCommand.php` | `attendance:readiness {--period=} {--unit=}` | Cek kesiapan data (global & per periode) via `AttendanceReadinessChecker`; output status/isu. (manual/CLI) |
| `app/Console/Commands/AttendanceGenerateCommand.php` | `attendance:generate {--period=} {--unit=}` | Generate/sinkronkan absensi via `AttendanceGenerator::generateAll`; lapor ringkasan & error. (manual/CLI & terjadwal) |
| `app/Console/Commands/AttendanceEvaluateAbsentCommand.php` | `attendance:evaluate-absent` | Tetapkan status `Tidak Hadir` untuk record yang lewat deadline via `AttendanceStatusEvaluator::markAbsent`. (manual/CLI & terjadwal) |

### 7.2 Scheduler
- **File**: `routes/console.php`
- **Jadwal otomatis** (tanpa intervensi manual, selama scheduler Laravel berjalan):
  - `Schedule::command('attendance:generate')->everyFifteenMinutes()->withoutOverlapping();`
  - `Schedule::command('attendance:evaluate-absent')->everyFifteenMinutes()->withoutOverlapping();`

---

## 8. Antarmuka (UI/Inertia Pages)

| File | Kegunaan |
|---|---|
| `resources/js/Pages/Admin/Attendance/Readiness.vue` | Halaman kesiapan & anomali (admin). |
| `resources/js/Pages/Admin/Attendance/Records/Index.vue` | Daftar record absensi (admin). |
| `resources/js/Pages/Admin/Attendance/Records/Show.vue` | Detail record, bukti, koreksi, anomali (admin). |
| `resources/js/Pages/Admin/Attendance/Corrections/Index.vue` | Daftar & review koreksi (admin). |
| `resources/js/Pages/Admin/AttendanceSetting/Edit.vue` | Pengaturan absensi global (superadmin). |

(Catatan: halaman sisi satpam `/app/attendance*` tidak ditemukan di `resources/js/Pages/App/Attendance/` pada repo saat ini.)

---

## 9. Flow Lengkap Modul Attendance

### 9.1 Persiapan Data (Manual)
1. **Superadmin** mengisi/memperbarui pengaturan global: `GET/PUT admin.attendance-setting.*` → `app/Http/Controllers/Admin/AttendanceSettingController.php` → simpan ke `attendance_settings` (`app/Models/AttendanceSetting.php`).
2. **Admin** memastikan data prasyarat penjadwalan siap (jadwal/periode, keanggotaan, shift valid & jam kerja lengkap, pos jaga dengan koordinat valid untuk shift kerja, relasi unit/regu/pos konsisten) — divalidasi oleh `app/Services/AttendanceReadinessChecker.php`.

### 9.2 Cek Kesiapan (Manual / CLI)
1. Admin membuka `admin.attendance.readiness` (`app/Http/Controllers/Admin/AttendanceReadinessController.php`) atau menjalankan `php artisan attendance:readiness` (`app/Console/Commands/AttendanceReadinessCommand.php`).
2. Sistem memanggil `AttendanceReadinessChecker::check()` untuk menghasilkan isu global & isu per periode/jadwal.
3. UI readiness juga menampilkan anomali aktif dari `attendance_generation_anomalies` (scope-aware).

### 9.3 Generate / Sinkronisasi Absensi (Manual, Otomatis, CLI)
- **Trigger**:
  - Manual (UI): `POST admin.attendance.generate` → `app/Http/Controllers/Admin/AttendanceGenerationController.php::store`.
  - Otomatis: scheduler tiap 15 menit `attendance:generate` (`routes/console.php`).
  - CLI: `php artisan attendance:generate` (`app/Console/Commands/AttendanceGenerateCommand.php`).
- **Proses** (`app/Services/AttendanceGenerator.php`):
  1. Cek readiness global; jika tidak valid → diblokir (tidak generate).
  2. Per periode (transaksi + lock): cek readiness periode.
     - **Tidak siap**: `syncAnomalies` membuat/memperbarui anomali dari issues, kembalikan error/ready=false (record tidak di-upsert).
     - **Siap**: `syncAnomalies` dengan issues kosong untuk resolve anomali yang sudah tidak relevan, lalu iterasi jadwal dan `upsertRecord`.
  3. `upsertRecord`:
     - **Create**: snapshot dari jadwal/setting; shift libur → `status=day_off` & `snapshot_locked_at`; shift kerja → `status=scheduled`.
     - **Existing & tidak bisa sync** (`!canSyncSnapshot`) → dilewati (`locked`).
     - **Existing & bisa sync** → perbarui snapshot bila berubah (`synced`) atau biarkan (`unchanged`), set `last_synced_at` saat berubah.
  4. Hasil agregat (created/synced/unchanged/locked/anomalies/errors) dikembalikan ke UI/CLI.

### 9.4 Evaluasi Tidak Hadir / Absen (Otomatis, CLI)
- **Trigger**: scheduler tiap 15 menit `attendance:evaluate-absent` (`routes/console.php`) atau CLI `php artisan attendance:evaluate-absent` (`app/Console/Commands/AttendanceEvaluateAbsentCommand.php`).
- **Proses** (`app/Services/AttendanceStatusEvaluator.php`): cari record kerja `scheduled` tanpa check-in/koreksi/lock yang sudah melewati `scheduled_end_at + checkout_close_minutes` → set `absent`, catat `absent_marked_at` dan kunci snapshot. Status dinamis `pending` dihitung saat dibaca (`AttendanceRecord::effectiveStatus`), bukan dipersist oleh evaluator.

### 9.5 Aktivitas Check-in / Check-out (Manual oleh Satpam)
- **Trigger (dirancang di route)**: `POST /app/attendance/{id}/check-in` dan `POST /app/attendance/{id}/check-out` (`routes/web.php:161-166`, mengarah ke `AppAttendanceController` — lihat catatan implementasi).
- **Proses** (`app/Services/AttendanceActivityService.php`):
  1. Validasi record shift kerja, koordinat pos tersedia, belum duplikat, dan dalam window waktu yang diizinkan (berbasis `AttendanceClock` & parameter setting snapshot).
  2. Validasi GPS: koordinat & akurasi valid, akurasi ≤ `setting_max_gps_accuracy_meters`, jarak ke pos ≤ `setting_gps_radius_meters` (server-side via `app/Support/GeoDistance.php`).
  3. Simpan bukti waktu/lokasi server-side, set status (`in_progress` saat check-in, `completed` saat check-out) dan `snapshot_locked_at` (snapshot tidak lagi bisa ditimpa generator).

### 9.6 Koreksi Absensi (Manual Request & Manual Review)
- **Request (dirancang di route satpam)**: `POST /app/attendance/{id}/correction` (`routes/web.php:167-169`) → logika di `app/Services/AttendanceCorrectionService.php::request` (wajib alasan & minimal satu perubahan, tidak boleh ada pending lain, validasi konsistensi).
- **Review (admin)**: `POST admin.attendance-correction.{approve|reject}` → `app/Http/Controllers/Admin/AttendanceCorrectionController.php` → `AttendanceCorrectionService::approve/reject`.
  - **Approve**: tulis hasil ke `corrected_*` pada `attendance_records`, kunci snapshot, simpan jejak `before/applied` pada `attendance_corrections`.
  - **Reject**: simpan keputusan & catatan tanpa mengubah nilai efektif record.
- Bukti GPS/timestamp asli tetap dipertahankan.

### 9.7 Monitoring & Tampilan (Manual/Read)
- Daftar & detail record absensi: `app/Http/Controllers/Admin/AttendanceRecordController.php` (filter status efektif, termasuk dinamis; tampilkan koreksi & anomali terkait schedule).
- Daftar koreksi: `app/Http/Controllers/Admin/AttendanceCorrectionController.php`.
- Kesiapan & anomali aktif: `app/Http/Controllers/Admin/AttendanceReadinessController.php`.
- Pengaturan: `app/Http/Controllers/Admin/AttendanceSettingController.php`.

---

## 10. Ringkasan Manual vs Otomatis

| Aksi | Manual | Otomatis | Komponen Utama (path) |
|---|---|---|---|
| Isi/ubah pengaturan global | Ya (superadmin) | Tidak | `app/Http/Controllers/Admin/AttendanceSettingController.php`, `app/Models/AttendanceSetting.php` |
| Cek kesiapan | Ya (UI/CLI) | Tidak | `app/Services/AttendanceReadinessChecker.php`, `app/Http/Controllers/Admin/AttendanceReadinessController.php`, `app/Console/Commands/AttendanceReadinessCommand.php` |
| Generate/sinkronisasi record & kelola anomali | Ya (UI/CLI) | Ya (tiap 15 menit) | `app/Services/AttendanceGenerator.php`, `app/Http/Controllers/Admin/AttendanceGenerationController.php`, `app/Console/Commands/AttendanceGenerateCommand.php`, `routes/console.php` |
| Tandai Tidak Hadir | Bisa via CLI | Ya (tiap 15 menit) | `app/Services/AttendanceStatusEvaluator.php`, `app/Console/Commands/AttendanceEvaluateAbsentCommand.php`, `routes/console.php` |
| Check-in / Check-out | Ya (satpam) | Tidak | `app/Services/AttendanceActivityService.php`, `app/Support/AttendanceClock.php`, `app/Support/GeoDistance.php`, route `routes/web.php:161-166` |
| Ajukan koreksi | Ya | Tidak | `app/Services/AttendanceCorrectionService.php`, route `routes/web.php:167-169` |
| Approve/Reject koreksi | Ya (admin) | Tidak | `app/Http/Controllers/Admin/AttendanceCorrectionController.php`, `app/Services/AttendanceCorrectionService.php` |

---

## 11. Catatan Penting
- **Idempoten & aman**: satu record per `schedule_id`; snapshot tidak ditimpa bila terkunci/ada aktivitas/koreksi diterapkan atau pending (`AttendanceRecord::canSyncSnapshot`, `AttendanceGenerator::upsertRecord`).
- **Status dinamis**: `pending`/`scheduled` dapat ditentukan oleh `effectiveStatus()` berdasarkan waktu (WIB/UTC terkonversi), tidak selalu tersimpan sebagai transisi oleh evaluator.
- **Anomali per jadwal+tipe**: unik `(schedule_id, anomaly_type)`; resolved otomatis saat issue hilang pada proses sinkronisasi/generate yang siap.
- **Shift lintas hari**: `scheduled_end_at` dihitung dengan `end_day_offset` (`AttendanceClock::scheduledEnd`).
- **Referensi file inti**: migrasi `database/migrations/2026_11_11_000001..0004_*`, model `app/Models/Attendance*`, service `app/Services/Attendance*`, support `app/Support/AttendanceClock.php` & `app/Support/GeoDistance.php`, controller `app/Http/Controllers/Admin/Attendance*` & `app/Http/Controllers/Concerns/ResolvesAttendanceScope.php`, command `app/Console/Commands/Attendance*`, route `routes/web.php`, scheduler `routes/console.php`.
