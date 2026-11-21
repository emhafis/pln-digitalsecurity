# Deployment Checklist — DSMS

Gunakan checklist ini untuk deployment DSMS. Sesuaikan placeholder dengan environment server aktual. Jangan menjalankan command destruktif (`migrate:fresh`, `db:wipe`, `migrate:reset`).

## First Deployment

### Persiapan
- [ ] Konfirmasi target server, OS, web server, PHP-FPM, domain, dan SSL.
- [ ] Konfirmasi PHP >= 8.3 dan extensions yang dibutuhkan terpasang (`php -m`).
- [ ] Konfirmasi Composer 2, Node.js/npm, database server tersedia.
- [ ] Konfirmasi user deploy dan user PHP-FPM.
- [ ] Konfirmasi strategi backup database dan file.

### Kode & Environment
- [ ] Upload/clone source code ke path deployment.
- [ ] Pastikan document root web server menunjuk ke `public/`.
- [ ] Salin `.env.example` menjadi `.env` (hanya first deploy).
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`.
- [ ] Set `DB_*`, session/cache/queue/log/mail/filesystem sesuai server.
- [ ] Pastikan secret/password tidak disimpan di repo.
- [ ] Jalankan `php artisan key:generate` **sekali** pada first deploy.

### Dependency & Build
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm ci`
- [ ] `npm run build`
- [ ] Pastikan `public/build` ter-deploy.

### Database & Storage
- [ ] Backup database (jika ada data) / siapkan DB kosong.
- [ ] `php artisan migrate --status`
- [ ] `php artisan migrate --force`
- [ ] `php artisan storage:link`
- [ ] Pastikan `storage/` dan `bootstrap/cache/` writable oleh PHP user.
- [ ] Verifikasi permission tidak terlalu permisif (tidak 777).

### Cache & Service
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Reload/restart PHP-FPM dan web server.
- [ ] Pasang cron scheduler: `* * * * * cd /path/to/dsms && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Konfirmasi queue worker **tidak diperlukan** saat ini, kecuali ada fitur queue baru.

### Keamanan & Akses
- [ ] HTTPS aktif dan valid.
- [ ] HTTP redirect ke HTTPS.
- [ ] Firewall membuka port yang diperlukan saja (80/443/SSH sesuai kebijakan).
- [ ] `/storage/*` tidak bypass auth Laravel secara tidak aman.
- [ ] `APP_DEBUG=false`.

## Routine Deployment / Update

### Pra-deploy
- [ ] Konfirmasi release/tag/commit yang akan dideploy.
- [ ] Backup database jika ada migration atau perubahan berisiko.
- [ ] Backup file `storage/app/public` / private bila diperlukan.
- [ ] Pertimbangkan `php artisan down --retry=60` jika perlu maintenance window.

### Deploy
- [ ] Update source code ke versi target.
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm ci`
- [ ] `npm run build`
- [ ] `php artisan migrate --status`
- [ ] `php artisan migrate --force` (jika ada migration pending).
- [ ] `php artisan optimize:clear`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Restart/reload PHP-FPM/web server.
- [ ] Jika pakai queue worker: `php artisan queue:restart`.
- [ ] **Jangan** jalankan `php artisan key:generate`.

### Pasca-deploy
- [ ] `php artisan up` jika maintenance mode aktif.
- [ ] Jalankan post-deployment verification.

## Post-Deployment Verification

- [ ] `GET /up` mengembalikan status sehat.
- [ ] Halaman login Admin dapat dibuka.
- [ ] Login Admin berhasil.
- [ ] Login Security/Satpam berhasil (akun uji sesuai kebijakan).
- [ ] Dashboard/Admin page utama termuat tanpa error 500.
- [ ] Halaman `/app` security termuat.
- [ ] Monitoring patroli dapat dibuka.
- [ ] Monitoring absensi/readiness dapat dibuka.
- [ ] Monitoring inspeksi APAR dan Hydrant dapat dibuka.
- [ ] Guest Book admin/QR/halaman publik (jika aktif) berfungsi.
- [ ] Gambar/upload tampil untuk user berwenang.
- [ ] User tidak berwenang/unauthenticated tidak dapat mengakses file yang dilindungi.
- [ ] `php artisan schedule:list` menampilkan 3 task DSMS.
- [ ] Cron/scheduler aktif di server.
- [ ] `storage/logs/laravel.log` tidak ada error kritikal baru.
- [ ] Disk space dan permission normal.

## Rollback / Recovery

- [ ] Hentikan deploy lanjutan jika ditemukan error kritikal.
- [ ] Catat waktu, versi, gejala, dan log error.
- [ ] Jika perlu, aktifkan maintenance mode.
- [ ] Rollback kode ke release sebelumnya yang stabil.
- [ ] Jalankan `composer install --no-dev --optimize-autoloader` jika dependency berubah.
- [ ] Restore `public/build`/asset sesuai release.
- [ ] Jalankan `php artisan optimize:clear` dan cache ulang.
- [ ] **Hati-hati dengan DB rollback**: migration down tidak selalu aman; utamakan restore backup DB hanya dengan prosedur yang disetujui.
- [ ] Restart PHP-FPM/web server.
- [ ] Verifikasi `/up`, login, dan modul kritikal.
- [ ] Catat root cause dan tindakan pencegahan.
