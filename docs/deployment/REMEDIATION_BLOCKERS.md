# Addendum Remediasi - 2026-11-21

Dokumen ini memperbarui status audit setelah remediasi parsial. Status keseluruhan tetap **NOT READY**.

## Perubahan yang Dilakukan

1. **BLK-001 (parsial)** - `.env.example` diperbarui menjadi template aman (tanpa secret): panduan production, `APP_DEBUG=false`, HTTPS `APP_URL`, `APP_TIMEZONE=Asia/Jakarta`, `SESSION_SECURE_COOKIE`, DB MySQL, catatan APP_KEY. `config/app.php` mendukung `APP_TIMEZONE`.
2. **BLK-002 (parsial)** - Ditambah template Nginx `docker/nginx/default.conf` yang meneruskan `/storage/*` ke Laravel; ditambah test feature `tests/Feature/Storage/AuthenticatedStorageAccessTest.php`. Route Laravel `web,auth` dan `StorageController` sudah ada.
3. **BLK-003 (parsial)** - Ditambah template `Dockerfile`, `docker-compose.yml`, `docker/php/php.ini`, `.dockerignore`. Belum diuji di Docker runtime dan belum mencakup TLS/secrets/backup/monitoring production.
4. **BLK-004 (masih terbuka)** - Prosedur backup/restore ada di dokumen, tetapi belum ada bukti backup terjadwal dan uji restore.

## Bukti Belum Cukup

- PHPUnit lokal gagal karena `pdo_sqlite/sqlite3` tidak terpasang di PHP CLI; test `/storage` baru belum dieksekusi.
- Tidak ada smoke test Nginx/Docker yang membuktikan guest ditolak dan admin/security diterima untuk `/storage/...`.
- Tidak ada `.env` production/staging terverifikasi.
- Tidak ada bukti backup/restore.

## Status Blocker

| Blocker | Status |
|---|---|
| BLK-001 | Terbuka (parsial) |
| BLK-002 | Terbuka (parsial) |
| BLK-003 | Terbuka (parsial) |
| BLK-004 | Terbuka |

Lihat `docs/deployment/REMEDIATION_BLOCKERS.md` untuk detail.
