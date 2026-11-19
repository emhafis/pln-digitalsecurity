# Implementation Plan — Digital Guest Book

Urutan kerja implementasi modul Guest Book (in-app Laravel), mengikuti konvensi existing, tanpa mengubah modul Scheduling/Attendance/Patrol/APAR/Hydrant atau struktur `users`. **TASK 01–08 selesai** pada ruang lingkup modul (lihat bukti uji/build/verifikasi di bawah).

## Status Task

| Task | Status | Ringkasan hasil aktual |
|---|---|---|
| TASK 01 — Dokumentasi & keputusan | **Selesai** | `docs/guest-book/README,BUSINESS_RULES,DATABASE_SCHEMA,SECURITY_DESIGN,IMPLEMENTATION_PLAN` disusun & diselaraskan dengan keputusan final (2 QR, checkout via HP, unit nonaktif: tolak check-in/izinkan checkout aktif QR berlaku, FK `checked_out_by` restrict, scope `descendantIds()`, limiter IP+aktivitas). |
| TASK 02 — (cakupan desain/rencana) | **Selesai** | Prasyarat keputusan (checkout QR terpisah, limiter configurable, stack Inertia/Vue, retensi sebagai kebijakan operasional) tercakup dalam dokumen & implementasi. |
| TASK 03 — Fondasi Data & Domain | **Selesai** | Migration `2026_11_18_000001_add_guest_book_checkout_qr_code_to_units_table`, `2026_11_18_000002_create_guest_visits_table`; model `GuestVisit` (relasi `unit`, `checkedOutBy`, casts), `Unit` (`guestVisits()`, auto QR saat `creating`, tidak ubah saat `updating`); service/support `GuestBookQrService`, `GuestBookPhone`, `GuestBookFileStorage`. |
| TASK 04 — Alur Publik Check-in | **Selesai** | Route/controller GET/POST check-in, resolusi QR server-side, tolak unit nonaktif/QR invalid, validasi & consent, `unit_id`/`check_in_at` prohibited, simpan file privat acak + cleanup on failure, snapshot & waktu server, limiter IP+aktivitas, halaman sukses/invalid. |
| TASK 05 — Alur Publik Check-out (Nomor HP) | **Selesai** | Route/controller lookup+confirm via QR checkout, normalisasi HP (62…), 0 pesan umum/1 konfirmasi/>1 pilihan, tanpa daftar aktif publik, transaksi+`lockForUpdate`, idempotent, tolak QR lama, checkout tetap boleh saat unit nonaktif (QR berlaku & kunjungan aktif), throttle + cooldown kegagalan eskalasi. |
| TASK 06 — Admin Monitoring & Manajemen | **Selesai** | Index (stats, active, daftar filter/paginasi), detail snapshot, manual checkout (server, `admin`, `checked_out_by`, transaksi+lock, idempotent), stream file privat terotorisasi, scope Superadmin semua / Admin unit+descendant (`descendantIds()`), masking No. HP. |
| TASK 07 — Pengelolaan QR (Superadmin) | **Selesai** | Halaman daftar/detail QR, tampil URL & QR (PNG data-uri) + unduh PNG, regenerasi check-in/checkout terpisah (Superadmin-only, transaksi+lock, unik, QR lama invalid, histori utuh), backfill checkout NULL (idempoten, seluruh unit, mekanisme tersedia) + eksekusi/verifikasi pada DB local. |
| TASK 08 — Hardening, Observability & Test Suite | **Selesai (modul Guest Book)** | Audit & hardening final (normalisasi HP, cooldown decay configurable + log hashed IP tanpa PII, copy invalid generik), integrasi/regresi Guest Book lulus, full suite dijalankan (kegagalan non-Guest Book dilaporkan terpisah), build sukses, verifikasi DB local, dokumentasi diperbarui & `OPERATIONS.md` ditambahkan. Logging proporsional tanpa PII sensitif; retensi/purge data pribadi = kebijakan operasional (belum diotomasi). |

## Acceptance Criteria Global (terpenuhi)
- Dua QR terpisah per unit (UUID acak, stabil, bukan `unit_id`), regenerasi Superadmin-only; QR lama tidak berlaku untuk transaksi baru; histori/snapshot utuh.
- Check-in: server-side, unit dari QR, tolak unit nonaktif. Check-out: via No. HP (tanpa OTP/SMS/WA/token), diizinkan saat unit nonaktif jika QR Checkout masih berlaku & kunjungan aktif; idempotent & race-safe; tanpa daftar aktif publik; anti-enumerasi.
- Rate limiting **IP+aktivitas** (configurable), 429 tolak dini; QR bukan kuota global.
- `checked_out_by` FK nullable → `users.id` `restrictOnDelete`; struktur `users` tidak diubah.
- Query/aksi admin scoped `descendantIds()`; file sensitif privat & terotorisasi; client tidak dipercaya untuk unit/timestamp/status. Tanpa perubahan modul lain.

## Hasil Test & Build (aktual)
- `php artisan test --filter=GuestBook` (SQLite in-memory): **PASS — 51 tests, 210 assertions** (check-in/out, scope/IDOR, file privat, manual checkout, QR/regenerasi/backfill, limiter/cooldown).
- `php artisan test --filter=UnitGuestBookQrModelTest`: **PASS — 4 tests, 16 assertions** (auto QR create/unik/tidak timpa, update tidak ubah, backfill null-only/idempoten).
- `php artisan test` (full suite): **628 tests, 549 passed, 4425 assertions, 66 failed, 13 errors** — seluruh kegagalan/error **tidak terkait Guest Book** (pre-existing): Scheduling (`ScheduleGeneration*`, `SchedulePattern*`, `SchedulingEndToEnd*`), modul Admin lain (`SecurityPostCoordinate`, `SecurityTeam*`, `Shift*`), dan `Security/AreaAccess*` (route/controller missing atau ekspektasi route lain di environment ini). Tidak ada kegagalan pada test Guest Book; modul lain tidak diperbaiki.
- `npm run build`: **sukses**.

## Verifikasi Database Local (pln_digtyaceh_db, 127.0.0.1, local)
- Migration guest_book: **[7] Ran** (000001 & 000002). `units` = 54 (tetap), `guest_book_qr_code` NULL = 0, `guest_book_checkout_qr_code` NULL = 0, duplikasi check-in/checkout = 0, tabrakan check-in↔checkout = 0, `guest_visits` = 0 (tidak berubah akibat backfill). Backfill checkout NULL dieksekusi & diverifikasi idempoten (rerun 0) pada local; **tidak dijalankan di production**.

## Keterbatasan / Catatan
- Retensi/purge data pribadi: kebijakan operasional/kompliance, belum diotomasi. Audit log khusus regenerasi QR: tidak ditambahkan (tidak ada pola audit existing). CAPTCHA adaptif: opsional, tidak diimplementasikan.
- `php artisan route:list` gagal pada environment ini karena controller modul lain (`ScheduleGenerationController`) hilang (pre-existing, di luar Guest Book); route Guest Book terdaftar di `routes/web.php`.
- Production: migration/backfill **tidak** dieksekusi; memerlukan target DB terkonfirmasi, backup terverifikasi, dan persetujuan eksplisit.