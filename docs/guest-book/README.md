# Digital Guest Book (Buku Tamu Digital) — DSMS

Dokumentasi utama modul Buku Tamu Digital in-app Laravel pada DSMS. Modul ini mencatat kunjungan tamu per unit melalui QR publik (tanpa login/akun/aplikasi tambahan) dan menyediakan monitoring/manajemen untuk Admin & Superadmin.

## 1. Tujuan & Ruang Lingkup
- **Tujuan:** pencatatan check-in/check-out tamu per unit secara terkontrol, terotorisasi, dan berjejak (snapshot), dengan pemisahan QR Check-in dan QR Check-out.
- **Ruang lingkup (in-app Laravel saja):** resolusi QR, form check-in/out publik, validasi & penyimpanan, monitoring/riwayat admin, manual checkout, manajemen & regenerasi QR Superadmin, backfill QR checkout (mekanisme). Rate limiting/CSRF/otorisasi adalah proteksi aplikasi, **bukan** solusi DDoS infrastruktur.
- **Di luar ruang lingkup:** konfigurasi Nginx/web server, Cloudflare/CDN/WAF, VPS/jaringan, OTP/SMS/WhatsApp/integrasi eksternal, perubahan modul lain (Scheduling, Attendance, Patrol, APAR, Hydrant) dan struktur tabel `users`.
- **Batasan kebijakan:** retensi/purge data pribadi merupakan keputusan operasional/kompliance (belum diotomasi di modul); audit log regenerasi khusus belum tersedia (mengikuti ketiadaan pola audit existing pada area terkait).

## 2. Fitur yang Tersedia
- **Publik (tanpa login):** form check-in via QR Check-in; lookup & konfirmasi check-out via QR Check-out + nomor HP (hasil 0/1/>1); halaman sukses/invalid.
- **Admin (scoped unit):** dashboard ringkasan, daftar/riwayat + filter & pagination, detail kunjungan (snapshot), akses file selfie/tanda tangan terotorisasi, check-out manual.
- **Superadmin:** seluruh unit + kelola QR per unit (tampil/unduh PNG), regenerasi QR Check-in/Check-out terpisah, backfill QR checkout yang NULL.
- **Otomatisasi:** unit baru mendapat `unit_code`, `guest_book_qr_code` (check-in) dan `guest_book_checkout_qr_code` (checkout) saat `creating` (UUID v4 unik, tidak saling sama/tidak menimpa nilai eksplisit; QR tidak berubah saat `updating`).

## 3. Arsitektur & Komponen Utama
- **Data/domain:** `units` (+ `guest_book_qr_code` existing, + `guest_book_checkout_qr_code`), `guest_visits` (snapshot unit/QR, waktu server, consent, file path privat, checkout audit). Model: `App\Models\Unit`, `App\Models\GuestVisit`.
- **Controller:** `App\Http\Controllers\Guest\GuestBookController` (check-in), `App\Http\Controllers\Guest\GuestCheckOutController` (checkout), `App\Http\Controllers\Admin\GuestBookController` (monitoring/detail/manual/file), `App\Http\Controllers\Admin\GuestBookQrController` (QR mgmt, Superadmin).
- **Service/support:** `App\Services\GuestBookQrService` (URL, PNG data-uri/string, backfill, regenerasi unik dalam transaksi+lock), `App\Support\GuestBookFileStorage` (private disk `local`, path acak `guest-book/{selfie|signature}/{unitId}`), `App\Support\GuestBookPhone` (normalisasi digit, kanonik 62…).
- **Route/middleware:** `routes/web.php` (publik + admin + grup `role:superadmin`), rate limiters di `App\Providers\AppServiceProvider`, CSRF web group, `role:admin,superadmin` untuk area admin.
- **UI (Inertia/Vue):** publik `resources/js/Pages/GuestBook/{CheckIn,Success,Invalid,CheckOut,CheckOutSuccess}.vue` (+ `resources/views/guest-book/invalid.blade.php` fallback); admin `resources/js/Pages/Admin/GuestBook/{Index,Show}.vue` & `Qr/{Index,Show}.vue`; menu di `resources/js/Components/Admin/AdminSidebar.vue`.
- **Storage file:** disk `local` (private, `storage/app/private`), diakses hanya via `admin.guest-book.file` terotorisasi+scoped.

## 4. Alur Pengunjung
- **Check-in:** scan QR Check-in (`/guest-book/{uuid}`) → server resolve UUID ke unit aktif (UUID & unit aktif wajib; unit nonaktif/QR invalid/UUID tidak valid → ditolak 404/invalid). Isi form (identitas, tujuan, bertemu, kendaraan opsional, selfie/tanda tangan opsional, consent wajib) → server validasi, tolak `unit_id`/`check_in_at` dari client, simpan file privat, transaksi+lock unit, set waktu/consent/IP/UA server-side & snapshot, redirect sukses.
- **Check-out:** scan QR Check-out (`/guest-book/checkout/{uuid}`) → input No. HP (lookup) → server cari kunjungan aktif `check_out_at IS NULL` pada **unit dari QR** dengan No. HP ternormalisasi: **0** = pesan umum, **1/>1** = kandidat konfirmasi terbatas (nama, perusahaan, waktu check-in). Konfirmasi → transaksi+`lockForUpdate`, idempotent (tidak menimpa checkout selesai), set `check_out_at` server, `checkout_source=guest`. **Boleh saat unit nonaktif** jika QR checkout masih berlaku & kunjungan aktif; QR lama pasca-regenerasi ditolak.

## 5. Alur Admin
- Akses `/admin/guest-book` (index: stats aktif/hari ini/checkout hari ini, active list, tabel filter tanggal/status/nama/instansi/HP + pagination) dan `/admin/guest-book/{id}` (detail snapshot, selfie/ttd privat, checkout manual).
- **Scope server-side:** Superadmin = semua unit; Admin = `user.unit_id` + `Unit::descendantIds()` (fallback `[unit_id]`), diterapkan pada query, detail, file, dan manual checkout (403 jika di luar scope).
- **Manual checkout:** transaksi+lock, idempotent, set `check_out_at` server, `checkout_source=admin`, `checked_out_by` user id; histori check-in/snapshot tidak berubah.

## 6. Alur Superadmin
- `/admin/guest-book/qr` (daftar unit, status QR, jumlah checkout NULL) & `/admin/guest-book/qr/{unit}` (QR data-uri + URL, unduh PNG). Rute/aksi dilindungi `role:superadmin`.
- **Regenerasi** (terpisah check-in/checkout, konfirmasi): UUID baru unik, transaksi+lock, QR lama tidak berlaku untuk transaksi baru, QR pasangan & histori/snapshot `guest_visits` tidak berubah.
- **Backfill:** isi `guest_book_checkout_qr_code` **hanya yang NULL** (seluruh unit), transaksi+lock, idempotent, tidak ubah check-in/checkout terisi/`guest_visits`. Jangan jalankan di production tanpa persetujuan & prasyarat (lihat OPERATIONS).

## 7. Daftar Route Penting
| Method | Path/Name | Akses | Keterangan |
|---|---|---|---|
| GET | `/guest-book/success` (`guest-book.success`) | publik, throttle form | sukses check-in |
| GET | `/guest-book/checkout/success` (`guest-book.checkout.success`) | publik, throttle form | sukses checkout |
| GET | `/guest-book/checkout/{uuid}` (`guest-book.checkout.show`) | publik, throttle form | form checkout |
| POST | `/guest-book/checkout/{uuid}/lookup` (`guest-book.checkout.lookup`) | publik, throttle submit | lookup HP |
| POST | `/guest-book/checkout/{uuid}/confirm` (`guest-book.checkout.confirm`) | publik, throttle submit | konfirmasi checkout |
| GET | `/guest-book/{uuid}` (`guest-book.show`) | publik, throttle form | form check-in |
| POST | `/guest-book/{uuid}` (`guest-book.store`) | publik, throttle submit | simpan check-in |
| GET | `/admin/guest-book` (`admin.guest-book.index`) | admin/superadmin | monitoring |
| GET | `/admin/guest-book/{id}` (`admin.guest-book.show`) | admin/superadmin, scoped | detail |
| POST | `/admin/guest-book/{id}/checkout` (`admin.guest-book.checkout`) | admin/superadmin, scoped | manual checkout |
| GET | `/admin/guest-book/{id}/file/{type}` (`admin.guest-book.file`) | admin/superadmin, scoped | stream file privat |
| GET | `/admin/guest-book/qr` (`admin.guest-book.qr.index`) | superadmin | daftar QR |
| GET | `/admin/guest-book/qr/{unit}` (`admin.guest-book.qr.show`) | superadmin | detail QR |
| GET | `/admin/guest-book/qr/{unit}/download/{type}` (`admin.guest-book.qr.download`) | superadmin | unduh PNG |
| POST | `/admin/guest-book/qr/{unit}/regenerate-checkin` (`admin.guest-book.qr.regenerate-checkin`) | superadmin | regenerasi check-in |
| POST | `/admin/guest-book/qr/{unit}/regenerate-checkout` (`admin.guest-book.qr.regenerate-checkout`) | superadmin | regenerasi checkout |
| POST | `/admin/guest-book/qr/backfill` (`admin.guest-book.qr.backfill`) | superadmin | backfill checkout NULL |

## 8. Instalasi & Konfigurasi
- Prasyarat mengikuti aplikasi: PHP 8.3, Composer, Node/npm, DB MySQL (prod) / SQLite in-memory (test). Dependensi QR: `endroid/qr-code` (sudah ada).
- Konfigurasi env rate limit (opsional, ada default): `GUEST_BOOK_FORM_PER_MINUTE` (60), `GUEST_BOOK_SUBMIT_DECAY_MINUTES` (10), `GUEST_BOOK_SUBMIT_MAX_ATTEMPTS` (10), `GUEST_BOOK_CHECKOUT_DECAY_MINUTES` (10), `GUEST_BOOK_CHECKOUT_MAX_ATTEMPTS` (5), `GUEST_BOOK_CHECKOUT_FAIL_THRESHOLD` (5), `GUEST_BOOK_CHECKOUT_FAIL_DECAY_MINUTES` (5), `GUEST_BOOK_CHECKOUT_FAIL_COOLDOWN_MINUTES` (15).
- Storage: disk `local` privat (default `storage/app/private`). Pastikan direktori storage writable; file di-serve via controller terotorisasi (bukan symlink publik).

## 9. Cara Menjalankan Migration
- **LOCAL (dev):** pastikan `.env` menunjuk DB local yang benar, backup bila perlu, lalu `php artisan migrate` (tanpa `migrate:fresh/refresh/reset/db:wipe`). Migration modul: `2026_11_18_000001_add_guest_book_checkout_qr_code_to_units_table`, `2026_11_18_000002_create_guest_visits_table`.
- **PRODUCTION:** hanya sesuai prosedur rilis & setelah backup terverifikasi serta persetujuan eksplisit; **tidak** dieksekusi oleh dokumentasi ini. Lihat `OPERATIONS.md`.

## 10. Cara Melakukan Verifikasi
- Test Guest Book: `php artisan test --filter=GuestBook` dan `php artisan test --filter=UnitGuestBookQrModelTest` (SQLite in-memory). Build: `npm run build`.
- Verifikasi data/route: `php artisan migrate:status` (filter guest_book), inspeksi `units` (QR unik/tidak NULL sesuai kondisi), `guest_visits`, dan `php artisan route:list` (abaikan error rute modul lain yang pre-existing di environment tertentu).

## 11. Referensi Dokumen
- [BUSINESS_RULES.md](BUSINESS_RULES.md) — aturan bisnis.
- [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) — skema & relasi (sesuai migration aktual).
- [SECURITY_DESIGN.md](SECURITY_DESIGN.md) — desain keamanan in-app.
- [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) — status task & hasil.
- [OPERATIONS.md](OPERATIONS.md) — operasi, backfill, regenerasi, troubleshooting, deployment.