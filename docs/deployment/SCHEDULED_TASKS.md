# Scheduled Tasks Inventory — DSMS

Inventaris ini dibuat dari audit kode saat ini. Sumber utama: `routes/console.php`, `app/Console/Commands/`, pencarian `app/Jobs`/`ShouldQueue`/dispatch queue.

## Scheduled Tasks (Laravel Scheduler)

| Scheduled Task | Lokasi Definisi | Command/Job | Frekuensi | Tujuan | Wajib Production | Dampak Jika Tidak Berjalan | Cara Verifikasi | Butuh Cron | Butuh Queue Worker |
|---|---|---|---:|---|---:|---|---|---:|---:|
| Generate attendance | `routes/console.php` | `php artisan attendance:generate` | Tiap 15 menit, `withoutOverlapping()` | Membuat/menyinkronkan data absensi dari jadwal yang siap diproses | Ya | Data absensi tidak terbentuk/sinkron; monitoring absensi/readiness bisa tertinggal | `php artisan schedule:list`; jalankan manual `php artisan attendance:generate`; cek output/log dan data absensi | Ya (`schedule:run` tiap menit) | Tidak |
| Evaluate absent | `routes/console.php` | `php artisan attendance:evaluate-absent` | Tiap 15 menit, `withoutOverlapping()` | Menetapkan status Tidak Hadir untuk absensi yang melewati batas check-in | Ya | Status tidak hadir terlambat/tidak diperbarui otomatis | `php artisan schedule:list`; jalankan manual `php artisan attendance:evaluate-absent`; cek status absensi | Ya | Tidak |
| Generate patrol sessions | `routes/console.php` | `php artisan patrol:generate-sessions` | Tiap 15 menit, `withoutOverlapping()` | Membentuk sesi patroli dari jadwal periode aktif | Ya | Sesi patroli tidak terbentuk; patroli/monitoring terganggu | `php artisan schedule:list`; jalankan manual `php artisan patrol:generate-sessions`; cek sesi patroli | Ya | Tidak |

## Manual / Operational Commands (Not Scheduled)

| Command | Lokasi | Tujuan | Kapan Dipakai | Catatan |
|---|---|---|---|---|
| `attendance:readiness {--period=} {--unit=}` | `app/Console/Commands/AttendanceReadinessCommand.php` | Cek kesiapan data absensi | Troubleshooting/readiness check | Tidak terjadwal; read/diagnostic oriented. |
| `dsms:migrate-photos-to-public {--dry-run}` | `app/Console/Commands/MigrateDsmsPhotosToPublic.php` | Audit/copy foto operasional DSMS dari private ke public disk | Migrasi/operasional khusus | Gunakan `--dry-run` dulu; verifikasi hasil. |
| `inspire` | `routes/console.php` closure | Command bawaan contoh | Tidak operasional | Tidak wajib production. |

## Queue / Job Audit

| Item | Hasil Audit |
|---|---|
| `app/Jobs` | Tidak ada file job. |
| `ShouldQueue` | Tidak ditemukan. |
| Dispatch queue / Bus / Queue push | Tidak ditemukan di kode aplikasi saat audit. |
| Mail/Notification queued | Tidak ditemukan; `User` memakai `Notifiable` trait saja. |
| Queue tables | Ada migration `jobs`, `job_batches`, `failed_jobs`. |
| Queue connection default | `database` (`config/queue.php`, `.env.example`). |

**Kesimpulan:** Queue worker **belum diperlukan** berdasarkan audit saat ini. Jika fitur queued job ditambahkan, update dokumen ini dan siapkan worker/restart policy.

## Cron Requirement

Jika menggunakan Linux cron, scheduler Laravel harus dijalankan tiap menit:

```cron
* * * * * cd /path/to/dsms && php artisan schedule:run >> /dev/null 2>&1
```

Laravel menentukan task mana yang due (3 task DSMS tiap 15 menit). Tanpa cron/schedule runner, task bisnis tidak berjalan otomatis.
