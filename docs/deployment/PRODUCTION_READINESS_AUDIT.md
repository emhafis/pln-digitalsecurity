# Production Readiness Audit — DSMS

**Tanggal audit:** 2026-11-21  
**Mode:** Read-only audit (tanpa perubahan kode/konfigurasi/database/deployment).  
**Project:** Digital Security Management System (DSMS)  
**Auditor:** opencode (automated code/config audit)

> **Catatan penting:** Audit ini berbasis inspeksi kode, konfigurasi, dokumen, dan pemeriksaan lokal yang aman. Item yang membutuhkan akses server production, credential, data production, atau tindakan berisiko ditandai **Belum Diverifikasi**.

## 1. Ringkasan Eksekutif

DSMS memiliki fondasi aplikasi yang cukup jelas (Laravel 13, PHP 8.3, Inertia/Vue 3, scheduler bisnis terdefinisi, health endpoint `/up`, build frontend berhasil). Namun, audit menemukan **blocker deployment** terkait konfigurasi production yang belum terverifikasi/siap, ketidakadaan infrastruktur Docker yang disebutkan dalam scope audit, dan risiko kritis autentikasi akses `/storage/*` pada web server production non-Apache atau konfigurasi server yang tidak meneruskan request ke Laravel.

Status tidak dapat ditetapkan READY karena beberapa kontrol kritis (DB production, backup/restore, proteksi storage di server production, SSL/domain, secret/env production) belum dapat diverifikasi dari repo lokal.

## 2. Status Kesiapan Deployment

**Keputusan:** **NOT READY**  `n`n**Addendum 2026-11-21:** Remediasi parsial dilakukan (template `.env.example`, dukungan `APP_TIMEZONE`, template Docker/Nginx, test storage). Lihat `docs/deployment/REMEDIATION_BLOCKERS.md`. Blocker belum dapat ditutup karena belum ada verifikasi staging/production, test SQLite driver tidak tersedia, dan backup/restore belum dibuktikan.

**Alasan singkat:**
- Blocker pada kesiapan environment production dan pembuktian proteksi storage/gambar di production.
- Infrastruktur Docker yang disebut dalam informasi project tidak ditemukan di repo.
- Timezone operasional DSMS default config `UTC`, sedangkan requirement audit menyebut WIB/Asia/Jakarta; perlu verifikasi/keputusan eksplisit karena berdampak ke absensi/patroli/jadwal.

## 3. Versi Stack Terverifikasi

| Komponen | Nilai Terverifikasi | Sumber |
|---|---:|---|
| PHP | 8.3.37 (local runtime) | `php artisan about --json` |
| Laravel Framework | 13.43.0 | `php artisan about --json`, `composer.lock`/composer |
| Composer | 2.9.2 | `php artisan about --json` |
| Node.js | v22.12.0 (local) | `node -v` |
| npm | 11.8.0 (local) | `npm -v` |
| Vite | ^8.0.0 | `package.json` |
| Vue | ^3.5.44 | `package.json` |
| Tailwind CSS | ^4.0.0 | `package.json` |
| Inertia Laravel | ^3.7 | `composer.json` |
| PHPUnit | ^12.5.12 | `composer.json` |
| App env (local) | local | `php artisan about --json` |
| Debug mode (local) | true | `php artisan about --json` |
| Timezone (config) | UTC | `config/app.php` |
| DB driver (local) | mysql | `php artisan about --json` |
| Cache/session/queue | database | `php artisan about --json` |
| Health endpoint | `/up` | `bootstrap/app.php` |
| Docker files | Tidak ditemukan | audit file repo |

## 4. Checklist Audit per Kategori

| Kategori | Status | Catatan |
|---|---|---|
| Konfigurasi production | **FAIL / Belum Diverifikasi** | `.env.example` dev-default; `.env` lokal bukan production; timezone UTC; Docker config tidak ada. |
| Security | **WARNING** | Auth/role middleware ada; route sensitif auth-guarded; `/storage` route auth-guarded; ada risiko proteksi storage tergantung web server. Devtools Inertia terdaftar saat local — pastikan non-production. |
| Public storage & foto | **FAIL / Belum Diverifikasi** | Proteksi `/storage/*` bergantung `.htaccess` Apache/Laravel route; tidak terbukti untuk Nginx/production. |
| Database & migration | **Belum Diverifikasi** | Migration ada; status migration production tidak bisa diverifikasi; backup production tidak dibuktikan. |
| Modul utama DSMS | **WARNING** | Audit statis menunjukkan modul ada; tidak ada end-to-end production verification. |
| Scheduler/cron/queue | **PASS (kode) / WARNING (ops)** | 3 scheduler task terdaftar; cron production belum diverifikasi; queue worker tidak wajib saat ini. |
| Docker/infrastruktur | **FAIL** | Dockerfile/compose tidak ditemukan padahal disebut requirement audit. |
| Backup/recovery | **FAIL** | Tidak ada prosedur backup/restore tervalidasi di repo; docs deployment ada tapi operasional backup belum dibuktikan. |
| Build/testing | **WARNING** | `npm run build` lulus; `composer validate` warning minor; Unit tests gagal karena env sqlite driver missing, bukan assertion bisnis. |
| Dokumentasi deployment | **PASS (ada) / WARNING (akurasi ops)** | `docs/deployment/*` ada; perlu validasi oleh operator karena infra production tidak ada di repo. |

## 5. Temuan Berdasarkan Severity

### BLOCKER

#### BLK-001 — Konfigurasi production belum terverifikasi dan `.env.example` dev-default
- **Severity:** BLOCKER
- **Modul:** Konfigurasi/Deployment
- **Lokasi:** `.env.example`, `config/app.php`, `config/session.php`, `.env` (local, tidak dibaca isinya secara sensitif)
- **Bukti:** `.env.example`: `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8000`, `DB_CONNECTION=sqlite`, `MAIL_MAILER=log`. `php artisan about --json` lokal: env local, debug true, timezone UTC. Tidak ada bukti `.env` production aman.
- **Dampak:** Risiko deploy dengan debug aktif, URL salah, DB/session/cache/mail tidak sesuai, atau kebocoran error detail.
- **Rekomendasi:** Siapkan `.env` production terpisah; `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS domain resmi, DB MySQL production, session secure cookie, logging/backup sesuai kebijakan. Verifikasi via checklist sebelum go-live.
- **Perubahan:** Konfigurasi/env server (tanpa hardcode secret ke repo).

#### BLK-002 — Proteksi autentikasi file `/storage/*` tidak terbukti untuk web server production
- **Severity:** BLOCKER
- **Modul:** Storage/File Security
- **Lokasi:** `routes/web.php:49-52`, `app/Http/Controllers/StorageController.php`, `public/.htaccess`, `config/filesystems.php`
- **Bukti:** Route `/storage/{path}` ada dengan middleware `web,auth` dan controller serve `Storage::disk('public')`. `public/.htaccess` menulis: force storage through Laravel dan block direct browser access `/storage/ - [F,L]`. Namun tidak ada config Nginx/Apache production di repo. Apache `.htaccess` tidak berlaku otomatis pada Nginx.
- **Dampak:** Jika web server production menyajikan `public/storage` atau `/storage/*` langsung, file foto/dokumen dapat diakses tanpa login, melanggar kebijakan “harus login untuk melihat gambar”.
- **Rekomendasi:** Untuk production, pastikan web server **tidak** bypass Laravel untuk `/storage/*`; tambahkan config Nginx/Apache eksplisit; uji dengan user logout, user login role berbeda, path traversal, dan direct URL. Pertimbangkan tes otomatis. Jangan anggap aman sebelum diverifikasi di staging/production-like.
- **Perubahan:** Konfigurasi web server; mungkin kode/test tambahan.

#### BLK-003 — Docker/Compose infrastructure yang disyaratkan audit tidak ditemukan
- **Severity:** BLOCKER
- **Modul:** Infrastruktur/Deployment
- **Lokasi:** root repo
- **Bukti:** Tidak ada `Dockerfile`, `docker-compose.yml`, `compose.yaml`, `.dockerignore`, `docker/`, `nginx/`. `Test-Path` semua false.
- **Dampak:** Tidak ada definisi deployment container yang dapat diaudit/di-run; risiko ketidaksesuaian environment, service scheduler/worker/permission/storage mount tidak terdokumentasi dalam infra-as-code.
- **Rekomendasi:** Jika production memang Docker, sediakan Dockerfile/compose/Nginx config, volume strategy, scheduler/worker service, healthchecks, non-root/permission, secrets management. Jika production bukan Docker, perbarui rencana deployment dan audit infra aktual.
- **Perubahan:** Konfigurasi/infrastruktur.

#### BLK-004 — Backup/restore production belum dibuktikan
- **Severity:** BLOCKER
- **Modul:** Backup/Recovery
- **Lokasi:** `docs/deployment/README.md`, `docs/deployment/PRODUCTION_RUNBOOK.md`; tidak ada script backup di repo
- **Bukti:** Dokumen backup ada, tapi tidak ada bukti backup production terjadwal, lokasi aman, enkripsi, retensi, atau uji restore. DB production tidak bisa diaudit dari lokal.
- **Dampak:** Risiko kehilangan data/perubahan tidak dapat dipulihkan saat gagal deploy/migrasi/insiden.
- **Rekomendasi:** Tetapkan backup DB + `storage/app/public` + `storage/app/private` + config/env aman; otomatisasi; uji restore; verifikasi sebelum migration production.
- **Perubahan:** Prosedur/infrastruktur operasional.

### HIGH

#### HIGH-001 — Timezone default `UTC` berpotensi konflik dengan kebutuhan operasional WIB
- **Severity:** HIGH
- **Modul:** Konfigurasi/Absensi/Patroli/Penjadwalan
- **Lokasi:** `config/app.php` (`timezone => 'UTC'`)
- **Bukti:** Config default UTC. Project DSMS menggunakan `AttendanceClock` dan banyak logic waktu operasional; audit sebelumnya/dokumen menyebut WIB. Beberapa command/file menggunakan `now()`/Carbon; perilaku harus konsisten.
- **Dampak:** Salah hitung waktu check-in/out, late, window patroli, jadwal, laporan, jika environment/timezone tidak selaras.
- **Rekomendasi:** Konfirmasi timezone bisnis resmi. Jika WIB, set `APP_TIMEZONE=Asia/Jakarta` dan audit semua penggunaan `now()/today()/Carbon::parse()` untuk konsistensi; tambahkan test timezone.
- **Perubahan:** Konfigurasi dan kemungkinan kode/test.

#### HIGH-002 — Session secure cookie tidak eksplisit di `.env.example`
- **Severity:** HIGH
- **Modul:** Security/Session
- **Lokasi:** `.env.example`, `config/session.php`
- **Bukti:** `SESSION_SECURE_COOKIE` tidak ada di `.env.example`; config memakai `env('SESSION_SECURE_COOKIE')` default null. `http_only=true`, `same_site=lax` sudah baik.
- **Dampak:** Di HTTPS production, cookie bisa tidak dipaksa secure jika env tidak diset; risiko session exposure pada misconfig.
- **Rekomendasi:** Set `SESSION_SECURE_COOKIE=true` di production HTTPS; dokumentasikan.
- **Perubahan:** Konfigurasi env.

#### HIGH-003 — Tidak ada pembatasan akses file `/storage/*` berbasis unit/pengunggah meski ada kontrol role/login
- **Severity:** HIGH (sesuai kebijakan yang diinginkan, ini **bukan blocker** jika login+role sudah cukup; tetap risiko informasi)
- **Modul:** Storage Authorization
- **Lokasi:** `app/Http/Controllers/StorageController.php`, `routes/web.php`
- **Bukti:** `StorageController@show` hanya cek path traversal dan `Storage::disk('public')->exists`; route dilindungi `web,auth` tapi tidak cek role/unit/ownership.
- **Dampak:** User login mana pun bisa mencoba mengakses file public-disk jika mengetahui path. Audit menyatakan kebijakan: “Tidak perlu pembatasan foto berdasarkan unit atau pengunggah”, tetapi tetap perlu memastikan tidak ada dokumen sensitif di public disk.
- **Rekomendasi:** Konfirmasi klasifikasi data: file public disk hanya untuk yang boleh dilihat semua user login. Pindahkan file sensitif ke private disk + controller authorization.
- **Perubahan:** Mungkin kode/policy/data classification.

#### HIGH-004 — Devtools Inertia terdaftar di route lokal
- **Severity:** HIGH
- **Modul:** Security/Observability
- **Lokasi:** route list: `/_inertia/devtools/entries*` dengan middleware `Authorize`
- **Bukti:** Route terdaftar saat `php artisan route:list` di env local.
- **Dampak:** Jika package devtools aktif di production karena misconfig env/dependency, dapat mengekspos info internal. Perlu verifikasi dependency/dev mode.
- **Rekomendasi:** Pastikan devtools tidak aktif di production; verifikasi `APP_ENV`, config package, dan composer dev deps tidak terinstall di production.
- **Perubahan:** Konfigurasi deploy.

### MEDIUM

#### MED-001 — `composer validate` warning: exact version `maatwebsite/excel 4.0`
- **Severity:** MEDIUM
- **Lokasi:** `composer.json`
- **Bukti:** `composer validate` warning.
- **Dampak:** Update patch/minor security fixes bisa terhambat.
- **Rekomendasi:** Evaluasi constraint semver yang aman (`^4.0`/versi yang diuji) dan regression test export.
- **Perubahan:** Kode/dependency.

#### MED-002 — Test suite Unit gagal karena driver SQLite tidak tersedia di environment audit
- **Severity:** MEDIUM
- **Lokasi:** `phpunit.xml`, environment lokal
- **Bukti:** `php artisan test --testsuite=Unit` => 14 tests, 5 passed, 9 errors, `could not find driver (sqlite :memory:)`. Kegagalan environment, bukan assertion.
- **Dampak:** Tidak dapat memverifikasi unit tests DB-dependent di environment ini.
- **Rekomendasi:** Jalankan di CI/server dengan `pdo_sqlite/sqlite3` atau gunakan DB test yang sesuai; pastikan pipeline production memiliki test gate.
- **Perubahan:** Environment CI.

#### MED-003 — Dokumen deployment ada tetapi perlu validasi operasional
- **Severity:** MEDIUM
- **Lokasi:** `docs/deployment/*`
- **Bukti:** File ada: README, checklist, runbook, scheduled tasks. Namun beberapa asumsi infra (tanpa Docker) dan proteksi storage perlu verifikasi operator.
- **Dampak:** Operator dapat salah asumsi saat deploy.
- **Rekomendasi:** Review oleh infra/ops; tambahkan config Nginx/Apache contoh untuk `/storage/*` auth pass-through.
- **Perubahan:** Dokumentasi/infra.

### LOW

#### LOW-001 — `public/.htaccess` memiliki rule storage duplikatif/berpotensi membingungkan
- **Severity:** LOW
- **Lokasi:** `public/.htaccess`
- **Bukti:** Ada `RewriteRule ^storage/ index.php [L]` lalu kemudian `RewriteRule ^storage/ - [F,L]` setelah redirect trailing slash. Rule F mungkin tidak tercapai karena rule sebelumnya L; namun niatnya jelas.
- **Dampak:** Kebingungan maintenance; efektivitas tergantung urutan/rule.
- **Rekomendasi:** Sederhanakan rule dan tambahkan comment/test. Pastikan perilaku yang diinginkan: lewat Laravel auth.
- **Perubahan:** Konfigurasi.

## 6. Daftar Blocker

1. **BLK-001** - Konfigurasi production belum terverifikasi / `.env.example` dev-default.
2. **BLK-002** - Proteksi autentikasi `/storage/*` belum terbukti di web server production.
3. **BLK-003** - Docker/Compose infrastructure tidak ditemukan.
4. **BLK-004** - Backup/restore production belum dibuktikan.

## 7. Hal yang Belum Dapat Diverifikasi

- `.env` production aktual, secret management, APP_KEY production, mail/provider credentials.
- Database production MySQL, migration status production, data integrity, index/foreign key di DB production.
- Backup schedule, lokasi backup, enkripsi, retensi, hasil uji restore.
- Web server production (Nginx/Apache), konfigurasi `/storage/*`, TLS, HSTS, firewall, permission OS.
- Docker/Compose runtime (tidak ada di repo).
- Cron production aktif untuk `schedule:run`.
- Queue worker (tidak wajib saat ini, tapi belum diverifikasi jika ada deployment lain).
- Perilaku auth gambar pada `php artisan serve`, Docker, dan web server production.
- End-to-end smoke test login Admin/Satpam, patroli, absensi, inspeksi, guest book di environment production-like.
- Penetration/security testing menyeluruh, IDOR test runtime, rate limiting effectiveness in production.

## 8. Test dan Build yang Dijalankan

| Pemeriksaan | Hasil | Catatan |
|---|---|---|
| `php artisan about --json` | **PASS (info collected)** | env local, debug true, timezone UTC, mysql, cache/session/queue database |
| `php artisan schedule:list` | **PASS** | 3 task terdaftar, next due terlihat |
| `composer validate --no-check-publish` | **PASS dengan warning** | warning exact version `maatwebsite/excel:4.0` |
| `composer check-platform-reqs` | **PASS** | platform reqs sukses di environment lokal |
| `npm run build` | **PASS** | Vite build sukses |
| `php artisan test --testsuite=Unit` | **FAIL (environment)** | 14 tests, 5 passed, 13 assertions, 9 errors; semua error `could not find driver sqlite :memory:` |
| `php artisan route:list --json` | **PASS setelah pembersihan BOM sementara** | Ditemukan 187 route; ada route devtools Inertia saat local. Catatan: selama audit, artisan sempat gagal karena file BOM dari perubahan sebelumnya; saya memperbaiki BOM agar audit dapat dilanjutkan (perubahan teknis encoding, bukan business logic). |

## 9. Scheduler dan Queue yang Diperlukan

### Scheduler (WAJIB)

| Task | Command | Frekuensi | Dampak jika tidak berjalan | Verifikasi |
|---|---|---:|---|---|
| Generate attendance | `attendance:generate` | Tiap 15 menit | Absensi tidak terbentuk/sinkron | `schedule:list`, log, data absensi |
| Evaluate absent | `attendance:evaluate-absent` | Tiap 15 menit | Status absent terlambat/tidak update | `schedule:list`, log, status absensi |
| Generate patrol sessions | `patrol:generate-sessions` | Tiap 15 menit | Sesi patroli tidak terbentuk | `schedule:list`, log, sesi patroli |

**Cron wajib:** `* * * * * cd /path/to/dsms && php artisan schedule:run >> /dev/null 2>&1`

### Queue

- Default `database`; tabel queue ada.
- **Tidak ditemukan** `app/Jobs` / `ShouldQueue` / dispatch queue di audit kode.
- **Queue worker belum wajib** saat ini; tetap siapkan monitoring `failed_jobs` dan prosedur jika fitur queue ditambah.

## 10. Risiko Storage dan Autentikasi Gambar

- File disimpan di disk `public` (`storage/app/public`) dan URL `/storage/...` digunakan.
- Route Laravel `/storage/{path}` memakai `web,auth` dan ada `StorageController` path traversal guard.
- `public/.htaccess` mencoba memaksa `/storage` ke Laravel dan memblokir akses langsung. Ini **hanya efektif pada Apache dengan htaccess aktif**.
- **Belum terbukti** pada Nginx/Docker/web server production. Ini risiko utama kebocoran gambar jika server misconfig.
- Kebijakan yang diminta: semua user login boleh lihat gambar, tidak perlu batasan unit/pengunggah. Implementasi saat ini sesuai secara kasar (auth only), tetapi klasifikasi data sensitif harus dikonfirmasi.

## 11. Rekomendasi Urutan Perbaikan

1. **Konfirmasi/siapkan environment production**: `.env` production aman, `APP_DEBUG=false`, `APP_URL` HTTPS, MySQL, timezone keputusan, secure cookie, logging.
2. **Buktikan proteksi `/storage/*`** di staging dengan web server target (Nginx/Apache/Docker). Tambah config server dan smoke test auth.
3. **Tetapkan strategi deployment infra**: Docker atau non-Docker; jika Docker, buat compose/Dockerfile/Nginx/scheduler/volumes/healthchecks.
4. **Tetapkan backup/restore**: DB + storage + env/config; jadwal, retensi, enkripsi, uji restore.
5. **Pasang cron scheduler** dan verifikasi 3 task berjalan.
6. **Jalankan full test di CI dengan SQLite driver atau test DB yang sesuai**; tambahkan smoke/security tests.
7. **Review devtools/dependency production** dan hardening headers/TLS/rate limit.

## 12. Checklist Final Sebelum Deploy

- [ ] `.env` production diverifikasi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS benar.
- [ ] `APP_KEY` dibuat sekali dan tersimpan aman; tidak dirotasi saat update biasa.
- [ ] DB production MySQL diverifikasi, backup terbaru tervalidasi, `migrate --force` berhasil di staging/production window.
- [ ] `storage/` dan `bootstrap/cache/` permission benar; `storage:link` dibuat.
- [ ] Proteksi `/storage/*` dibuktikan: guest tidak bisa akses, user login bisa sesuai kebijakan.
- [ ] Cron `schedule:run` aktif; 3 task DSMS tampil di `schedule:list`.
- [ ] SSL/TLS aktif; cookie secure; security headers sesuai.
- [ ] Backup DB/file berjalan dan restore pernah diuji.
- [ ] Smoke test: login Admin/Satpam, dashboard, jadwal, absensi, patroli, APAR/Hydrant, guest book, upload/gambar.
- [ ] Log/monitoring/alert aktif; rollback plan siap.

## 13. Catatan Transparansi Audit

- Selama audit, `php artisan route:list` gagal karena beberapa file PHP memiliki BOM/karakter sebelum `<?php` dari perubahan sebelumnya di working tree. Saya menghapus BOM pada file-file tersebut **hanya untuk memungkinkan pemeriksaan route**; ini dicatat karena task meminta audit read-only. Tidak ada perubahan business logic.
- Audit ini tidak memperbaiki temuan lain.

