# Production Runbook — DSMS

Runbook ini berisi command operasional yang umum untuk DSMS. Gunakan path, user, service name, dan domain sesuai server aktual. Command bertanda **RISIKO** dapat memengaruhi data, ketersediaan, atau keamanan.

## Konvensi Placeholder

- `/path/to/dsms` — path root aplikasi di server.
- `php` — binary PHP yang benar (mis. `php8.3`).
- `www-data` / `deploy` — user sesuai kebijakan server.
- `nginx|apache2` dan `php-fpm` — nama service sesuai OS/distro.

## Cek Status Aplikasi

```bash
cd /path/to/dsms
php artisan about
php artisan --version
php artisan migrate:status
php artisan schedule:list
curl -I https://domain-resmi.example/up
```

Verifikasi:
- `/up` sehat.
- Tidak ada migration pending tak terduga.
- 3 scheduled task DSMS muncul.

## Cek Log

```bash
tail -n 200 storage/logs/laravel.log
tail -f storage/logs/laravel.log
```

Cek juga log web server/PHP-FPM sesuai OS, misalnya:

```bash
# contoh, sesuaikan path/service
journalctl -u php*-fpm -n 200 --no-pager
journalctl -u nginx -n 200 --no-pager
```

## Cek Scheduler

```bash
cd /path/to/dsms
php artisan schedule:list
php artisan schedule:run -v
```

Manual run task (jalankan pada environment yang benar):

```bash
php artisan attendance:generate
php artisan attendance:evaluate-absent
php artisan patrol:generate-sessions
```

Cek cron:

```bash
crontab -l
# atau cek /etc/cron.d, /var/spool/cron sesuai kebijakan server
```

**RISIKO:** Command manual dapat membuat/mengubah data operasional (absensi/sesi patroli). Jalankan saat window aman dan pantau log.

## Cek Queue

Audit saat ini: **tidak ada queued job aplikasi**; DB queue infra ada. Jika queue worker tidak diaktifkan, cukup pastikan tidak ada backlog tak terduga.

```bash
cd /path/to/dsms
php artisan queue:failed
```

Jika worker diaktifkan:

```bash
php artisan queue:restart
php artisan queue:retry all   # RISIKO: dapat menjalankan ulang job gagal
```

## Clear / Rebuild Cache

Aman untuk troubleshooting umum:

```bash
cd /path/to/dsms
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**RISIKO sedang:** `php artisan cache:clear` dapat menghapus cache aplikasi. Pahami dampaknya pada session/cache store.

## Restart Service

Sesuaikan nama service:

```bash
# contoh Ubuntu/systemd
sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
sudo systemctl status php8.3-fpm --no-pager
sudo systemctl status nginx --no-pager
```

**RISIKO:** Restart/reload service dapat memengaruhi user aktif. Lakukan saat window sesuai.

## Backup Database

**RISIKO:** Backup berisi data sensitif. Simpan di lokasi aman, terenkripsi, bukan web root.

MySQL/MariaDB contoh:

```bash
mysqldump --single-transaction --quick --lock-tables=false -h DB_HOST -u DB_USER -p DB_NAME > /secure-backup/dsms-db-YYYYMMDD-HHMM.sql
```

Verifikasi file backup terbentuk dan uji restore secara berkala.

## Backup File

```bash
tar -czf /secure-backup/dsms-storage-public-YYYYMMDD-HHMM.tar.gz -C /path/to/dsms storage/app/public
tar -czf /secure-backup/dsms-storage-private-YYYYMMDD-HHMM.tar.gz -C /path/to/dsms storage/app/private
```

Backup `.env` dan config server secara aman/terenkripsi.

## Restore Backup

**RISIKO TINGGI:** Restore dapat menimpa data production. Butuh persetujuan, window maintenance, dan backup kondisi saat ini.

Langkah umum:
1. Aktifkan maintenance mode.
2. Backup kondisi saat ini.
3. Restore DB ke database target yang benar.
4. Restore file storage bila diperlukan.
5. Jalankan `php artisan migrate:status`.
6. Clear/cache ulang dan restart service.
7. Verifikasi `/up`, login, modul kritikal.
8. Matikan maintenance mode.

Jangan restore ke DB yang belum dipastikan benar.

## Maintenance Mode

```bash
cd /path/to/dsms
php artisan down --retry=60
php artisan up
```

Gunakan saat deploy berisiko atau recovery. Pastikan halaman maintenance tidak membocorkan debug info.

## Storage Link

```bash
cd /path/to/dsms
php artisan storage:link
```

Verifikasi `public/storage` menunjuk ke `storage/app/public`. Periksa juga bahwa `/storage/*` tetap melalui kontrol akses aplikasi.

## Health Check Pasca Deploy

```bash
cd /path/to/dsms
php artisan about
php artisan migrate:status
php artisan schedule:list
curl -I https://domain-resmi.example/up
tail -n 100 storage/logs/laravel.log
```

Smoke test manual:
- Login Admin.
- Login Security/Satpam.
- Monitoring patroli, absensi, APAR/Hydrant.
- Guest Book.
- Upload/gambar/dokumen.

## Command Berbahaya (JANGAN di Production)

- `php artisan migrate:fresh`
- `php artisan migrate:refresh`
- `php artisan migrate:reset`
- `php artisan db:wipe`
- `php artisan key:generate` pada update biasa
- Restore DB tanpa backup dan persetujuan
