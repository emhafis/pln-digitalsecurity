# Deployment & Production Runbook — DSMS

Dokumentasi ini disusun berdasarkan **audit kode dan konfigurasi project DSMS saat ini** (Laravel 13 + Inertia + Vue 3). Ini bukan panduan Laravel generik. Jika suatu fitur tidak ditemukan saat audit, dokumen ini menyatakannya secara eksplisit.

> **Batasan penting:** Dokumentasi ini tidak mengklaim aplikasi sudah siap production. Deployment production tetap memerlukan verifikasi environment server, backup, keamanan, dan smoke test oleh operator.

## Audit Ringkas

| Area | Temuan | Evidence |
|---|---|---|
| Runtime | PHP `^8.3`, Laravel `^13.17`, Inertia Laravel `^3.7` | `composer.json` |
| Frontend build | Vite 8, Vue 3, Tailwind 4; scripts `npm run build`, `npm run dev` | `package.json`, `vite.config.js` |
| DB default (.env.example) | `DB_CONNECTION=sqlite`; MySQL vars tersedia tapi dikomentari; config mendukung sqlite/mysql/mariadb/pgsql/sqlsrv | `.env.example`, `config/database.php` |
| Cache/session/queue default | `database` | `.env.example`, `config/cache.php`, `config/session.php`, `config/queue.php` |
| Filesystem default | `FILESYSTEM_DISK=local`; disk `public` = `storage/app/public`, URL `/storage` | `.env.example`, `config/filesystems.php` |
| Storage access | `GET /storage/{path}` route dengan middleware `web,auth`; controller serve dari disk `public`; `public/.htaccess` memaksa `/storage` melalui Laravel dan memblokir akses langsung | `routes/web.php`, `app/Http/Controllers/StorageController.php`, `public/.htaccess` |
| Health check | `GET /up` terdaftar | `bootstrap/app.php` |
| Scheduler | 3 task wajib: `attendance:generate`, `attendance:evaluate-absent`, `patrol:generate-sessions`, tiap 15 menit, `withoutOverlapping()` | `routes/console.php` |
| Console commands manual | `attendance:readiness`, `dsms:migrate-photos-to-public {--dry-run}` | `app/Console/Commands/*` |
| Queue jobs | Tidak ada `app/Jobs`, tidak ditemukan `ShouldQueue`/dispatch queue/mail/notification queue saat audit; tabel `jobs/job_batches/failed_jobs` ada | audit repo, `database/migrations/0001_01_01_000002_create_jobs_table.php` |
| Docker/CI | Tidak ditemukan `Dockerfile`, compose, nginx config, supervisor, GitHub Actions workflow | audit repo |
| Docs existing | `docs/RUNNING.md`, `docs/guest-book/OPERATIONS.md` | `docs/` |

## Isi Dokumentasi

- [README.md](./README.md) — panduan deployment utama (file ini).
- [DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md) — checklist first deployment, routine update, verifikasi, rollback.
- [PRODUCTION_RUNBOOK.md](./PRODUCTION_RUNBOOK.md) — command operasional harian/troubleshooting.
- [SCHEDULED_TASKS.md](./SCHEDULED_TASKS.md) — inventaris task otomatis dan command manual.

## A. Persiapan Server

### Wajib (berdasarkan audit)

| Kebutuhan | Status | Catatan |
|---|---|---|
| OS Linux server | Wajib | Ubuntu LTS umum dipakai; versi final dikonfirmasi operator. |
| PHP >= 8.3 | Wajib | `composer.json: php ^8.3`. |
| PHP extensions | Wajib, verifikasi di server | Minimal umum Laravel: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer`, `xml`. Untuk MySQL/MariaDB: `pdo_mysql`/`mysqlnd`. Untuk SQLite dev/test: `pdo_sqlite`. Karena project memakai `dompdf`, `endroid/qr-code`, `maatwebsite/excel`, dan upload gambar, verifikasi juga `gd` atau `imagick`, `zip`, `intl` jika dibutuhkan environment; pastikan `php -m` di server mencakup kebutuhan aktual. |
| Composer 2 | Wajib | Install dependency PHP. |
| Node.js + npm | Wajib untuk build frontend | `package.json` memakai Vite 8; gunakan Node LTS modern (Node 20+ direkomendasikan) dan npm yang kompatibel. |
| Database | Wajib | SQLite hanya cocok dev/test sederhana; production umumnya MySQL/MariaDB/PostgreSQL sesuai `config/database.php`. Pilih satu dan verifikasi. |
| Web server + PHP-FPM | Wajib | Nginx/Apache tidak disertakan di repo; operator menyiapkan config. Document root harus `public/`. |
| Akses tulis `storage/` dan `bootstrap/cache/` | Wajib | Untuk logs, cache, session/file, upload, compiled views. |
| Cron/scheduler runner | Wajib | 3 scheduled task bisnis aktif setiap 15 menit. |
| Domain, DNS, SSL/TLS | Wajib production | `APP_URL` harus HTTPS di production. |

### Opsional / Tidak Ditemukan

| Kebutuhan | Status | Catatan |
|---|---|---|
| Docker / Docker Compose | Tidak ditemukan | Tidak ada `Dockerfile`, compose, `.dockerignore`, config docker/nginx di repo. Jika operator memakai Docker, config harus dibuat/audit terpisah. |
| Queue worker daemon | Saat ini tidak wajib berdasarkan audit | Tidak ada queued job/ShouldQueue. DB queue infra ada; siapkan worker hanya jika fitur queue ditambahkan. |
| Redis | Opsional | Config ada, bukan default. Dapat dipakai untuk cache/session/queue jika diinginkan. |
| Mail SMTP/provider | Opsional saat ini | Default `log`. Aktifkan jika fitur email/notifikasi dipakai. |
| Supervisor/systemd | Opsional, direkomendasikan jika pakai worker/schedule:work | Tidak ada config di repo. |
| Backup agent/object storage | Opsional tapi sangat direkomendasikan | Tidak ada config backup di repo. |

## B. Persiapan Environment

1. Salin `.env.example` menjadi `.env` di server deployment **pertama kali**.
2. Isi nilai production. Jangan menuliskan secret/password asli ke dokumentasi/repo.

### Variabel penting

| Variabel | Production guidance | Catatan |
|---|---|---|
| `APP_NAME` | `"Digital Security"` atau nama operasional | Dipakai UI/title. |
| `APP_ENV` | `production` | Wajib. |
| `APP_KEY` | Generate sekali saat first deploy; **jaga tetap sama** saat update | Jangan regenerate pada update biasa; dapat merusak data terenkripsi/session/cookie. |
| `APP_DEBUG` | `false` | Wajib. |
| `APP_URL` | `https://domain-resmi.example` | Dipakai URL generation, storage public URL. |
| `APP_LOCALE`/timezone | Verifikasi kebutuhan bisnis | `config/app.php` timezone default `UTC`; DSMS operasional Indonesia sering butuh `Asia/Jakarta` — **konfirmasi sebelum ubah**, karena memengaruhi waktu, jadwal, absensi, patroli. |
| `LOG_CHANNEL`/`LOG_LEVEL` | `stack`/`daily` atau sesuai kebijakan; level `info`/`warning` umum | Default example `debug`. |
| `DB_*` | Sesuai DB production | Jangan pakai sqlite production tanpa alasan kuat. |
| `SESSION_DRIVER` | `database` default | Butuh tabel sessions (migration default Laravel ada). |
| `SESSION_SECURE_COOKIE` | `true` di HTTPS | Pertimbangkan tambah di `.env` production. |
| `CACHE_STORE` | `database` default; Redis/file sesuai infra | Pastikan store yang dipilih tersedia. |
| `QUEUE_CONNECTION` | `database` default | Aman; worker tidak wajib saat ini. |
| `FILESYSTEM_DISK` | `local` default | Disk `public` tetap dikonfigurasi untuk file publik aplikasi. |
| `MAIL_*` | Sesuai provider jika digunakan | Default `log`; jangan kirim email nyata tanpa konfigurasi. |

### Perintah APP_KEY

First deployment (setelah `.env` dibuat):

```bash
php artisan key:generate
```

Routine update: **jangan jalankan** `key:generate` kecuali ada prosedur rotasi kunci yang direncanakan dan dampaknya dipahami.

## C. Instalasi Dependency dan Build

### Build/deploy machine (CI atau server)

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Catatan:
- `npm ci` membutuhkan `package-lock.json` (ada di repo).
- `npm run build` menghasilkan asset Vite di `public/build` (gitignored). Pastikan hasil build ikut ter-deploy atau dibangun di server target.
- `composer dev` ada di `composer.json`, tetapi isinya `php artisan dev`; `docs/RUNNING.md` mendeskripsikan perilaku berbeda. **Jangan gunakan `composer dev` untuk production.** Verifikasi perilaku sebelum pakai di lokal.

## D. Database Migration

### Aturan aman

- Backup database sebelum migration production, terutama migration besar/destruktif.
- Jalankan hanya pada database yang sudah dipastikan benar.
- Gunakan:

```bash
php artisan migrate --status
php artisan migrate --force
```

- `--force` diperlukan di `APP_ENV=production`.
- **Jangan jalankan** `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` di production.

### Saat gagal

1. Jangan ulangi command destruktif.
2. Amankan log error dan output migration.
3. Cek `migrate:status` dan skema DB.
4. Restore backup jika diperlukan dan sesuai prosedur.
5. Perbaiki migration/kode di lingkungan non-production, lalu rilis ulang.

## E. Laravel Optimization

Setelah dependency/config siap:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Opsional setelah verifikasi: `php artisan optimize`.

### Catatan

- Jalankan ulang `config:cache` setelah perubahan `.env` atau config.
- Jika ada perilaku aneh pasca deploy, bersihkan cache:

```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

- `cache:clear` membersihkan cache aplikasi; pahami dampaknya pada session/cache store yang dipakai.
- Jangan menjalankan optimization sebelum `.env` production benar.

## F. Storage dan Permission

### Lokasi

| Item | Lokasi | Catatan |
|---|---|---|
| App storage | `storage/` | logs, framework cache/sessions/views, uploads. |
| Public disk root | `storage/app/public` | `config/filesystems.php`. |
| Private disk root | `storage/app/private` | disk `local`, default. |
| Public symlink | `public/storage` -> `storage/app/public` | `php artisan storage:link`. |
| Auth-gated storage route | `/storage/{path}` | `routes/web.php`, `StorageController`, middleware `web,auth`. |

### Perintah

```bash
php artisan storage:link
```

### Permission

- Pastikan user PHP-FPM/web server dapat membaca kode dan menulis `storage/` dan `bootstrap/cache/`.
- Gunakan ownership/permission minimal yang aman (mis. `www-data` atau user deploy sesuai server). Jangan `chmod 777`.
- Pastikan disk tidak penuh.

### Kebijakan akses gambar/file DSMS

Audit menemukan:
- Public disk digunakan; URL `/storage/...`.
- Route `storage.show` memakai middleware `web,auth`.
- `public/.htaccess` memiliki rule: rewrite `/storage/` ke Laravel dan juga rule blokir akses langsung `/storage/` (`[F,L]`).

Implikasi deployment:
- Jangan mengekspos `storage/app/public` sebagai static path yang bypass Laravel, kecuali Anda sudah meninjau ulang kebijakan akses.
- Untuk Nginx, pastikan request `/storage/*` diproses sesuai kebijakan keamanan aplikasi (umumnya diteruskan ke Laravel, bukan langsung alias publik tanpa auth), lalu verifikasi file private/tidak publik tidak bocor.
- Uji dengan akun unauthorized/unauthenticated dan authorized.

## G. Laravel Scheduler

Scheduler **wajib** karena ada task bisnis aktif.

| Scheduled Task | Command/Job | Frekuensi | Tujuan | Wajib Production |
|---|---|---|---:|---:|
| Generate attendance | `attendance:generate` | Tiap 15 menit, `withoutOverlapping` | Membuat/menyinkronkan data absensi dari jadwal | Ya |
| Evaluate absent | `attendance:evaluate-absent` | Tiap 15 menit, `withoutOverlapping` | Menetapkan status Tidak Hadir untuk absensi lewat batas | Ya |
| Generate patrol sessions | `patrol:generate-sessions` | Tiap 15 menit, `withoutOverlapping` | Membentuk sesi patroli dari periode jadwal aktif | Ya |

Sumber: `routes/console.php`.

### Cron Linux

Tambahkan cron untuk user yang tepat (biasanya user deploy/www-data sesuai kebijakan server):

```cron
* * * * * cd /path/to/dsms && php artisan schedule:run >> /dev/null 2>&1
```

Ganti `/path/to/dsms` dengan path deployment aktual. Jika perlu log, arahkan ke file log terkontrol:

```cron
* * * * * cd /path/to/dsms && php artisan schedule:run >> /var/log/dsms-scheduler.log 2>&1
```

### Verifikasi

```bash
php artisan schedule:list
php artisan schedule:run
php artisan attendance:generate
php artisan attendance:evaluate-absent
php artisan patrol:generate-sessions
```

Catatan: `schedule:run` menjalankan task yang due. Untuk debugging, jalankan command manual di environment yang benar.

## H. Queue Worker

### Hasil audit

- `QUEUE_CONNECTION=database` default.
- Migration queue ada: `jobs`, `job_batches`, `failed_jobs`.
- **Tidak ditemukan** class `app/Jobs`, `ShouldQueue`, dispatch queue, mail/notification queue di kode aplikasi saat audit.

### Kesimpulan

Queue worker **tidak wajib** untuk fitur yang terdeteksi saat ini. Namun infrastruktur queue DB sudah ada.

Jika di masa depan ada queued job:

```bash
php artisan queue:work --tries=3
php artisan queue:restart
php artisan queue:failed
php artisan queue:retry all
```

Jalankan worker via `systemd`/Supervisor dan restart setelah deployment. Jangan expose retry/destructive queue commands tanpa memahami dampak.

## I. Docker dan Service Production

**Tidak ditemukan** di audit: `Dockerfile`, `docker-compose.yml`, `compose.yaml`, `.dockerignore`, folder `docker/`, `nginx/`, workflow CI deployment.

Oleh karena itu:
- Jangan mengasumsikan deployment Docker tersedia.
- Jika ingin Docker, buat dan audit config terpisah (web, php-fpm/app, db, scheduler, optional queue, volumes, secrets, TLS, permissions).
- Checklist Docker di dokumen ini tidak dibuat karena tidak ada evidence di repo.

## J. Deployment Pertama Kali

1. **Siapkan server**: PHP 8.3+, extensions, web server, DB, Node/npm, cron, SSL.
2. **Ambil source code**: clone/upload ke `/path/to/dsms`; pastikan `public/` sebagai document root.
3. **Siapkan `.env`**: salin dari `.env.example`, set nilai production, `APP_DEBUG=false`, `APP_URL` HTTPS.
4. **Install dependency**:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```
5. **Generate key** (sekali saja):
   ```bash
   php artisan key:generate
   ```
6. **Siapkan DB**: buat database/user dengan privilege minimal.
7. **Migration**:
   ```bash
   php artisan migrate --status
   php artisan migrate --force
   ```
8. **Storage link**:
   ```bash
   php artisan storage:link
   ```
9. **Permission**: `storage/` dan `bootstrap/cache/` writable oleh PHP user.
10. **Cache/optimize**:
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```
11. **Konfigurasi web server/PHP-FPM**: arahkan ke `public/`; reload service.
12. **Scheduler cron**: pasang cron `schedule:run` tiap menit.
13. **Queue worker**: lewati jika tidak digunakan; siapkan jika nanti ada job.
14. **SSL/domain**: aktifkan HTTPS, redirect HTTP->HTTPS.
15. **Verifikasi**: `/up`, login admin/satpam, modul utama, upload/gambar, scheduler, log.

## K. Deployment Update

1. **Backup** sebelum update besar/risky migration.
2. **Maintenance mode** (opsional, sesuai downtime policy):
   ```bash
   php artisan down --retry=60
   ```
3. **Update code**: pull/tag release sesuai proses tim.
4. **Dependency/build**:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```
5. **Migration** jika ada:
   ```bash
   php artisan migrate --status
   php artisan migrate --force
   ```
6. **Clear/rebuild cache**:
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
7. **Restart services**: PHP-FPM/web server sesuai OS; restart queue worker jika ada (`queue:restart`).
8. **Verifikasi**: `/up`, login, modul kritikal, log error, scheduler.
9. **Keluar maintenance mode**:
   ```bash
   php artisan up
   ```

Jangan regenerate `APP_KEY`. Jangan jalankan command destructive DB.

## L. Backup dan Recovery

### Wajib dipertimbangkan

| Item | Prioritas | Catatan |
|---|---|---|
| Database | Tinggi | Backup sebelum migration/deploy besar dan berkala. |
| `storage/app/public` | Tinggi | Berisi file upload/dokumen publik aplikasi. |
| `storage/app/private` | Sedang/tinggi | Jika ada file private; audit menyebut disk local/private ada. |
| `.env` dan config server | Tinggi | Simpan aman, terenkripsi, bukan di web root. |

### Praktik

- Simpan backup di lokasi aman di luar document root/public.
- Tetapkan retensi dan uji restore berkala.
- Verifikasi backup sebelum migration production.
- Restore database/file harus diuji di staging/restore drill, bukan asumsi.

## M. Monitoring dan Troubleshooting

Checklist minimum:
- `GET /up` mengembalikan sehat.
- `storage/logs/laravel.log` tidak berisi error baru kritikal.
- Web server/PHP-FPM log bersih.
- DB connection OK.
- Disk usage aman, permission `storage`/`bootstrap/cache` benar.
- Cron scheduler berjalan; `schedule:list` menampilkan 3 task.
- SSL valid.
- Login Admin dan Security/Satpam.
- Monitoring patroli, absensi, inspeksi APAR/Hydrant, Guest Book.
- Gambar/file upload dapat diakses oleh user berwenang dan tidak bocor ke unauthenticated jika kebijakan mengharuskan.

Command diagnostik:

```bash
php artisan about
php artisan --version
php artisan migrate:status
php artisan schedule:list
php artisan route:list
php artisan config:show app
tail -n 200 storage/logs/laravel.log
```

## Hal yang Perlu Dikonfirmasi Operator

- OS dan web server final (Nginx/Apache, PHP-FPM/systemd).
- Versi PHP extensions terpasang di server.
- Database production (MySQL/MariaDB/PostgreSQL) dan parameter tuning/backup.
- Path deployment, user cron, user PHP-FPM.
- Timezone bisnis (`UTC` default config vs `Asia/Jakarta` operasional).
- Kebijakan backup, retensi, lokasi, dan restore test.
- Apakah perlu queue worker di masa depan.
- Apakah email/notifikasi akan diaktifkan.
- Konfigurasi Nginx/Apache untuk `/storage/*` agar tidak bypass auth Laravel.
