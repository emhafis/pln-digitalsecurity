# Alur Sistem - Absensi Satpam

## 1. Komponen Utama

| Komponen | Tanggung jawab |
|---|---|
| `App\Support\AttendanceClock` | Konversi WIB <-> zona penyimpanan, perhitungan waktu jadwal. |
| `App\Support\GeoDistance` | Perhitungan jarak Haversine. |
| `App\Services\AttendanceReadinessChecker` | Validasi pengaturan global dan kesiapan per periode. |
| `App\Services\AttendanceGenerator` | Pembuatan/sinkronisasi record dan anomaly. |
| `App\Services\AttendanceStatusEvaluator` | Penetapan Tidak Hadir. |
| `App\Services\AttendanceActivityService` | Check-in, check-out, validasi waktu dan GPS. |
| `App\Services\AttendanceCorrectionService` | Pengajuan, persetujuan, dan penolakan koreksi. |
| `App\Http\Controllers\Concerns\ResolvesAttendanceScope` | Lingkup unit Admin Unit/Superadmin. |
| Command + scheduler | Menjalankan generator dan evaluator berkala. |
| Halaman Inertia/Vue | Kesiapan, data absensi, koreksi, pengaturan, aplikasi Satpam. |

## 2. Alur Kesiapan

```text
Superadmin/Admin membuka /admin/attendance/readiness
        |
        v
AttendanceReadinessChecker::check(unitId?, periodId?)
        |
        +--> Pengaturan global valid?
        |        tidak -> issues global, tidak ada proses
        |
        +--> Untuk setiap periode (dalam scope):
                 - unit tersedia
                 - pos jaga memiliki koordinat
                 - regu aktif
                 - jumlah jadwal > 0
                 - per jadwal: shift, jam kerja, Satpam/membership, relasi
        |
        v
Ringkasan { global, periods, issues } -> halaman Vue
```

Aturan penting:

- Masalah global memblokir semua periode.
- Masalah satu periode hanya memblokir periode tersebut.
- Periode tanpa jadwal tidak diproses dan tidak dianggap siap.
- Kesiapan yang ditampilkan adalah kondisi saat ini, bukan hasil tersimpan.

## 3. Alur Pembuatan Data Absensi

```text
POST /admin/attendance/generate (unit_id?, period_id?)
        |
        v
AttendanceGenerationController
        |
        +--> scope unit (Superadmin semua, Admin unit + turunan)
        |
        v
AttendanceGenerator::generateAll(unitId?, periodId?, userId?)
        |
        +--> cek pengaturan global
        |
        +--> ambil periode yang memiliki jadwal (dalam scope)
                 |
                 v
        generatePeriod() per periode
                 |
                 +--> transaksi
                 +--> lockForUpdate periode
                 +--> cek kesiapan ulang (semua jadwal)
                 |        gagal -> rollback, tanpa record parsial
                 +--> per jadwal:
                 |        upsertRecord()
                 |          - belum ada -> create
                 |          - aman disinkronkan -> update snapshot
                 |          - terkunci -> lewati
                 +--> syncAnomalies()
                 +--> commit
        |
        v
Ringkasan per periode { created, synced, unchanged, locked, anomalies, errors }
```

### Keputusan `upsertRecord`

| Kondisi record | Aksi | Hitungan |
|---|---|---|
| Belum ada | Buat record (Libur langsung terkunci) | `created` |
| Ada, `canSyncSnapshot()` benar, snapshot berubah | Perbarui snapshot + `last_synced_at` | `synced` |
| Ada, `canSyncSnapshot()` benar, snapshot sama | Tidak menulis | `unchanged` |
| Ada, terkunci | Lewati | `locked` |

`canSyncSnapshot()` bernilai benar hanya bila:

- `snapshot_locked_at` null,
- belum ada `check_in_at` maupun `check_out_at`,
- belum ada `corrected_*`,
- tidak ada koreksi `pending`.

## 4. Alur Check-in

```text
Satpam membuka /app/attendance
        |
        v
Pilih record efektif hari ini -> ambil koordinat perangkat
        |
        v
POST /app/attendance/{id}/check-in
        |
        v
ownRecord() -> assertWorking() -> validasi zona waktu check-in
        |
        v
validateGps() [wajib, Haversine, radius + akurasi snapshot]
        |
        v
simpan check_in_* + status in_progress + snapshot_locked_at
```

Validasi check-in:

- Shift harus `working`.
- Record belum memiliki check-in.
- Waktu server berada di
  `[scheduled_start - check_in_open, scheduled_end]` (inklusif).
- Koordinat dan akurasi wajib.
- Jarak <= `setting_gps_radius_meters`.
- Akurasi <= `setting_max_gps_accuracy_meters`.

Keterlambatan dihitung dari `check_in_at > scheduled_start + late_tolerance`,
bukan ditolak.

## 5. Alur Check-out

- Shift harus `working`, sudah check-in, belum check-out.
- Waktu server berada di
  `[scheduled_end - early_checkout_tolerance, scheduled_end + checkout_close]`.
- Validasi GPS sama seperti check-in.
- Status menjadi `completed`, snapshot tetap terkunci.

## 6. Penetapan Tidak Hadir

```text
Scheduler / manual: attendance:evaluate-absent
        |
        v
AttendanceStatusEvaluator::markAbsent()
        |
        v
record status = scheduled
  AND tidak ada check_in_at / corrected_check_in_at
  AND now > scheduled_end_at + checkout_close_minutes
        |
        v
status = absent, absent_marked_at = now, snapshot_locked_at = now
```

Record yang sudah check-in tetapi belum check-out **tidak** ditandai Tidak
Hadir; record tetap `in_progress`.

## 7. Alur Koreksi

```text
Satpam: POST /app/attendance/{id}/correction
        |
        v
AttendanceCorrectionService::request()
  - record milik Satpam
  - record correctable atau status efektif scheduled/pending
  - tidak ada koreksi pending lain (lock)
  - status tujuan valid
        |
        v
attendance_corrections.status = pending
        |
        +--> Admin Unit/Superadmin membuka /admin/attendance-correction
                 |
                 +--> approve:
                 |      - hitung status efektif + nilai efektif
                 |      - isi before_* dan applied_*
                 |      - tulis corrected_* pada record
                 |      - snapshot_locked_at
                 |      - koreksi approved + reviewed_*
                 |
                 +--> reject:
                        - koreksi rejected + reviewed_*
                        - record tidak berubah
```

Status efektif record dihitung dari koreksi `approved` terakhir, lalu jatuh ke
status tersimpan. Nilai efektif check-in/check-out memakai `corrected_*` bila
ada, lalu nilai asli.

## 8. Scheduler

`routes/console.php`:

```php
Schedule::command('attendance:generate')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('attendance:evaluate-absent')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
```

Keduanya memakai service yang sama dengan aksi manual sehingga perilaku
konsisten. `withoutOverlapping()` mencegah eksekusi ganda saat proses sebelumnya
belum selesai.

## 9. Batas Transaksi

| Operasi | Transaksi | Lock |
|---|---|---|
| `generatePeriod` | Ya, per periode | `schedule_periods` |
| `upsertRecord` | Bagian dari transaksi periode | `attendance_records` per `schedule_id` |
| `syncAnomalies` | Bagian dari transaksi periode | `attendance_generation_anomalies` |
| `request` koreksi | Ya | `attendance_records` |
| `approve`/`reject` | Ya | `attendance_records` + `attendance_corrections` |
| `checkIn`/`checkOut` | Ya | `attendance_records` |
| `markAbsent` | Ya | Query update per record |

## 10. Diagram Status

```text
scheduled --check-in--> in_progress --check-out--> completed
    |
    +--lewat batas--> absent

day_off (langsung saat record Libur dibuat)

Koreksi disetujui dapat menghasilkan status efektif:
in_progress / completed / absent / day_off
tanpa menghapus bukti asli.
```

`pending` bukan status tersimpan; ia muncul sebagai status efektif ketika
terdapat koreksi `pending`.

## 11. Peta Halaman

| Halaman | Route | Peran |
|---|---|---|
| Kesiapan Absensi | `/admin/attendance/readiness` | Admin, Superadmin |
| Data Absensi | `/admin/attendance-record` | Admin, Superadmin |
| Detail Absensi | `/admin/attendance-record/{id}` | Admin, Superadmin |
| Pengajuan Koreksi | `/admin/attendance-correction` | Admin, Superadmin |
| Pengaturan Absensi | `/admin/attendance-setting` | Superadmin |
| Aplikasi Absensi | `/app/attendance` | Satpam |

Halaman admin memakai `AdminLayout` dan sidebar **Absensi**; halaman Satpam
memakai `AppLayout` dengan tab Absensi.
