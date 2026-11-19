# Operations — Digital Guest Book

Panduan operasional modul Guest Book (implementasi aktual). **Bedakan LOCAL dan PRODUCTION.** Jangan menuliskan/mengeksekusi credential, secret, atau data pribadi. Jangan mengklaim backup production tersedia jika belum diverifikasi.

## 1. Migration (Environment yang Benar)
- **LOCAL:** pastikan `.env` menunjuk DB local yang benar (`APP_ENV`, `DB_*` diverifikasi). Jalankan: `php artisan migrate`. Verifikasi: `php artisan migrate:status` (filter `guest_book`). **Dilarang:** `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`.
- **PRODUCTION:** **hanya** pada database production yang telah dipastikan benar, sesuai prosedur rilis & persetujuan eksplisit, setelah **backup terbaru terverifikasi**. Gunakan `php artisan migrate --force` (normal) bila prosedur mengizinkan. **Dilarang** menjalankan perintah destruktif atau eksekusi tanpa verifikasi target/backup. Dokumen ini **tidak** mengeksekusi migration production.

## 2. Prosedur Backup Sebelum Migration (Wajib Verifikasi)
- Pastikan backup database (dan bila relevan storage privat) **terbaru & tervalidasi/restore-able** sesuai kebijakan tim sebelum migration/backfill di production. Jika backup belum tersedia/terverifikasi: **berhenti**.
- LOCAL: backup opsional namun disarankan sebelum perubahan skema/data.

## 3. Verifikasi Migration
- `php artisan migrate:status` → `2026_11_18_000001_add_guest_book_checkout_qr_code_to_units_table` dan `2026_11_18_000002_create_guest_visits_table` berstatus Ran.
- Konfirmasi kolom `units.guest_book_checkout_qr_code` dan tabel `guest_visits` ada; cek struktur/index sesuai `DATABASE_SCHEMA.md` bila perlu (query skema read-only).

## 4. Prosedur Backfill QR Checkout (Unit Existing)
- **Tujuan:** isi `guest_book_checkout_qr_code` yang **NULL** dengan UUID v4 unik (seluruh unit), tanpa mengubah `guest_book_qr_code`, checkout terisi, atau `guest_visits`. Transaksi + lock, **idempotent/aman diulang**.
- **Prasyarat:** migration kolom sudah Ran; preflight baca-only (jumlah unit, jumlah NULL/terisi checkout, target DB benar). **Jangan** jalankan jika target/backup (production) tidak pasti.
- **Cara (mekanisme tersedia):**
  - UI Superadmin: `/admin/guest-book/qr` → tombol **Backfill QR Check-out** (route `admin.guest-book.qr.backfill`, `POST /admin/guest-book/qr/backfill`, konfirmasi) memanggil `GuestBookQrService::backfillMissingCheckoutQrCodes()`.
  - Alternatif terkontrol (tinker/script artisan satu kali, environment benar): `app(App\Services\GuestBookQrService::class)->backfillMissingCheckoutQrCodes()` (mengembalikan jumlah baris diisi). Gunakan hanya pada DB yang telah dipastikan.
- **LOCAL:** boleh dijalankan setelah preflight; verifikasi hasil (lihat §7/§8). **PRODUCTION:** **hanya** setelah persetujuan eksplisit, target DB production terkonfirmasi, backup terverifikasi, dan preflight; eksekusi satu kali lalu verifikasi — **tidak** dilakukan otomatis oleh dokumen ini.

## 5. Melihat Status QR per Unit
- Superadmin: `/admin/guest-book/qr` (daftar: status unit, QR Check-in/Checkout siap/belum, indikator jumlah checkout NULL) → detail `/admin/guest-book/qr/{unit}` (UUID, URL, QR, unduh PNG `admin.guest-book.qr.download`). Akses terbatas Superadmin.
- Cek cepat (read-only, DB benar): hitung `guest_book_checkout_qr_code IS NULL` / duplikasi / tabrakan dengan check-in via query agregasi.

## 6. Prosedur Regenerasi QR oleh Superadmin
- UI: detail unit `/admin/guest-book/qr/{unit}` → **Regenerasi Check-in** (`admin.guest-book.qr.regenerate-checkin`) atau **Regenerasi Check-out** (`admin.guest-book.qr.regenerate-checkout`), masing-masing dengan konfirmasi.
- Efek: UUID baru unik, transaksi+lock; QR lama tidak berlaku untuk transaksi baru; QR pasangan & histori/snapshot `guest_visits` tidak berubah. Cetak/unduh & ganti QR fisik di lokasi unit setelah regenerasi.
- Terbatas Superadmin (server-side). Audit log khusus regenerasi tidak otomatis (tidak ada pola audit existing di area ini).

## 7. Menangani QR Invalid atau Telah Diregenerasi
- Gejala: halaman invalid/404 “QR Code tidak valid atau tidak dapat digunakan”.
- Tindakan: pastikan tamu memindai QR **terkini** yang terpasang di unit; verifikasi status QR unit di `/admin/guest-book/qr/{unit}` (Superadmin). Jika QR checkout unit NULL (belum backfill) → lakukan backfill pada environment yang benar (lihat §4) lalu verifikasi. Jika QR fisik usang karena regenerasi → ganti dengan QR baru (unduh PNG). Jangan melemahkan validasi server.

## 8. Lokasi Penyimpanan File Private
- Disk `local` (private): root `storage/app/private` (default Laravel). Path modul: `guest-book/{selfie|signature}/{unitId}/{uuid}.{ext}`. Akses hanya via `admin.guest-book.file` (otorisasi+scope). Bukan direktori publik/symlink publik.

## 9. Troubleshooting Umum
- **QR invalid/404:** UUID salah, QR diregenerasi/usang, checkout NULL (belum backfill), atau (check-in) unit nonaktif. Cek status unit/QR (Superadmin) & kesesuaian QR fisik.
- **Checkout “tidak ditemukan/sudah selesai”:** pesan umum (anti-enumerasi) — bisa berarti No. HP tidak cocok (normalisasi ke 62…), tidak ada kunjungan aktif di unit QR, atau sudah checkout. Pastikan nomor sama dengan saat check-in dan QR checkout unit benar.
- **429 Terlalu banyak percobaan:** throttle IP+aktivitas atau cooldown kegagalan lookup (threshold/window configurable). Tunggu window/cooldown; jangan gunakan kuota global per-QR.
- **403 di admin/file/QR:** akun bukan admin/superadmin, admin di luar scope unit, atau aksi QR oleh non-Superadmin. Periksa role, `unit_id`, dan hierarki.
- **File selfie/tanda tangan tidak tampil:** path kosong/tidak ada di disk `local`, atau akses di luar scope — verifikasi record & otorisasi.
- **Backfill tidak mengubah apa pun:** idempoten jika tidak ada `guest_book_checkout_qr_code` NULL.

## 10. Checklist Deployment & Verifikasi Pascadeployment
- [ ] (PROD) Target DB production terkonfirmasi; **backup terbaru terverifikasi** sebelum perubahan.
- [ ] Jalankan migration normal (bukan destruktif) pada environment yang benar; `migrate:status` guest_book = Ran.
- [ ] Pastikan `units.guest_book_checkout_qr_code` terisi untuk semua unit (atau rencanakan backfill dengan persetujuan): tidak ada NULL tak sengaja, tidak ada duplikasi/tabrakan check-in vs checkout.
- [ ] Verifikasi akses: publik dapat resolve QR valid; admin scoped; QR management/backfill hanya Superadmin.
- [ ] Smoke test: check-in (unit aktif), checkout (0/1/>1, idempotent), admin detail/file/manual checkout, regenerasi (QR lama ditolak, histori utuh) pada environment uji.
- [ ] Konfirmasi rate limit env (bila override) & storage privat writable; `npm run build` aset deploy; `php artisan test --filter=GuestBook` & `--filter=UnitGuestBookQrModelTest` lulus di pipeline (SQLite in-memory).
- [ ] **Tidak** menjalankan backfill/migration production tanpa persetujuan eksplisit & prasyarat terpenuhi.