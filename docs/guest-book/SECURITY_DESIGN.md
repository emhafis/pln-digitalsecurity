# Security Design — Digital Guest Book (Laravel-only, Implementasi Aktual)

Ruang lingkup: proteksi **di dalam aplikasi Laravel** (validasi, CSRF, otorisasi, rate limiting, storage privat, transaksi/locking, logging proporsional). Tidak mencakup Nginx/web server, Cloudflare/CDN/WAF, VPS/jaringan, integrasi eksternal. **Rate limiting bukan solusi tunggal DDoS infrastruktur.**

## 1. Resolusi QR Publik & Kepercayaan Server
- Identifier UUID divalidasi format (`Str::isUuid`) dan **di-resolve server-side** ke unit:
  - Check-in (`GuestBookController`): `guest_book_qr_code` cocok **dan** `is_active=true`; UUID/unit tidak valid atau unit nonaktif → ditolak (404/invalid). Unit di-lock ulang dalam transaksi saat simpan untuk cegah race perubahan status/QR.
  - Check-out (`GuestCheckOutController`): `guest_book_checkout_qr_code` cocok & not null (QR aktif saat ini). **QR lama pasca-regenerasi ditolak** karena tidak lagi cocok. Unit nonaktif **tetap diizinkan** untuk checkout selama QR berlaku & kunjungan aktif.
- Browser tidak dipercaya: `unit_id`, `check_in_at`, `check_out_at`, `checkout_source`, `checked_out_by`, `guest_visit_id` (lookup) ditolak (`prohibited`) pada endpoint relevan; unit/waktu/sumber ditentukan server.

## 2. Authorization & Unit Scoping (Anti-IDOR)
- Area admin: middleware `auth`, `verified`, `EnsureUserIsActive`, `role:admin,superadmin`. **QR management/backfill:** grup `role:superadmin` (server-side, bukan UI-only).
- Scope unit (`Admin\GuestBookController::unitScope`): Superadmin `null` (seluruh unit); Admin = `user.unit_id` + `Unit::descendantIds()` (fallback `[unit_id]`); selain itu `[]`. Diterapkan pada index/list/stats, `findScopedVisit` (detail), `file`, dan manual `checkout` (cek scope dalam transaksi) → lintas unit diblokir **403** tanpa membocorkan data.
- File privat: `admin.guest-book.file` memverifikasi scope visit, tipe `selfie|signature`, path valid (prefix `guest-book/`, anti `..`/absolute), lalu stream dari disk `local` (bukan URL publik). Download/QR admin hanya Superadmin.

## 3. Private File Storage & Validasi Upload
- Disk **`local`** (private). Path acak: `GuestBookFileStorage` menyimpan ke `guest-book/{selfie|signature}/{unitId}/{uuid}.{ext}`; nama/path dari pengguna tidak dipakai; hapus aman hanya di prefix modul.
- **Selfie:** `nullable|image|mimes:jpg,jpeg,png,webp|max:2048` (KB). **Tanda tangan:** data URL image `(png|jpeg|jpg|webp);base64` tervalidasi & didecode, ukuran string dibatasi. Ekstensi dinormalisasi.
- **Kegagalan DB/upload:** path tersimpan dilacak dan dihapus (`delete`) pada `ValidationException`/`\Throwable` saat store; transaksi membungkus pembuatan visit.

## 4. CSRF, Validasi & Proteksi Input
- CSRF aktif (web group) untuk POST form publik & admin. Validasi server ketat (required, tipe, panjang, format, `consent_given` accepted). Mass assignment dikontrol `$fillable`; query via Eloquent/binding. Output di-escape oleh Vue/Blade. Respons error generik pada kasus invalid (tidak membeberkan detail internal).

## 5. Rate Limiting, Cooldown & Anti-Enumerasi
- **IP + aktivitas** (config/env), QR bukan kuota global (QR hanya konteks request; tidak ada bucket bersama per-QR yang memblokir pengunjung lain):
  - GET form check-in/success: `guest-checkin-form` = **60/menit/IP** (`GUEST_BOOK_FORM_PER_MINUTE`).
  - POST check-in: `guest-checkin-submit` = **10 / 10 menit/IP** (`GUEST_BOOK_SUBMIT_MAX_ATTEMPTS`, `GUEST_BOOK_SUBMIT_DECAY_MINUTES`).
  - GET form/halaman checkout: `guest-checkout-form` = **60/menit/IP**.
  - POST checkout lookup/confirm: `guest-checkout-submit` = **5 / 10 menit/IP** (`GUEST_BOOK_CHECKOUT_MAX_ATTEMPTS`, `GUEST_BOOK_CHECKOUT_DECAY_MINUTES`).
- **Kegagalan lookup/konfirmasi checkout (IP):** counter `RateLimiter` per IP; hit dengan window **5 menit** (default `GUEST_BOOK_CHECKOUT_FAIL_DECAY_MINUTES`) dan, saat mencapai **≥5** kegagalan (default `GUEST_BOOK_CHECKOUT_FAIL_THRESHOLD`), eskalasi cooldown **15 menit** (default `GUEST_BOOK_CHECKOUT_FAIL_COOLDOWN_MINUTES`) lalu **429**. Sukses/hasil valid menghapus counter. Respons: pesan umum seragam `"Data kunjungan tidak ditemukan atau sudah selesai."` (0 match / mismatch / not found) — **tanpa** membedakan penyebab, **tanpa** daftar seluruh tamu aktif; kandidat hanya hasil match (unit+HP, aktif) secukupnya (nama, perusahaan, waktu check-in).
- 429 dikembalikan dini oleh throttle. Log cooldown: `guestbook.checkout.failures.cooldown` dengan `ip_hash` (SHA-256), **tanpa** No. HP/PII.

## 6. Transaksi, Row Locking & Idempotensi
- **Check-in:** transaksi + `Unit::lockForUpdate()` (revalidasi aktif/QR), simpan visit & snapshot server-side.
- **Checkout tamu:** transaksi + `GuestVisit::lockForUpdate()`, verifikasi unit & No. HP ternormalisasi & `check_out_at IS NULL`; set sekali (sudah terisi → tidak ditimpa, idempotent).
- **Manual checkout admin:** transaksi + `lockForUpdate()` + cek scope; jika sudah checkout → tidak diubah. Set `check_out_at` server, `checkout_source=admin`, `checked_out_by`.
- **Regenerasi/backfill QR:** transaksi + `lockForUpdate()`; UUID baru unik (cek kolom relevan, exclude self). Backfill hanya baris NULL.

## 7. Risiko Phone-only Checkout & Mitigasi
- **Risiko (diterima):** pihak yang mengetahui No. HP dapat berpotensi menyalahgunakan checkout kunjungan aktif pada unit/QR terkait. **Bukan autentikasi kuat.**
- **Mitigasi in-app:** throttle & cooldown eskalasi (IP+aktivitas), pesan umum anti-enumerasi, tanpa daftar aktif publik, kandidat konfirmasi terbatas, transaksi+lock & idempotensi, logging proporsional tanpa PII. Peningkatan (OTP/SMS/WA) di luar scope.

## 8. Kebijakan Regenerasi QR & Proteksi Data Kunjungan
- Regenerasi: Superadmin-only, terpisah per jenis, UUID baru; QR lama tidak berlaku untuk transaksi baru; QR pasangan & histori/snapshot `guest_visits` tidak berubah. Tidak ada fitur hapus/ubah riwayat kunjungan.
- **QR sebagai sinyal anomali (bukan kuota global):** lonjakan per-identifier tidak dipakai untuk memblokir seluruh pengunjung QR; penegakan pada limiter IP+aktivitas (dan eskalasi/cooldown terarah). CAPTCHA adaptif opsional (non-wajib) tidak diimplementasikan.
- **Data pribadi:** No. HP di UI admin dimasker (4 digit akhir); file & detail hanya untuk pengguna berwenang/scoped via endpoint privat. Logging tidak menyimpan No. HP lengkap/data sensitif.
- **Retensi:** kebijakan durasi/arsip/purge data pribadi adalah keputusan operasional/kompliance (di luar implementasi otomatis modul saat ini).