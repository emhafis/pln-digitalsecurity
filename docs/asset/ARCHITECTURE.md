# Arsitektur Implementasi — Modul Asset (APAR & Hydrant)

> Dokumen ini mencerminkan implementasi aktual modul Asset APAR & Hydrant. Modul telah melewati Final Security Audit & Regression Test (ASSET-17): Asset suite 129 tests / 1.250 assertions PASS dan `npm run build` PASS. Struktur Hydrant tetap memakai `hydrant_code` dan `hydrant_type`; rename ke `code`/`type` belum dilakukan (B-03).

## 1. Prinsip Umum
- MVC Laravel + Inertia/Vue, selaras modul existing (SecurityPost/PatrolPoint/Attendance/Patrol).
- Waktu inspeksi dari **server**. Inspeksi = **record baru**, histori **immutable** (tidak update/delete), tetap terbaca saat aset non-aktif. Tanpa ketergantungan jadwal/shift/regu/absensi/patroli. Tanpa operasi destruktif data historis.
- Jangan ubah scheduling/attendance/patrol.

## 2. Struktur Model dan Relasi (implementasi aktual)
- **Aset**: model APAR dan Hydrant (nama/tabel/kolom/FK mengikuti migration acuan). Relasi ke konteks lokasi/unit (kemungkinan `unit` dan/atau `security_post` — **tentukan dari migration acuan**, jangan asumsi). Status aktif aset menentukan boleh/tidak diinspeksi.
- **Inspeksi**: model Inspection APAR & Hydrant (header) + Inspection Items + Inspection Photos sesuai migration acuan. Relasi ke aset, ke inspector (user/security) sesuai skema acuan, timestamp server. `is_good` pada inspection items **mengikuti kolom migration acuan**.
- **Aturan model**: fillable/casts eksplisit, eager load untuk listing/detail, scope query untuk filter aset dalam cakupan unit user; guard agar record inspeksi/item/foto historis tidak diubah/dihapus.
- **Integrasi existing**: manfaatkan `Unit::descendantIds()`; relasi security via `Security->User->Unit` (sesuai pola saat ini).
- **Kaitan roadmap**: fondasi domain pada ASSET-04; aturan/foto inspeksi pada ASSET-05.

## 3. Controller (implementasi aktual)
- **Admin** (`app/Http/Controllers/Admin/*`): `AparController`, `HydrantController` (dan/atau controller terfokus inspeksi/riwayat) — CRUD master dalam scope unit, list/filter/sort/paginate/detail, detail + riwayat, aksi non-destruktif. Ikuti pola validasi, transaksi bila perlu, redirect+flash. (ASSET-06, ASSET-09, ASSET-11, ASSET-14)
- **App/Security** (`app/Http/Controllers/App/*`): controller untuk list/detail aset unit sendiri & submit inspeksi (record baru). Resolusi identity `Security` by `user_id`, otorisasi kepemilikan unit & status aktif akun/aset. (ASSET-07, ASSET-08, ASSET-12, ASSET-13)
- **Concern/service**: reuse/selaraskan scoping dengan `ResolvesAttendanceScope`/pola existing; ekstraksi service hanya bila memang mengurangi duplikasi nyata (hindari abstraksi berlebihan).

## 4. Route (implementasi aktual)
- **Admin**: dalam group `middleware(['role:admin,superadmin'])->prefix('admin')->name('admin.')` (route eksplisit, penamaan konsisten, `whereNumber` bila relevan). Contoh pola: `admin.apar.*`, `admin.hydrant.*`, sub-route riwayat/detail/export/QR sesuai kebutuhan.
- **App**: dalam group `middleware(['role:security','security.profile'])->prefix('app')->name('app.')`. Contoh pola: `app.apar.*`, `app.hydrant.*` untuk index/show/inspect/submit (dan endpoint foto terproteksi bila privat).
- Ikuti konvensi `routes/web.php` existing; hindari bentrok nama/URI. Integrasi menu/navigasi pada ASSET-16.

## 5. Authorization dan Unit Scoping
- Tanpa Spatie/Policy baru kecuali pola proyek berubah; gunakan middleware `role` + `security.profile` + pengecekan scope di controller (pola existing).
- **Admin**: superadmin unlimited; admin terbatas **own unit + descendants** (`Unit::descendantIds()`), 403 bila out-of-scope. Catatan: `Unit::accessibleUnitIds()` belum ada — tentukan helper terpusat yang konsisten (jangan duplikasi tak perlu) saat implementasi (ASSET-04).
- **Security**: wajib terautentikasi, `users.is_active` (sudah enforced global/login), profil `Security` terhubung; hanya aset milik unitnya; hanya aset **aktif** yang dapat diinspeksi. Keanggotaan regu **bukan** syarat.
- **Riwayat**: akses baca riwayat tetap tersedia sesuai scope meski aset dinonaktifkan; tidak ada aksi hapus/timpa histori.

## 6. Penyimpanan Foto Inspeksi
- Ikuti dua pola existing dan pilih **konsisten** (rekomendasi: **privat + stream terotorisasi** seperti patroli insiden, untuk meminimalkan eksposur):
  - Publik: disk `public` (`storage/app/public`, URL `/storage`) — pola `Admin/SecurityController` (store/replace/delete).
  - Privat: disk `local` (mis. path tersegmentasi), file **hanya** di-stream oleh controller setelah cek auth/scope/ownership — pola `PatrolSessionOperationService` + endpoint photo app/admin.
- Validasi: maks **5 foto/inspeksi**, batasan tipe/ukuran selaras kebijakan (rujuk pola patrol: image types, size limit) — detail final saat skema/UX ditetapkan. (ASSET-05)

## 7. PDF dan Excel
- **Excel**: `maatwebsite/excel` (v4) + class Export (multi-sheet bila perlu), `Excel::download` dari controller, data read-only & scoped — pola `Admin/PatrolReport`.
- **PDF**: `dompdf/dompdf` + Blade view di `resources/views/...`, respon inline/download; QR/generator via service bila relevan — pola `Admin/PatrolPoint` + `PatrolPointQrCodeService`.
- Ditargetkan pada ASSET-10 (APAR: QR PDF, kartu tahunan, Excel) dan ASSET-15 (Hydrant: QR PDF, Excel).

## 8. Struktur Halaman Vue (implementasi aktual)
- **Admin**: `resources/js/Pages/Admin/Apar/*` dan `.../Hydrant/*` (Index/Create/Edit/Show + riwayat/detail) dengan `Partials` (Filter/Table/Form) dalam `AdminLayout`, `PageHeader`, `FlashMessage`, `Card`, `DataTable`+`Pagination`, komponen form shared, Heroicons outline.
- **App**: `resources/js/Pages/App/Apar/*`, `.../Hydrant/*` dalam `AppLayout` (mobile/bottom slot), komponen `Components/App/*` sesuai kebutuhan alur inspeksi.
- Pola interaksi: `router.get` (filter/sort/paginate, preserve state/scroll), submit inspeksi sebagai aksi create-only, konfirmasi aksi destruktif hanya untuk data non-historis yang diizinkan (bukan histori inspeksi). Konsistensi UI/menu pada ASSET-16.

## 9. Testing (implementasi aktual)
- PHPUnit, sqlite `:memory:`, `RefreshDatabase` (selaras `phpunit.xml` & TestCase existing).
- **ASSET-17**: akses role/area/profile, akun security aktif/non-aktif, scope unit (admin own+descendants, out-of-scope 403, superadmin), aset aktif vs non-aktif, validasi form inspeksi (notes wajib bila tidak baik, maks 5 foto, `is_good`).
- **ASSET-18**: inspeksi membuat **record baru** (timestamp server), histori immutable & tetap tampil saat aset non-aktif, proteksi akses foto, performa query utama, **non-regresi** scheduling/attendance/patrol.
- Jalankan lint/typecheck/test command project saat implementasi (cek README/konfigurasi project).
