# Business Rules — Digital Guest Book

Aturan bisnis final modul Buku Tamu Digital (in-app Laravel), selaras dengan implementasi aktual (`GuestBookController`, `GuestCheckOutController`, `Admin\GuestBookController`, `Admin\GuestBookQrController`, `GuestBookQrService`, `Unit`, `GuestVisit`).

## 1. QR & Unit
- **BR-QR-01 (Dua QR):** setiap unit memiliki **QR Check-in** (`units.guest_book_qr_code`) dan **QR Check-out** (`units.guest_book_checkout_qr_code`) yang berbeda.
- **BR-QR-02 (Identifier):** UUID v4 acak, tidak mudah ditebak, **bukan `unit_id`**. URL publik berbasis identifier; `unit_id` dari browser tidak dipercaya.
- **BR-QR-03 (Stabil & regenerasi):** QR stabil (tidak berubah per penggunaan). **Regenerasi hanya Superadmin**, per-jenis (check-in atau checkout) secara terpisah, dengan konfirmasi.
- **BR-QR-04 (Invalidasi pasca-regenerasi):** QR lama tidak berlaku untuk transaksi baru; histori kunjungan tetap utuh (mengandalkan snapshot `guest_book_qr_code`, `unit_name_snapshot`, `unit_code_snapshot` di `guest_visits`).
- **BR-QR-05 (Penentuan unit):** unit tujuan/konteks hanya ditentukan dari QR tervalidasi server.
- **BR-QR-06 (Unit nonaktif):** **tolak check-in baru**. **Check-out tetap diizinkan** untuk kunjungan aktif (`check_out_at IS NULL`) selama menggunakan **QR Checkout yang masih berlaku** (QR lama hasil regenerasi tetap ditolak).
- **BR-QR-07 (Otomatisasi & backfill):** unit baru mendapat kedua QR saat `creating` (UUID v4 unik per kolom, tidak sama, tidak menimpa nilai eksplisit; tidak berubah saat `updating`). Unit existing yang `guest_book_checkout_qr_code` NULL diisi via backfill (hanya NULL, idempotent, transaksi, tidak ubah QR lain/histori).

## 2. Check-in (Publik)
- **BR-CI-01 (Akses & field):** tanpa login. Field: Nama lengkap*, No. HP*, Perusahaan/instansi*, Jabatan*, Tujuan kunjungan*, Orang yang ditemui*, Nomor kendaraan (opsional), Selfie (opsional), Tanda tangan digital (opsional), Persetujuan penggunaan data*.
- **BR-CI-02 (Server-side truth):** validasi server; `unit_id` & `check_in_at` dari client ditolak (`prohibited`). `check_in_at`, `consent_at`, `ip_address`, `user_agent` ditentukan/dicatat server. Snapshot: `guest_book_qr_code`, `unit_name_snapshot`, `unit_code_snapshot`.
- **BR-CI-03 (Consent):** `consent_given` wajib true (`accepted`), `consent_at` server.
- **BR-CI-04 (File):** selfie/tanda tangan opsional, disimpan di private storage (`local`) dengan nama acak per unit/jenis; bukan URL publik.
- **BR-CI-05 (Proteksi & kondisi tolak):** CSRF aktif, rate limiting IP+aktivitas. QR/UUID tidak valid, unit nonaktif, atau QR tidak cocok → ditolak (404/invalid). Halaman sukses tanpa membocorkan data kunjungan lain.

## 3. Check-out Tamu (Nomor HP)
- **BR-CO-01 (Mekanisme):** scan **QR Check-out** + input No. HP. **Tanpa** OTP/SMS/WhatsApp/token pribadi.
- **BR-CO-02 (Pencarian):** kunjungan **aktif** (`check_out_at IS NULL`) pada **unit dari QR Checkout tervalidasi** dengan No. HP ternormalisasi (kanonik digit, format ID → 62…). `unit_id`/waktu/sumber dari client ditolak.
- **BR-CO-03 (Hasil):** **0** → pesan umum seragam (anti-enumerasi). **1** → konfirmasi ringkasan. **>1** (HP sama, kunjungan aktif) → pilihan kandidat (nama, perusahaan, waktu check-in) untuk dipilih & konfirmasi. **Tidak** menampilkan daftar seluruh pengunjung aktif.
- **BR-CO-04 (Penyelesaian):** `check_out_at` waktu server, `checkout_source='guest'`, `checked_out_by` null. **Idempotent**: checkout selesai tidak menimpa waktu pertama.
- **BR-CO-05 (Race-safety):** transaksi DB + `lockForUpdate` pada baris terpilih, verifikasi unit/HP/kondisi aktif sebelum set.
- **BR-CO-06 (Unit nonaktif & QR lama):** diizinkan jika QR checkout masih berlaku & kunjungan aktif; QR lama/regenerasi-lama ditolak untuk transaksi baru.
- **BR-CO-07 (Anti-abuse):** throttle submit (IP+aktivitas) + pencatatan kegagalan lookup/konfirmasi per IP dengan cooldown eskalasi (window/dekapan & threshold/cooldown configurable), respons 429/pesan umum tanpa PII.

## 4. Check-out Manual (Admin)
- **BR-ADM-01 (Kewenangan):** Admin hanya unit dalam scope (unit sendiri + turunan `descendantIds()`); Superadmin seluruh unit. Otorisasi & scope server-side pada setiap aksi/detail/file.
- **BR-ADM-02 (Audit):** `check_out_at` waktu server, `checkout_source='admin'`, `checked_out_by` = user id admin. Transaksi+lock, idempotent (jika sudah checkout → tidak diubah).
- **BR-ADM-03 (Integritas identitas):** `checked_out_by` FK nullable ke `users.id` dengan `restrictOnDelete`; struktur `users` tidak diubah (penonaktifan via `is_active`).
- **BR-ADM-04 (Histori):** manual checkout tidak mengubah data/snapshot check-in; tidak ada fitur hapus/ubah riwayat kunjungan.

## 5. Monitoring & Riwayat (Admin)
- **BR-MON-01:** ringkasan scoped: masih di lokasi, kunjungan hari ini, sudah checkout hari ini, daftar kunjungan aktif terbaru.
- **BR-MON-02:** daftar paginasi + filter tanggal/status/nama/instansi/HP (pencarian tidak boleh bypass scope).
- **BR-MON-03:** detail + snapshot + akses file selfie/tanda tangan via endpoint terotorisasi/scoped (private storage, bukan URL publik).
- **BR-MON-04:** status: **Masih Berada di Lokasi** (`check_out_at NULL`) / **Sudah Pulang**.

## 6. Authorization & Scope
- **BR-AUTH-01:** Superadmin akses seluruh unit; Admin akses unit sendiri + turunan (`Unit::descendantIds()`); semua query/aksi admin scoped.
- **BR-AUTH-02:** akses lintas unit diblokir (403) tanpa membocorkan data; `unit_id` dari request tidak dipercaya.
- **BR-AUTH-03:** pengelolaan & regenerasi & backfill QR **hanya Superadmin** (route middleware `role:superadmin`, bukan sekadar UI).
- **BR-AUTH-04:** publik tidak memiliki akses dashboard/daftar; hanya form via QR valid dan checkout via QR valid + verifikasi No. HP (kandidat terbatas).

## 7. Risiko yang Diterima & Larangan
- **Nomor HP bukan autentikasi kuat** (diterima): mitigasi in-app (throttle/cooldown, pesan umum, tanpa daftar aktif publik, konfirmasi terbatas, transaksi+lock). OTP/SMS/WA di luar scope.
- **Larangan:** tidak mengubah/menghapus riwayat kunjungan, tidak mengubah struktur `users`, tidak mengubah modul lain, tidak mengekspose file privat publik.