# Modul Asset (APAR & Hydrant) — DSMS

> **Wajib dibaca setiap sesi pengembangan** sebelum analisis/implementasi. Dokumen ini adalah titik masuk utama.
>
> **Sesi berikutnya:** baca **`PROGRESS.md`** terlebih dahulu untuk mengetahui status task terakhir, task aktif, blocker, dan pekerjaan selanjutnya (roadmap resmi 19 task).

## Tujuan
Menyediakan pengelolaan aset keselamatan (APAR dan Hydrant) dan pencatatan inspeksinya yang terintegrasi dengan konteks unit, aman secara otorisasi, dan menjaga riwayat historis.

## Gambaran Fitur
- **APAR (Admin)**: kelola data master APAR dalam cakupan unit yang diizinkan; lihat detail & riwayat inspeksi; output lanjut (QR PDF, kartu APAR tahunan, Excel export).
- **APAR (Security/App)**: lihat daftar & detail APAR milik unit sendiri; lakukan inspeksi pada aset aktif (record baru) beserta riwayat/detail inspeksi.
- **Hydrant**: pola fitur setara APAR (master admin, daftar/detail security, inspeksi, riwayat/detail, QR PDF & Excel export). **MVP:** pembuatan Hydrant hanya `fire_pump_diesel`; 5 jenis lain dipertahankan di schema dan dropdown (disabled) untuk pengembangan berikutnya. Kode APAR/Hydrant dibuat otomatis (`APAR-0001`, `HYD-0001`) dan tidak dapat diubah.
- **Status**: modul Asset APAR & Hydrant telah melewati Final Security Audit & Regression Test (ASSET-17): Asset suite 129 tests / 1.250 assertions PASS, `npm run build` PASS. Dokumentasi final ditutup pada ASSET-18. Struktur Hydrant tetap memakai `hydrant_code` dan `hydrant_type`; rename ke `code`/`type` belum dilakukan (B-03).

## Aturan Bisnis Utama (ringkas)
- Admin kelola aset sesuai cakupan unit. Security hanya lihat/inspeksi aset milik unitnya sendiri.
- Akun Security harus **aktif** untuk inspeksi. Inspeksi **tidak** bergantung jadwal/shift/regu/absensi/patroli; bisa kapan saja.
- Aset harus **aktif** untuk diinspeksi. Setiap inspeksi = **record baru** (tidak menimpa/menghapus histori). Riwayat tetap ada meski aset dinonaktifkan.
- Waktu inspeksi dari **server**. Notes **wajib** jika hasil tidak baik. Maks **5 foto** per inspeksi.
- Ikuti checklist & **struktur migration yang diberikan pemilik proyek**. Jangan ubah modul scheduling, attendance, patrol. Detail lengkap: `BUSINESS_RULES.md`.

## Arsitektur & Teknologi Proyek
- Laravel 13, PHP 8.3, Inertia 3, Vue 3, Tailwind CSS 4, MySQL (production), MVC klasik.
- Frontend: Heroicons, layout `AdminLayout` (admin) dan `AppLayout` (security/app).
- Pola existing: route group `/admin` (`role:admin,superadmin`) & `/app` (`role:security`,`security.profile`), scope unit own+descendants, upload public/private sesuai konteks, export Excel (maatwebsite/excel) & PDF (dompdf) — ikuti konvensi, hindari abstraksi tidak perlu.

## Dokumen Pendukung
- `PROGRESS.md` — **roadmap resmi 19 task**, status tiap task (tujuan/cakupan/kriteria), TODO/NEXT TASK, blocker, dan riwayat pekerjaan (**baca untuk task terakhir**).
- `ARCHITECTURE.md` — rencana arsitektur implementasi mengikuti pola proyek.
- `BUSINESS_RULES.md` — aturan bisnis lengkap yang disepakati.

## Catatan Audit (ASSET-01)
Audit awal (read-only) selesai. **Blocker (B-01)**: 8 migration acuan APAR/Hydrant/inspeksi (+items+photos) **belum tersedia & belum diverifikasi** di repository; struktur DB/kolom/`is_good` tidak boleh diasumsikan. `Unit::accessibleUnitIds()` belum ada (tersedia `Unit::descendantIds()`), scoping unit tersebar — implementasi harus konsisten dan aman. Lihat `PROGRESS.md`.
