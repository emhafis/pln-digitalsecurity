# Cara Menjalankan Aplikasi

Panduan ini menjelaskan langkah menjalankan aplikasi Digital Security (DSMS)
dari nol, termasuk web server, build frontend, scheduler, dan queue worker.

## 1. Prasyarat

Pastikan perangkat sudah memiliki:

- PHP 8.3 atau lebih baru
- Composer
- Node.js 20 atau lebih baru dan npm
- MySQL 8.4 (disarankan) atau SQLite untuk pengembangan lokal
- Git

Periksa versi:

```bash
php -v
composer -V
node -v
npm -v
```

## 2. Ambil Kode dan Install Dependency

```bash
git clone <url-repository> pln-digtyaceh
cd pln-digtyaceh

composer install
npm install
```

## 3. Siapkan File Environment

Salin konfigurasi contoh, lalu buat application key:

```bash
copy .env.example .env
php artisan key:generate
```

Pada PowerShell/Linux/macOS gunakan `cp .env.example .env` jika tidak memakai
Command Prompt Windows.

## 4. Konfigurasi Database

### Opsi A: MySQL (disarankan)

Buat database terlebih dahulu, misalnya:

```sql
CREATE DATABASE pln_digtyaceh_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Sesuaikan bagian berikut di `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pln_digtyaceh_db
DB_USERNAME=root
DB_PASSWORD=
```

### Opsi B: SQLite (cepat untuk lokal)

```env
DB_CONNECTION=sqlite
# DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD tidak perlu diisi
```

Pastikan file `database/database.sqlite` tersedia:

```bash
type nul > database/database.sqlite
```

Di PowerShell gunakan `New-Item database/database.sqlite -ItemType File -Force`.

## 5. Jalankan Migrasi dan Seeder

```bash
php artisan migrate
php artisan db:seed
```

Seeder membuat tiga akun awal dengan password `password`:

| Username | Role |
|---|---|
| `superadmin` | superadmin |
| `admin` | admin |
| `security` | security |

Ganti password akun tersebut setelah login pertama.

Untuk mengulang database dari awal di lingkungan lokal:

```bash
php artisan migrate:fresh --seed
```

## 6. Buat Storage Link

Diperlukan agar file unggahan dan dokumen/QR dapat diakses publik:

```bash
php artisan storage:link
```

## 7. Build atau Jalankan Frontend

Untuk mode produksi atau sekali build:

```bash
npm run build
```

Untuk pengembangan dengan hot reload, jalankan di terminal terpisah:

```bash
npm run dev
```

## 8. Jalankan Aplikasi

Cara paling sederhana:

```bash
php artisan serve
```

Aplikasi tersedia di `http://127.0.0.1:8000` sesuai `APP_URL`.

Alternatif yang sekaligus menjalankan server, queue listener, dan Vite:

```bash
composer dev
```

## 9. Jalankan Scheduler

Scheduler menjalankan tiga command setiap 15 menit:

- `attendance:generate` - membuat/menyinkronkan data absensi dari jadwal
- `attendance:evaluate-absent` - menandai absensi terlambat sebagai absen
- `patrol:generate-sessions` - membuat sesi patroli dari periode jadwal aktif

Cek daftar jadwal:

```bash
php artisan schedule:list
```

### Pengembangan lokal

Jalankan scheduler secara terus-menerus di terminal terpisah:

```bash
php artisan schedule:work
```

### Produksi (Linux)

Tambahkan cron berikut:

```cron
* * * * * cd /path/ke/pln-digtyaceh && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler tidak berjalan otomatis tanpa cron atau `schedule:work`.

## 10. Jalankan Queue Worker

Konfigurasi default memakai queue `database`. Jika fitur yang memakai queue
diaktifkan, jalankan worker di terminal terpisah:

```bash
php artisan queue:work
```

Pada `composer dev`, queue listener sudah termasuk.

## 11. Menjalankan Command Manual

Command terjadiwal juga bisa dijalankan manual saat debugging:

```bash
php artisan attendance:generate
php artisan attendance:evaluate-absent
php artisan patrol:generate-sessions
```

## 12. Verifikasi

1. Buka `http://127.0.0.1:8000`.
2. Login memakai akun hasil seeder.
3. Buka menu Data Master dan pastikan halaman termuat.
4. Buka Periode Jadwal, lalu coba Generate Jadwal.
5. Jalankan `php artisan schedule:list` dan pastikan tiga command terjadwal.

## 13. Menjalankan Test

```bash
php artisan test
```

Hanya modul penjadwalan:

```bash
php artisan test tests/Feature/Scheduling
```

Cek format kode:

```bash
php vendor/bin/pint --test
```

## 14. Deployment Produksi

Siapkan `.env` produksi, lalu jalankan:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Setelah mengubah `.env` atau kode konfigurasi, jalankan ulang `config:cache`.
Pastikan `APP_DEBUG=false` di produksi.

## 15. Troubleshooting

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| Halaman tampil tanpa style | Asset belum di-build | `npm run build` atau jalankan `npm run dev` |
| `Vite manifest not found` | Sama seperti di atas | Build ulang asset |
| Gagal koneksi database | Kredensial `.env` salah | Periksa `DB_*` dan pastikan database dibuat |
| File/QR tidak tampil | Symlink storage belum ada | `php artisan storage:link` |
| Perubahan `.env` tidak berpengaruh | Konfigurasi ter-cache | `php artisan config:clear` |
| Route berubah tidak terbaca | Route ter-cache | `php artisan route:clear` |
| Scheduler tidak jalan | Belum ada cron atau `schedule:work` | Jalankan `php artisan schedule:work` atau pasang cron |
| 500 setelah deploy | Permission `storage`/`bootstrap/cache` | Beri izin tulis ke kedua folder tersebut |
