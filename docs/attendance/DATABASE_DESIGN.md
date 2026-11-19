# Rancangan Database - Absensi Satpam

Empat tabel baru ditambahkan tanpa mengubah tabel modul Penjadwalan. Semua
foreign key memakai `restrictOnDelete` kecuali kolom penanda yang boleh kosong.

## 1. `attendance_settings`

Menyimpan satu baris pengaturan global. Baris pertama (id terkecil) dianggap
aktif melalui `AttendanceSetting::current()`.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | tidak | auto | Primary key |
| `gps_radius_meters` | SMALLINT UNSIGNED | tidak | 50 | Radius lokasi (meter) |
| `check_in_open_minutes` | SMALLINT UNSIGNED | tidak | 15 | Check-in dibuka sebelum shift |
| `late_tolerance_minutes` | SMALLINT UNSIGNED | tidak | 5 | Batas tepat waktu |
| `early_checkout_tolerance_minutes` | SMALLINT UNSIGNED | tidak | 0 | Toleransi check-out awal |
| `checkout_close_minutes` | SMALLINT UNSIGNED | tidak | 60 | Batas tutup check-out dan penetapan Tidak Hadir |
| `max_gps_accuracy_meters` | SMALLINT UNSIGNED | tidak | 50 | Akurasi lokasi maksimal |
| `updated_by` | BIGINT UNSIGNED | ya | null | FK `users.id`, null on delete |
| `created_at`, `updated_at` | TIMESTAMP | ya | null | |

## 2. `attendance_records`

Snapshot absensi per jadwal.

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | tidak | Primary key |
| `schedule_id` | BIGINT UNSIGNED | tidak | FK `schedules.id`, **unik**, restrict |
| `schedule_period_id` | BIGINT UNSIGNED | tidak | FK `schedule_periods.id` |
| `security_id` | BIGINT UNSIGNED | tidak | FK `securities.id` |
| `security_post_id` | BIGINT UNSIGNED | tidak | FK `security_posts.id` |
| `shift_id` | BIGINT UNSIGNED | tidak | FK `shifts.id` |
| `work_date` | DATE | tidak | Tanggal mulai operasional (WIB) |
| `security_name` | VARCHAR(255) | tidak | Snapshot nama Satpam |
| `security_registration_number` | VARCHAR(255) | ya | Snapshot NIP |
| `unit_id` | BIGINT UNSIGNED | tidak | FK `units.id` |
| `unit_name` | VARCHAR(255) | tidak | Snapshot nama unit |
| `security_team_id` | BIGINT UNSIGNED | tidak | FK `security_teams.id` |
| `security_team_name` | VARCHAR(255) | tidak | Snapshot nama regu |
| `security_post_name` | VARCHAR(255) | tidak | Snapshot nama pos |
| `shift_name`, `shift_code` | VARCHAR(255) | tidak | Snapshot shift |
| `shift_type` | VARCHAR(16) | tidak | `working` / `day_off` |
| `shift_start_time`, `shift_end_time` | TIME | ya | Null untuk Libur |
| `shift_end_day_offset` | TINYINT UNSIGNED | tidak | 0 / 1 |
| `scheduled_start_at`, `scheduled_end_at` | DATETIME | ya | Waktu jadwal tersimpan (UTC) |
| `post_latitude` | DECIMAL(10,8) | ya | Snapshot koordinat pos |
| `post_longitude` | DECIMAL(11,8) | ya | Snapshot koordinat pos |
| `setting_gps_radius_meters` | SMALLINT UNSIGNED | tidak | Snapshot pengaturan |
| `setting_check_in_open_minutes` | SMALLINT UNSIGNED | tidak | Snapshot pengaturan |
| `setting_late_tolerance_minutes` | SMALLINT UNSIGNED | tidak | Snapshot pengaturan |
| `setting_early_checkout_tolerance_minutes` | SMALLINT UNSIGNED | tidak | Snapshot pengaturan |
| `setting_checkout_close_minutes` | SMALLINT UNSIGNED | tidak | Snapshot pengaturan |
| `setting_max_gps_accuracy_meters` | SMALLINT UNSIGNED | tidak | Snapshot pengaturan |
| `check_in_at` | DATETIME | ya | Waktu server check-in |
| `check_in_captured_at` | DATETIME | ya | Waktu perangkat |
| `check_in_latitude`, `check_in_longitude` | DECIMAL | ya | Koordinat perangkat |
| `check_in_accuracy_meters` | DECIMAL(8,2) | ya | Akurasi perangkat |
| `check_in_distance_meters` | DECIMAL(10,2) | ya | Jarak hasil Haversine |
| `check_in_geofence_valid` | BOOLEAN | ya | Bukti validasi radius |
| `check_out_at` dan kolom `check_out_*` | DATETIME/DECIMAL/BOOLEAN | ya | Sama seperti check-in |
| `corrected_check_in_at` | DATETIME | ya | Hasil koreksi disetujui |
| `corrected_check_out_at` | DATETIME | ya | Hasil koreksi disetujui |
| `corrected_status` | VARCHAR(20) | ya | Status hasil koreksi |
| `status` | VARCHAR(20) | tidak | Status tersimpan, default `scheduled` |
| `absent_marked_at` | DATETIME | ya | Waktu ditetapkan Tidak Hadir |
| `snapshot_locked_at` | DATETIME | ya | Kunci snapshot permanen |
| `last_synced_at` | DATETIME | ya | Sinkronisasi snapshot terakhir |
| `created_at`, `updated_at` | TIMESTAMP | ya | |

Status tersimpan: `scheduled`, `in_progress`, `completed`, `absent`, `day_off`.
Status `pending` bersifat dinamis dan tidak pernah dipersistensi.

Index:

- unique `schedule_id`
- `(security_id, work_date)`
- `(schedule_period_id, work_date)`
- `(security_post_id, work_date)`
- `(unit_id, work_date)`
- `status`, `shift_type`, `work_date`, `scheduled_end_at`

## 3. `attendance_corrections`

Riwayat pengajuan koreksi (append-only).

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | tidak | Primary key |
| `attendance_record_id` | BIGINT UNSIGNED | tidak | FK `attendance_records.id` |
| `requested_by` | BIGINT UNSIGNED | tidak | FK `users.id` |
| `requested_by_role` | VARCHAR(20) | tidak | Snapshot peran pengaju |
| `reason` | TEXT | tidak | Alasan pengajuan |
| `requested_check_in_at` | DATETIME | ya | Usulan check-in (UTC) |
| `requested_check_out_at` | DATETIME | ya | Usulan check-out (UTC) |
| `requested_status` | VARCHAR(20) | ya | Usulan status |
| `status` | VARCHAR(20) | tidak | `pending` / `approved` / `rejected` |
| `reviewed_by` | BIGINT UNSIGNED | ya | FK `users.id` |
| `reviewed_at` | DATETIME | ya | Waktu keputusan |
| `review_note` | TEXT | ya | Catatan peninjau |
| `before_check_in_at`, `before_check_out_at` | DATETIME | ya | Nilai efektif sebelum koreksi |
| `before_status` | VARCHAR(20) | ya | Status efektif sebelum koreksi |
| `applied_check_in_at`, `applied_check_out_at` | DATETIME | ya | Nilai efektif setelah koreksi |
| `applied_status` | VARCHAR(20) | ya | Status efektif setelah koreksi |
| `created_at`, `updated_at` | TIMESTAMP | ya | |

Index: `(attendance_record_id, status)`, `requested_by`, `reviewed_by`,
`status`, `(attendance_record_id, created_at)`.

Catatan: aturan "maksimal satu `pending` per record" ditegakkan di aplikasi
dengan transaksi + `lockForUpdate`, bukan partial unique index, agar portabel
di MySQL dan SQLite.

## 4. `attendance_generation_anomalies`

Data bermasalah yang menghalangi pembuatan absensi untuk satu jadwal.

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | tidak | Primary key |
| `schedule_id` | BIGINT UNSIGNED | tidak | FK `schedules.id` |
| `schedule_period_id` | BIGINT UNSIGNED | tidak | FK `schedule_periods.id` |
| `security_id` | BIGINT UNSIGNED | ya | FK `securities.id` |
| `work_date` | DATE | ya | Tanggal jadwal |
| `anomaly_type` | VARCHAR(50) | tidak | Lihat daftar tipe |
| `details` | TEXT | ya | Pesan masalah |
| `detected_at` | DATETIME | tidak | Waktu deteksi/refresh |
| `resolved_at` | DATETIME | ya | Waktu selesai |
| `resolved_by` | BIGINT UNSIGNED | ya | FK `users.id`, null on delete |
| `resolution_note` | TEXT | ya | Catatan penyelesaian |
| `created_at`, `updated_at` | TIMESTAMP | ya | |

Unique `(schedule_id, anomaly_type)`. Index: `resolved_at`, `anomaly_type`,
`schedule_period_id`, `work_date`, `security_id`.

Tipe anomali:

- `missing_shift`
- `invalid_shift`
- `incomplete_working_time`
- `missing_post_coordinates`
- `invalid_security_or_membership`
- `invalid_schedule_relation`

Anomali global (pengaturan tidak valid) **tidak** disimpan di tabel ini.

## Relasi Ringkas

```text
schedules 1---1 attendance_records
attendance_records 1---N attendance_corrections
schedules 1---N attendance_generation_anomalies
units 1---N attendance_records
security_posts 1---N attendance_records
shifts 1---N attendance_records
users 1---N attendance_records (tidak langsung, lewat security)
```

## Kompatibilitas

- Skema memakai tipe dan index portabel untuk SQLite (test) dan MySQL 8.4
  (target produksi).
- Tidak ada partial unique index.
- Tidak ada perubahan pada tabel modul Penjadwalan.
