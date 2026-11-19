# Progress Pengembangan Modul Asset (APAR & Hydrant)

> **Acuan utama status pengembangan.** Sesi berikutnya wajib membaca dokumen ini untuk mengetahui task terakhir/aktif, blocker, dan pekerjaan selanjutnya.

Status: `NOT STARTED` | `IN PROGRESS` | `BLOCKED` | `COMPLETED`

## TODO / NEXT TASK
- **Task aktif/terakhir selesai:** **ASSET-18 (Final Documentation & Project Closure) — COMPLETED**. Dokumentasi modul Asset telah ditutup berdasarkan implementasi aktual, hasil audit keamanan, dan hasil pengujian yang tersedia.
- **Task sebelumnya:** **ASSET-17 (Final Security Audit & Regression Test APAR & Hydrant) — COMPLETED**. Asset suite **129 tests / 1.250 assertions PASS**; `npm run build` **PASS**; authorization, unit scope, validasi request, integritas riwayat/snapshot, private inspection photo storage, serta PDF/QR/Excel authorization **PASS**.
- **Status fase APAR:** **SELESAI** — **ASSET-06 s.d. ASSET-10 COMPLETED**.
- **Status fase Hydrant:** **SELESAI** — **ASSET-11 s.d. ASSET-15 COMPLETED**.
- **Status fase Integrasi:** **SELESAI** — **ASSET-16 COMPLETED**.
- **Status audit/model:** **ASSET-16A COMPLETED**. Auto-code `APAR-0001`/`HYD-0001`, QR UUID server-side, kode read-only, dan MVP Hydrant hanya `fire_pump_diesel` sudah diterapkan. **Rename kolom Hydrant `hydrant_code`→`code` dan `hydrant_type`→`type` BELUM dilakukan** karena menunggu persetujuan perubahan schema (B-03).
- **Status ASSET-03 (roadmap):** **COMPLETED** secara implementasi internal (ASSET-03A verifikasi sumber, **ASSET-03B** `DATABASE_SCHEMA.md`, **ASSET-03C** 8 migration terverifikasi teknis). **Catatan B-01:** 8 migration acuan pemilik belum tersedia/terverifikasi di repo — selaraskan saat acuan ada (termasuk `is_good`/FK).
- **Pekerjaan yang masih menunggu:** verifikasi file SQL APAR asli (B-01); persetujuan rename kolom Hydrant (B-03); penyesuaian temuan pre-existing di luar modul Asset (67 test gagal pada scheduling/admin/account) sebagai pekerjaan terpisah.

## Roadmap Resmi (19 Task)

### Fase 1 — Persiapan dan fondasi

| Kode | Task | Status | Tujuan | Cakupan pekerjaan (ringkas) | Kriteria selesai (ringkas) |
|---|---|---|---|---|---|
| ASSET-01 | Audit awal | COMPLETED | Memahami kondisi proyek & kesiapan modul | Audit read-only struktur, route/auth/scope, storage/export/test, ketersediaan artefak aset | Temuan, risiko, dan rekomendasi terdokumentasi |
| ASSET-02 | Dokumentasi pengembangan | COMPLETED | Menjadi acuan lintas sesi | Buat/finalisasi `docs/asset/*` & roadmap 19 task | Dokumen tersimpan, konsisten, blocker & next task jelas |
| ASSET-03 | Verifikasi 8 migration acuan | COMPLETED* | Memastikan skema DB acuan valid & dipahami | ASSET-03A verifikasi sumber; **ASSET-03B** schema final `DATABASE_SCHEMA.md`; **ASSET-03C** 8 migration terimplementasi & terverifikasi | Implementasi internal selesai; *selaraskan/verifikasi ulang saat acuan pemilik tersedia (B-01)* |
| ASSET-04 | Model, relasi, authorization, dan unit scoping | COMPLETED | Fondasi domain & akses yang aman | 8 model + relasi Eloquent; concern auth/scoping unit aset selaras pola existing | Model/relasi & otorisasi+scoping sesuai migration/aturan bisnis, siap dipakai controller |
| ASSET-05 | Fondasi penyimpanan foto dan aturan inspeksi | COMPLETED | Fondasi inspeksi konsisten & aman | Private storage helper + aturan inspeksi/checklist reusable, validasi inti & guard eligibility | Mekanisme foto privat & aturan inspeksi tervalidasi |

### Fase 2 — APAR

| Kode | Task | Status | Tujuan | Cakupan pekerjaan (ringkas) | Kriteria selesai (ringkas) |
|---|---|---|---|---|---|
| ASSET-06 | Master APAR Admin | COMPLETED | Admin kelola master APAR dalam scope unit | CRUD/kelola status aktif, list/filter/sort/paginate/detail, scope unit, UI & menu admin | Data APAR dikelola aman dalam scope unit, aset non-aktif tidak dapat diinspeksi, histori tidak berubah |
| ASSET-07 | APAR Security: daftar dan detail | COMPLETED | Security melihat APAR milik unitnya | Daftar & detail APAR aktif (read-only) milik unit sendiri, cari/paginasi, ringkasan inspeksi terakhir, akses backend terotorisasi, UI app | Akses & visibilitas sesuai unit & status aktif; APAR nonaktif/unit lain tidak dapat diakses/diinspeksi |
| ASSET-08 | Inspeksi APAR | COMPLETED | Security mencatat inspeksi APAR | Form/submit inspeksi APAR (aset aktif, unit sendiri), checklist `is_good`, foto (maks 5, privat), notes wajib bila tidak baik, waktu server, snapshot | Setiap submit membuat **record baru** valid; histori tidak diubah/dihapus |
| ASSET-09 | Riwayat dan detail inspeksi APAR | COMPLETED | Riwayat inspeksi APAR dapat ditelusuri | Tampil riwayat & detail inspeksi (admin & security sesuai hak), tetap tersedia saat aset dinonaktifkan | Riwayat lengkap, immutable, dan aksesnya sesuai scope/role |
| ASSET-10 | QR PDF, kartu APAR tahunan, dan Excel export | COMPLETED | Output dokumen & ekspor APAR | QR/PDF, kartu APAR tahunan, export Excel (pola dompdf/maatwebsite/excel existing), scoped & read-only | Dokumen/ekspor dihasilkan benar, aman, dan sesuai scope data |

### Fase 3 — Hydrant

| Kode | Task | Status | Tujuan | Cakupan pekerjaan (ringkas) | Kriteria selesai (ringkas) |
|---|---|---|---|---|---|
| ASSET-11 | Master Hydrant Admin | COMPLETED | Admin kelola master Hydrant dalam scope unit | CRUD/kelola status aktif aset Hydrant, list/filter/sort/paginate/detail sesuai pola admin | Data Hydrant dikelola aman dalam scope unit, aset non-aktif tidak dapat diinspeksi |
| ASSET-12 | Hydrant Security: daftar dan detail | COMPLETED | Security melihat Hydrant milik unitnya | Daftar & detail Hydrant (read-only) milik unit sendiri, akun security aktif/profil valid | Akses & visibilitas sesuai unit & status aktif, UI konsisten AppLayout |
| ASSET-13 | Inspeksi Hydrant | COMPLETED | Security mencatat inspeksi Hydrant | Form/submit inspeksi Hydrant (aset aktif), item `is_good` & checklist per migration, foto (maks 5), notes wajib bila tidak baik, waktu server | Setiap submit membuat **record baru** valid; histori tidak diubah/dihapus |
| ASSET-14 | Riwayat dan detail inspeksi Hydrant | COMPLETED | Riwayat inspeksi Hydrant dapat ditelusuri | Tampil riwayat & detail inspeksi (admin & security sesuai hak), tetap tersedia saat aset dinonaktifkan | Riwayat lengkap, immutable, dan aksesnya sesuai scope/role |
| ASSET-15 | QR PDF dan Excel export Hydrant | COMPLETED | Output dokumen & ekspor Hydrant | QR/PDF dan export Excel (pola existing), scoped & read-only | Dokumen/ekspor dihasilkan benar, aman, dan sesuai scope data |

### Fase 4 — Integrasi

| Kode | Task | Status | Tujuan | Cakupan pekerjaan (ringkas) | Kriteria selesai (ringkas) |
|---|---|---|---|---|---|
| ASSET-16 | Integrasi menu Admin dan Security serta konsistensi UI | COMPLETED | Navigasi & pengalaman pengguna terpadu | Entry menu/route admin & app, konsistensi komponen/layout/Heroicons/Tailwind, alur & feedback selaras pola proyek | Modul mudah diakses, UI konsisten & tanpa regresi UX |

### Fase 5 — Testing

| Kode | Task | Status | Tujuan | Cakupan pekerjaan (ringkas) | Kriteria selesai (ringkas) |
|---|---|---|---|---|---|
| ASSET-17 | Final Security Audit & Regression Test APAR & Hydrant | COMPLETED | Memastikan kontrol akses, validasi, storage, integritas histori, dokumen, dan regresi | Audit authorization/unit scope, validasi request, private photo storage, snapshot/history, PDF/QR/Excel, regression Asset | 129 tests / 1.250 assertions PASS; `npm run build` PASS; temuan B-03 dan B-01 tetap tercatat |
| ASSET-18 | Final Documentation & Project Closure | COMPLETED | Menutup dokumentasi berdasarkan implementasi aktual | Audit & pembaruan `README.md`, `PROGRESS.md`, `ARCHITECTURE.md`, `BUSINESS_RULES.md`, `DATABASE_SCHEMA.md`; catatan hasil ASSET-17 | Dokumentasi modul Asset tertutup sesuai implementasi aktual dan hasil pengujian |

### Fase 6 — Finalisasi

| Kode | Task | Status | Tujuan | Cakupan pekerjaan (ringkas) | Kriteria selesai (ringkas) |
|---|---|---|---|---|---|
| ASSET-19 | Perbaikan temuan, finalisasi dokumentasi, dan laporan selesai | NOT STARTED | Menutup pengembangan dengan rapi | Perbaiki temuan uji/review, perbarui `docs/asset/*`, susun laporan selesai | Temuan kritis tertangani, dokumentasi final akurat |

## Hasil ASSET-03A — Verifikasi sumber & persiapan migration (diperiksa)
- 8 migration acuan pemilik & dokumen schema aset tidak ditemukan di repo saat pemeriksaan. Timestamp terakhir existing `2026_11_15_000011`.

## Hasil ASSET-03B — Rancang dan tetapkan schema database Asset (COMPLETED)
- Spesifikasi final internal 8 tabel di `docs/asset/DATABASE_SCHEMA.md` (ERD, kolom/tipe/nullable/default/enum/index/unique/FK/delete, snapshot, `is_good`, checklist, strategi histori). Wajib diselaraskan dengan acuan pemilik bila tersedia.

## Hasil ASSET-03C — Implementasi 8 migration Asset (COMPLETED)
- 8 migration `2026_11_16_000001`–`000008` sesuai `DATABASE_SCHEMA.md` (FK aset/unit/security inspeksi = RESTRICT; item/foto cascade hanya dari inspeksi induk). `php -l` **PASS**; `migrate --force` SQLite `:memory:` **PASS/DONE**. Tidak ada migrate pada DB existing/dev/prod.

## Hasil ASSET-04 — Model, relasi, authorization, dan unit scoping (COMPLETED)
- **Model (8):** `Apar*`, `Hydrant*` dengan fillable/casts & relasi (aset→unit/inspections; inspeksi→aset,unit,security,items,photos; item/foto→inspection). Security existing `Security->user->unit` (diverifikasi). Tanpa relasi/hapus histori cascade ke schedule/shift/attendance/patrol.
- **Authorization/scoping:** `app/Http/Controllers/Concerns/AuthorizesAssetAccess.php` — superadmin unlimited, admin own+descendants (`Unit::descendantIds()`), security (aktif+profil+unit) own unit; helpers scope/authorize; tidak memakai `Unit::accessibleUnitIds()`.

## Hasil ASSET-05 — Fondasi penyimpanan foto dan aturan inspeksi (COMPLETED)
- **Private storage:** `app/Support/AssetInspectionPhotoStorage.php` — disk `local` (non-publik), path `asset-inspections/{apar|hydrant}/{assetId}/{uuid}.ext`, store/delete terkelola aman.
- **Aturan:** `app/Support/AssetInspectionRules.php` — checklist APAR(10)/Hydrant(11), `resultForItems`, validasi kelengkapan/notes/rules (maks 5 foto), `serverInspectedAt()`, eligibility security & aset aktif/unit, normalisasi item, record-baru/immutable by design.
- **Verifikasi:** `php -l` 3 file **PASS**; `tests/Unit/AssetInspectionFoundationTest.php` **PASS** (9 tests, 37 assertions, SQLite in-memory).

## Hasil ASSET-06 — Master APAR Admin (COMPLETED)
- **Backend/UI:** `app/Http/Controllers/Admin/AparController.php`, route `admin.apar.*`, menu sidebar, halaman `resources/js/Pages/Admin/Apar/*` — CRUD/scope/status (tanpa hard delete), QR UUID tetap saat edit, foto master `apar-photos/*` (public disk) aman, detail ringkasan inspeksi read-only, histori tidak diubah.
- **Verifikasi:** `php -l` **PASS**; `tests/Feature/Admin/AparControllerTest.php` **PASS** (10 tests, 61 assertions, SQLite in-memory).

## Hasil ASSET-07 — APAR Security: Daftar dan Detail (COMPLETED)
- **Backend/UI:** `app/Http/Controllers/App/AparController.php` (index/show), route `app.apar.index/show`, menu bottom nav, halaman `resources/js/Pages/App/Apar/{Index,Show}.vue` — daftar/detail APAR **aktif** milik unit sendiri (cari/paginasi, ringkasan inspeksi terakhir read-only), backend-only scope & akses (security aktif+profil+unit); luar unit/nonaktif → 404; read-only tanpa inspeksi/riwayat penuh.
- **Verifikasi:** `php -l` **PASS**; `tests/Feature/App/AparSecurityTest.php` **PASS** (10 tests, 135 assertions, SQLite in-memory).

## Hasil ASSET-08 — Inspeksi APAR (COMPLETED)
- **Backend:** `app/Http/Controllers/App/AparController.php` — `createInspection`/`storeInspection` (route GET/POST `/app/apar/{id}/inspect` di group app security). Akses: `authorizeSecurityAccess` + aset harus aktif & milik unit scope security (`findInspectableApar`, 404 bila luar unit/nonaktif/tidak ada) + `AssetInspectionRules::canSecurityInspectAsset`.
- **Validasi & aturan:** `AssetInspectionRules::rules('apar')` (items required/size 10, `item_name` in checklist, `is_good` boolean, notes nullable, foto nullable array max 5, image mimes jpg/jpeg/png/webp max 2MB) + `validateChecklistCompleteness` + `resultForItems` (ok jika semua baik, else not_ok) + `validateNotesForResult` (notes wajib jika not_ok). Checklist 10 item & normalisasi sesuai `AssetInspectionRules::aparChecklist()`.
- **Penyimpanan immutable (record baru):** transaksi DB — `AparInspection::create` dengan `inspected_at = AssetInspectionRules::serverInspectedAt()` (waktu server), `unit_id/security_id/apar_id`, hasil, notes, snapshot (unit/apar/type/brand/capacity/serial/placement/expiry); 10 `AparInspectionItem` (nama/label/is_good/note/sort_order); foto inspeksi via `AssetInspectionPhotoStorage::store` (disk `local` privat `asset-inspections/apar/{assetId}/...`) + `AparInspectionPhoto`. Re-check aktif/unit di transaksi; file tersimpan dibersihkan jika exception. Tidak ada update/hapus histori; aset nonaktif tidak bisa diinspeksi.
- **UI:** `resources/js/Pages/App/Apar/Show.vue` (CTA “Mulai Inspeksi”), `resources/js/Pages/App/Apar/CreateInspection.vue` (form checklist Baik/Tidak Baik, catatan item opsional saat tidak baik, catatan inspeksi wajib bila ada item tidak baik, upload foto maks 5, submit forceFormData, AppLayout).
- **Verifikasi (aktual, SQLite in-memory terisolasi):** `php -l` controller/test/routes **PASS**; `php artisan test tests/Feature/App/AparInspectionSubmitTest.php tests/Feature/App/AparSecurityTest.php` **PASS** (20 tests, 207 assertions) — form akses, submit ok (server time/snapshot/10 item), submit berulang = record baru, notes wajib not_ok, >5 foto & checklist tidak lengkap ditolak, foto tersimpan privat & tercatat (bukan public), tolak unit lain/nonaktif/inactive security. Tidak ada run pada DB existing/dev/prod.

## Hasil ASSET-09 — Riwayat dan Detail Inspeksi APAR (COMPLETED)
- **Backend admin:** `app/Http/Controllers/Admin/AparInspectionController.php` — `index`/`show`/`photo`; filter `search`/`result`/`date_from`/`date_to`, urut `inspected_at desc, id desc`, eager load + `withCount('photos')`; superadmin unlimited, admin own+descendants (403 di luar scope); foto di-stream via disk `local` setelah cek scope + guard path terkelola.
- **Backend security:** `app/Http/Controllers/App/AparInspectionController.php` — hanya unit sendiri (404 lintas unit), security aktif+profil, riwayat tetap tersedia walau APAR nonaktif; foto terotorisasi.
- **Route:** `admin.apar.inspections.index` / `apar-inspections.show` / `apar-inspections.photo`; `app.apar.inspections.index/show/photo`.
- **UI:** `resources/js/Pages/Admin/Apar/{Inspections,InspectionShow}.vue`, `resources/js/Pages/App/Apar/{Inspections,InspectionShow}.vue`, tautan riwayat pada `Show.vue` admin & app. Read-only/immutable.
- **Verifikasi:** `php -l` **PASS**; `tests/Feature/Asset/AparInspectionHistoryTest.php` **PASS** (15 tests, 143 assertions); regresi ASSET-05–09 **54 tests, 448 assertions PASS**.

## Hasil ASSET-10 — QR PDF, Kartu APAR Tahunan, dan Excel Export (COMPLETED)
- **QR PDF:** `app/Http/Controllers/Admin/AparDocumentController.php` + `app/Http/Controllers/App/AparDocumentController.php` (`qrPdf`) memakai `PatrolPointQrCodeService::dataUri((string) $apar->qr_code)` — **UUID tersimpan, tidak membuat UUID baru**; template `resources/views/admin/apar/qr-pdf.blade.php` menampilkan kode/jenis/unit/lokasi/UUID; Dompdf A4 `inline`; nama file `qr-apar-{kode}.pdf`.
- **Kartu tahunan:** `app/Support/AparAnnualCardData.php` (read-only builder) + `resources/views/admin/apar/annual-card.blade.php` — A4 portrait, identitas APAR+unit, periode tahun terpilih, 12 bulan Jan–Des, checklist 10 item, tanggal, hasil/catatan, nama petugas, area tanda tangan kosong. Bulan dengan **beberapa inspeksi** memakai `rowspan` dan menampilkan seluruh record (bukan hanya terakhir), termasuk petugas berbeda. Histori diambil dari record inspeksi tersimpan (snapshot item/notes).
- **Excel export:** `app/Exports/AparInspectionExport.php` (`FromQuery`, `WithHeadings`, `WithMapping`, `ShouldAutoSize`, `WithTitle`) — satu baris per inspeksi; kolom identitas/snapshot, unit, petugas, waktu, hasil, notes, dan 10 checklist; filter `apar_id`, `search`, `result`, `date_from`, `date_to`, scope unit; eager load `apar/security/items` (tanpa N+1); nama file memuat periode/tanggal (`riwayat-inspeksi-apar-...xlsx`).
- **Authorization:** seluruh endpoint PDF/Excel divalidasi backend — admin own+descendants (403), superadmin unlimited, security aktif+profil+unit sendiri (404 lintas unit). UI hanya pemicu, bukan kontrol akses.
- **Integritas:** PDF/Excel hanya membaca; feature test memastikan jumlah/`updated_at` inspeksi dan item tidak berubah, termasuk APAR nonaktif.
- **UI:** `Admin/Apar/Show.vue` (QR PDF + kartu tahunan dengan input tahun), `App/Apar/Show.vue` (Dokumen APAR), tombol **Ekspor Excel** pada `Admin/Apar/Inspections.vue` dan `App/Apar/Inspections.vue` (membawa filter aktif).
- **Route:** `admin.apar.qr-pdf` / `apar.annual-card` / `apar.inspections.export`; `app.apar.qr-pdf` / `app.apar.annual-card` / `app.apar.inspections.export`.
- **Verifikasi:** `php -l` controller/support/test **PASS**; `tests/Feature/Asset/AparDocumentOutputTest.php` **PASS** (8 tests, 101 assertions); regresi gabungan ASSET-05–10 **62 tests, 549 assertions PASS**.

## Hasil ASSET-18 — Final Documentation & Project Closure (COMPLETED)
- **Dokumentasi diperbarui:** `docs/asset/README.md`, `docs/asset/PROGRESS.md`, `docs/asset/ARCHITECTURE.md`, `docs/asset/BUSINESS_RULES.md`, dan `docs/asset/DATABASE_SCHEMA.md`.
- **Status akhir:** modul Asset APAR & Hydrant ditutup berdasarkan implementasi aktual dan hasil pengujian. Struktur Hydrant tetap memakai `hydrant_code` dan `hydrant_type`; rename ke `code`/`type` belum dilakukan.
- **Pekerjaan yang masih menunggu:** verifikasi file SQL APAR asli (B-01); persetujuan rename kolom Hydrant (B-03); penyelesaian 67 kegagalan test pre-existing di luar modul Asset sebagai pekerjaan terpisah.
- **Perubahan kode/database:** tidak ada. Hanya dokumentasi yang diperbarui.

## Hasil ASSET-17 — Final Security Audit & Regression Test APAR & Hydrant (COMPLETED)
- **Regression:** Asset suite `129 tests / 1.250 assertions PASS`; `npm run build` **PASS**.
- **Authorization & unit scope:** admin own+descendants, superadmin all, security own unit; manipulasi ID/out-of-scope ditolak; IDOR pada detail/riwayat/foto/export tidak ditemukan.
- **Validasi request:** `unit_id`, kode aset, QR UUID, status aktif, hasil inspeksi, checklist, notes, foto, ID inspeksi, parameter export/PDF divalidasi atau ditentukan backend.
- **Integritas riwayat:** setiap submit membuat record baru; inspeksi lama tidak ditimpa; snapshot tidak berubah saat master diedit/dinonaktifkan; foto master tidak menghapus foto inspeksi historis.
- **Storage:** foto master APAR/Hydrant memakai public managed path; foto inspeksi memakai private disk dan endpoint terotorisasi; path traversal ditolak.
- **Dokumen:** PDF/QR/Excel menerapkan authorization dan unit scope; QR memakai UUID tersimpan; export tidak mengubah database.
- **Temuan yang tetap tercatat:** format kode >9999 dapat melebihi 4 digit (Low); collision scan dilindungi unique constraint DB (Low); B-03 mismatch nama kolom Hydrant (Medium); B-01 file SQL APAR belum diverifikasi (Medium).
- **Full suite:** 575 tests, 495 passed, 67 failed, 4.145 assertions; 67 kegagalan berasal dari test pre-existing di luar modul Asset (scheduling/admin/account), bukan test Asset.

## Hasil ASSET-16A — Audit Model & Pembatasan Jenis Hydrant MVP (COMPLETED, schema rename pending)
- **Audit struktur:** `apars` sudah sesuai target (kolom, nullable, default, enum 5 jenis, unique `apar_code`/`qr_code`, FK `unit_id` restrict). `hydrants` berbeda pada nama kolom target: aktual `hydrant_code`/`hydrant_type`, target `code`/`type`; tipe data/length target belum dapat diverifikasi tanpa SQL asli. Enum Hydrant tetap 6 nilai; unique `hydrant_code`/`qr_code`; FK restrict; nullable/default sesuai target.
- **Auto-code:** APAR `APAR-%04d`, Hydrant `HYD-%04d`. Dibuat setelah insert menggunakan id auto-increment (bukan `max(id)+1`) sehingga aman terhadap race; jika bentrok dengan kode import, suffix dinaikkan sampai unik. Unique constraint DB tetap menjadi pengaman akhir.
- **Kode read-only:** field kode dihapus dari form Create/Edit APAR & Hydrant; backend mengabaikan `apar_code`/`hydrant_code` dari request; edit tidak mengubah kode. UUID QR tetap dibuat server-side saat create dan tidak berubah saat edit.
- **MVP Hydrant:** backend hanya menerima `fire_pump_diesel` untuk create; 5 jenis lain ditolak. Edit tidak mengubah `hydrant_type` sama sekali; data lama dengan jenis lain tetap utuh dan tampil di halaman edit. Dropdown Create mengaktifkan hanya `fire_pump_diesel`; 5 opsi lain disabled dengan label “(belum tersedia)”. Checklist 11 item tidak diubah.
- **Blocker B-03 (perlu persetujuan):** rename kolom master Hydrant `hydrant_code`→`code`, `hydrant_type`→`type` memerlukan migration baru + pembaruan seluruh referensi aplikasi/ekspor/PDF/test; data existing aman (rename in-place), snapshot inspeksi (`hydrant_code_snapshot`, `hydrant_type_snapshot`) tidak boleh diubah. Model Hydrant belum memakai fillable target sampai rename disetujui.
- **Verifikasi:** `php -l` controller/test **PASS**; `AparControllerTest` + `HydrantControllerTest` + `AssetModelTargetAuditTest` **PASS** (25 tests, 201 assertions); regresi seluruh Asset Module **PASS** (129 tests, 1250 assertions); `npm run build` **PASS**.

## Hasil ASSET-16 — Integrasi Menu dan Konsistensi UI (COMPLETED)
- **Menu admin:** Hydrant tersedia di sidebar `resources/js/Components/Admin/AdminSidebar.vue` sebagai **Data Hydrant** (`/admin/hydrant`) berdampingan dengan Data APAR.
- **Menu security:** sesuai keputusan desain, **tidak menambah item bottom navigation**; ditambahkan `resources/js/Components/App/Home/HydrantMenuCard.vue` pada Beranda (`App/Home.vue`) berisi pintasan **Buka Hydrant** ke `/app/hydrant` dengan gaya kartu selaras `PatrolSummary` (Heroicons + Tailwind, feedback tombol).
- **Route & halaman:** seluruh entry route `admin.hydrant.*` dan `app.hydrant.*` terdaftar dan saling terhubung (master, detail, inspeksi, riwayat, foto, QR PDF, export); halaman App tetap memakai `AppLayout` dan halaman Admin memakai komponen/layout admin existing.
- **Verifikasi:** `npm run build` **PASS** (Vite, 1472 modules; warning `fontaine` opsional sudah ada sebelumnya); `php -l` test integrasi **PASS**; `tests/Feature/Asset/HydrantIntegrationTest.php` **PASS** (3 tests, 64 assertions) memverifikasi nama route, akses Security ke Beranda/Hydrant, dan pemisahan portal admin vs security; regresi gabungan Hydrant+APAR+ASSET **PASS** (119 tests, 1155 assertions).

## Hasil ASSET-15 — QR PDF dan Excel Export Hydrant (COMPLETED)
- **QR PDF admin/security:** `app/Http/Controllers/Admin/HydrantDocumentController.php` & `app/Http/Controllers/App/HydrantDocumentController.php` (`qrPdf`), route `admin.hydrant.qr-pdf` / `app.hydrant.qr-pdf`, view `resources/views/admin/hydrant/qr-pdf.blade.php`; memakai `qr_code` UUID tersimpan (tanpa UUID baru), identitas kode/nama/jenis/unit/lokasi, dicetak via Dompdf.
- **Excel export:** `app/Exports/HydrantInspectionExport.php` + `export` pada kedua controller, route `admin.hydrant.inspections.export` / `app.hydrant.inspections.export`; satu baris per inspeksi dengan snapshot Hydrant, unit, petugas, waktu, hasil, catatan, dan 11 item checklist; filter `hydrant_id`, cari, hasil, rentang tanggal.
- **Scoping/keamanan:** admin own+descendants, superadmin all, security unit sendiri; `hydrant_id` di luar scope → 403 (admin) / 404 (security); PDF/Excel read-only.
- **UI:** tombol **QR Code Hydrant (PDF)** pada detail Hydrant admin & security; tombol **Ekspor Excel** pada riwayat inspeksi Hydrant admin & security.
- **Verifikasi:** `php -l` export/controller/routes/test **PASS**; `tests/Feature/Asset/HydrantDocumentOutputTest.php` **PASS** (6 tests, 50 assertions); regresi gabungan Hydrant+APAR+ASSET **PASS** (116 tests, 1091 assertions).

## Hasil ASSET-14 — Riwayat dan Detail Inspeksi Hydrant (COMPLETED)
- **Backend admin:** `app/Http/Controllers/Admin/HydrantInspectionController.php` (index/show/photo) + route `admin.hydrant.inspections.index`, `admin.hydrant-inspections.show/photo`; scope admin/superadmin, filter cari (kode/petugas), hasil, rentang tanggal, pagination 20.
- **Backend security:** `app/Http/Controllers/App/HydrantInspectionController.php` (index/show/photo) + route `app.hydrant.inspections.index/show/photo`; guard akun aktif+profil, hanya unit sendiri; master nonaktif tetap dapat dilihat riwayatnya.
- **Detail:** seluruh snapshot aset (kode, nama, jenis, merek, model, nomor seri, lokasi, maintenance terakhir), unit, petugas, waktu, hasil, catatan, 11 item checklist, foto privat; status aset saat ini ditampilkan terpisah tanpa mengubah histori.
- **Foto:** endpoint privat hanya melayani path terkelola `asset-inspections/`; unit lain → 404; tidak ada endpoint edit/delete histori (405).
- **UI:** `Admin/Hydrant/{Inspections,InspectionShow}.vue`, `App/Hydrant/{Inspections,InspectionShow}.vue`; tautan riwayat ditambahkan pada detail Hydrant admin & security.
- **Verifikasi:** `php -l` kedua controller/routes/test **PASS**; `tests/Feature/Asset/HydrantInspectionHistoryTest.php` **PASS** (15 tests, 143 assertions); regresi gabungan Hydrant+APAR+ASSET **PASS** (110 tests, 1041 assertions).

## Hasil ASSET-13 — Inspeksi Hydrant (COMPLETED)
- **Backend:** `App\Http\Controllers\App\HydrantController` (`createInspection`, `storeInspection`), route `app.hydrant.inspect.create/store`; guard akun security aktif, profil valid, unit sendiri, Hydrant aktif (404 untuk unit lain/nonaktif), validasi `AssetInspectionRules::rules('hydrant')`.
- **Checklist:** 11 item Hydrant (`kondisi_baterai` s.d. `pipa_sambungan`), `is_good` wajib per item, catatan item opsional, `result` dihitung otomatis (`ok`/`not_ok`).
- **Aturan:** catatan inspeksi wajib jika `not_ok`; foto opsional maks 5 (jpg/jpeg/png/webp, 2 MB) disimpan privat di `asset-inspections/hydrant/{id}/`; waktu inspeksi dari server; snapshot unit/aset/last maintenance disimpan pada record inspeksi; setiap submit = record baru.
- **UI:** `resources/js/Pages/App/Hydrant/CreateInspection.vue` + CTA **Mulai Inspeksi** pada `App/Hydrant/Show.vue`; redirect ke detail dengan flash sukses.
- **Verifikasi:** `php -l` controller/routes/test **PASS**; `tests/Feature/App/HydrantInspectionSubmitTest.php` **PASS** (16 tests, 107 assertions, termasuk duplikat/unknown item, boolean, waktu server, foto invalid, rollback transaksi & file yatim); regresi gabungan Hydrant+APAR+ASSET **PASS** (126 tests, 1189 assertions); `npm run build` **PASS**.

## Hasil ASSET-12 — Hydrant Security: Daftar dan Detail (COMPLETED)
- **Backend:** `app/Http/Controllers/App/HydrantController.php` (index/show), route `app.hydrant.*`; guard `role:security` + `security.profile` + pengecekan backend akun aktif, unit, dan profil Security.
- **Daftar:** hanya Hydrant **aktif** pada unit sendiri, pencarian `hydrant_code`/`name`/`brand`/`model`/`placement`, urut kode, pagination 20, scope unit via `AuthorizesAssetAccess`.
- **Detail:** identitas lengkap (kode, nama, jenis, merek, model, nomor seri, tanggal pengadaan/instalasi/maintenance, lokasi, status), foto bila ada, serta ringkasan inspeksi terakhir (waktu, hasil, petugas) — read-only.
- **Keamanan:** Hydrant unit lain atau nonaktif → 404; akun security nonaktif → redirect login; tanpa profil/unit → 403. Tidak ada endpoint tulis pada tahap ini (inspeksi = ASSET-13, riwayat = ASSET-14, dokumen = ASSET-15).
- **UI:** `resources/js/Pages/App/Hydrant/{Index,Show}.vue` dalam `AppLayout` (mobile card, pencarian debounce, state kosong, pagination). Menu/bottom-nav tidak diubah; integrasi menu dilakukan pada ASSET-16 melalui kartu Hydrant di Beranda.
- **Verifikasi:** `php -l` controller/routes/test **PASS**; `tests/Feature/App/HydrantSecurityTest.php` **PASS** (12 tests, 197 assertions, termasuk penolakan admin/superadmin dari portal Security); regresi gabungan Hydrant+APAR+ASSET **PASS** (120 tests, 1159 assertions); `npm run build` **PASS**.

## Hasil ASSET-11 — Master Hydrant Admin (COMPLETED)
- **Backend/UI:** `app/Http/Controllers/Admin/HydrantController.php`, route `admin.hydrant.*`, halaman `resources/js/Pages/Admin/Hydrant/{Index,Create,Edit,Show}.vue` + `Partials/{HydrantFilter,HydrantTable,HydrantForm}.vue`, menu `Data Hydrant` di `AdminSidebar.vue`.
- **Daftar:** pagination, pencarian `hydrant_code`/`name`/`brand`/`placement`, filter status & unit, sort kolom, eager load `unit` + `withCount('inspections')`, scope unit (superadmin all, admin own+descendants).
- **Tambah/Edit:** kolom & enum persis schema (`fire_pump_diesel`, `fire_pump_electric`, `jockey_pump`, `hydrant_box`, `hydrant_pillar`, `siamese_connection`); validasi kode unik, unit dalam scope, enum valid, field nullable sesuai schema, foto JPG/PNG/WebP maks 2 MB. QR UUID dibuat saat create (`Str::uuid()`) dan **tidak diubah saat edit**.
- **Foto:** disimpan di disk `public` `hydrant-photos/`; penggantian/penghapusan hanya untuk path `hydrant-photos/` (path lain tidak disentuh).
- **Detail:** identitas lengkap + unit + foto + status + QR UUID; ringkasan **jumlah inspeksi** dan hasil/waktu inspeksi terakhir (read-only).
- **Aktivasi:** toggle aktif/nonaktif; tanpa hard delete; histori tetap tersedia.
- **Authorization:** role middleware admin/superadmin + `AuthorizesAssetAccess`; security ditolak dari seluruh endpoint master.
- **Verifikasi:** `php -l` controller/routes/test **PASS**; `tests/Feature/Admin/HydrantControllerTest.php` **PASS** (12 tests, 79 assertions); regresi gabungan APAR+ASSET **PASS** (74 tests, 628 assertions).

## Ringkasan Pekerjaan Selesai
- **ASSET-01–ASSET-02:** Audit & dokumentasi/roadmap.
- **ASSET-03A–03C:** Verifikasi sumber, schema final, 8 migration terimplementasi & terverifikasi.
- **ASSET-04:** 8 model, relasi Eloquent, concern authorization & unit scoping aset.
- **ASSET-05:** Fondasi private storage & aturan inspeksi reusable (APAR/Hydrant).
- **ASSET-06:** Master APAR Admin (CRUD/scope/status/UI/menu) + feature tests.
- **ASSET-07:** APAR Security daftar & detail (read-only, scope/akses backend, UI app) + feature tests.
- **ASSET-08:** Inspeksi APAR security (form/submit, checklist, notes, foto privat max 5, server time, immutable record+snapshot) + feature tests.
- **ASSET-09:** Riwayat & detail inspeksi APAR admin/security (filters, scope, foto privat terotorisasi, read-only) + feature tests.
- **ASSET-10:** QR PDF (UUID tersimpan), kartu inspeksi tahunan A4 (semua inspeksi per bulan), Excel export (snapshot/checklist/filter), scoped & read-only + feature tests. **Fase APAR (ASSET-06–10) selesai.**
- **ASSET-11:** Master Hydrant admin (CRUD/scope/status/UI/menu, QR UUID stabil, foto terkelola, ringkasan histori read-only) + feature tests.
- **ASSET-12:** Hydrant security daftar & detail (aktif/unit sendiri, read-only, cari/paginasi, ringkasan inspeksi terakhir, guard backend) + feature tests.
- **ASSET-13:** Inspeksi Hydrant security (form/submit, checklist 11 item, catatan wajib bila tidak baik, foto privat maks 5, waktu server, snapshot, record baru) + feature tests.
- **ASSET-14:** Riwayat & detail inspeksi Hydrant admin/security (filter, scope, foto privat terotorisasi, snapshot immutable, tetap tersedia saat aset nonaktif) + feature tests.
- **ASSET-15:** QR PDF Hydrant (UUID tersimpan) + Excel export (snapshot/checklist/filter), scoped & read-only + feature tests. **Fase Hydrant (ASSET-11–15) selesai.**
- **ASSET-16:** Integrasi menu Hydrant admin (sidebar) & security (kartu Beranda), konsistensi route/UI, build frontend + feature test integrasi.
- **ASSET-16A:** Audit model APAR/Hydrant, auto-code `APAR-%04d`/`HYD-%04d`, kode read-only, QR stabil, MVP Hydrant `fire_pump_diesel` (5 jenis disabled), legacy type preserved; rename kolom Hydrant menunggu approval (B-03).


## File Dibuat atau Diubah
- **ASSET-16A dibuat:** `tests/Feature/Asset/AssetModelTargetAuditTest.php`.
- **ASSET-16A diubah:** `app/Http/Controllers/Admin/AparController.php`, `app/Http/Controllers/Admin/HydrantController.php`, `app/Models/Apar.php`, `resources/js/Pages/Admin/Apar/{Create,Edit}.vue`, `resources/js/Pages/Admin/Apar/Partials/AparForm.vue`, `resources/js/Pages/Admin/Hydrant/{Create,Edit}.vue`, `resources/js/Pages/Admin/Hydrant/Partials/HydrantForm.vue`, `tests/Feature/Admin/{AparControllerTest,HydrantControllerTest}.php`, `docs/asset/PROGRESS.md`.
- **ASSET-16 dibuat:** `resources/js/Components/App/Home/HydrantMenuCard.vue`, `tests/Feature/Asset/HydrantIntegrationTest.php`.
- **ASSET-16 diubah:** `resources/js/Pages/App/Home.vue` (kartu Hydrant), `docs/asset/PROGRESS.md`; verifikasi ulang `resources/js/Components/Admin/AdminSidebar.vue` (Data Hydrant).
- **ASSET-15 dibuat:** `app/Exports/HydrantInspectionExport.php`, `app/Http/Controllers/Admin/HydrantDocumentController.php`, `app/Http/Controllers/App/HydrantDocumentController.php`, `resources/views/admin/hydrant/qr-pdf.blade.php`, `tests/Feature/Asset/HydrantDocumentOutputTest.php`.
- **ASSET-15 diubah:** `routes/web.php` (QR/export admin & app), `resources/js/Pages/Admin/Hydrant/Show.vue` & `resources/js/Pages/App/Hydrant/Show.vue` (QR PDF), `resources/js/Pages/Admin/Hydrant/Inspections.vue` & `resources/js/Pages/App/Hydrant/Inspections.vue` (Ekspor Excel), `docs/asset/PROGRESS.md`.
- **ASSET-14 dibuat:** `app/Http/Controllers/Admin/HydrantInspectionController.php`, `app/Http/Controllers/App/HydrantInspectionController.php`, `resources/js/Pages/Admin/Hydrant/{Inspections,InspectionShow}.vue`, `resources/js/Pages/App/Hydrant/{Inspections,InspectionShow}.vue`, `tests/Feature/Asset/HydrantInspectionHistoryTest.php`.
- **ASSET-14 diubah:** `routes/web.php` (riwayat/detail/foto admin & app), `resources/js/Pages/Admin/Hydrant/Show.vue` & `resources/js/Pages/App/Hydrant/Show.vue` (tautan riwayat), `docs/asset/PROGRESS.md`.
- **ASSET-13 dibuat:** `resources/js/Pages/App/Hydrant/CreateInspection.vue`, `tests/Feature/App/HydrantInspectionSubmitTest.php`.
- **ASSET-13 dilengkapi (verifikasi ulang):** `tests/Feature/App/HydrantInspectionSubmitTest.php` (tambahan test tanpa profil, item duplikat/unknown, boolean, override waktu/unit/security, foto invalid, rollback transaksi + pembersihan file).
- **ASSET-13 diubah:** `app/Http/Controllers/App/HydrantController.php` (form/submit inspeksi), `routes/web.php` (`app.hydrant.inspect.create/store`), `resources/js/Pages/App/Hydrant/Show.vue` (CTA inspeksi), `docs/asset/PROGRESS.md`.
- **ASSET-12 dibuat:** `app/Http/Controllers/App/HydrantController.php`, `resources/js/Pages/App/Hydrant/Index.vue`, `resources/js/Pages/App/Hydrant/Show.vue`, `tests/Feature/App/HydrantSecurityTest.php`.
- **ASSET-12 dilengkapi (verifikasi ulang):** `tests/Feature/App/HydrantSecurityTest.php` (tambahan test admin/superadmin ditolak dari portal Security).
- **ASSET-12 diubah:** `routes/web.php` (`app.hydrant.index/show`), `docs/asset/PROGRESS.md`.
- **ASSET-11 dibuat:** `app/Http/Controllers/Admin/HydrantController.php`, `resources/js/Pages/Admin/Hydrant/{Index,Create,Edit,Show}.vue`, `resources/js/Pages/Admin/Hydrant/Partials/{HydrantFilter,HydrantTable,HydrantForm}.vue`, `tests/Feature/Admin/HydrantControllerTest.php`.
- **ASSET-11 diubah:** `routes/web.php` (`admin.hydrant.*`), `resources/js/Components/Admin/AdminSidebar.vue` (menu Data Hydrant), `docs/asset/PROGRESS.md`.
- **ASSET-10 dibuat:** `app/Support/AparAnnualCardData.php`, `app/Exports/AparInspectionExport.php`, `app/Http/Controllers/Admin/AparDocumentController.php`, `app/Http/Controllers/App/AparDocumentController.php`, `resources/views/admin/apar/qr-pdf.blade.php`, `resources/views/admin/apar/annual-card.blade.php`, `tests/Feature/Asset/AparDocumentOutputTest.php`.
- **ASSET-10 diubah:** `routes/web.php` (route QR/kartu/export admin & app), `resources/js/Pages/Admin/Apar/Show.vue` (QR + kartu tahunan), `resources/js/Pages/App/Apar/Show.vue` (Dokumen APAR), `resources/js/Pages/Admin/Apar/Inspections.vue` & `resources/js/Pages/App/Apar/Inspections.vue` (Ekspor Excel), `docs/asset/PROGRESS.md`.
- **ASSET-09 dibuat:** `app/Http/Controllers/Admin/AparInspectionController.php`, `app/Http/Controllers/App/AparInspectionController.php`, `resources/js/Pages/Admin/Apar/{Inspections,InspectionShow}.vue`, `resources/js/Pages/App/Apar/{Inspections,InspectionShow}.vue`, `tests/Feature/Asset/AparInspectionHistoryTest.php`.
- **ASSET-09 diubah:** `routes/web.php` (route riwayat/detail/foto admin & app), `resources/js/Pages/Admin/Apar/Show.vue` & `resources/js/Pages/App/Apar/Show.vue` (tautan riwayat).
- **ASSET-08 dibuat:** `resources/js/Pages/App/Apar/CreateInspection.vue`, `tests/Feature/App/AparInspectionSubmitTest.php`.
- **ASSET-08 diubah:** `app/Http/Controllers/App/AparController.php` (form/submit inspeksi), `routes/web.php` (route `app.apar.inspect.create/store`), `resources/js/Pages/App/Apar/Show.vue` (CTA inspeksi), `docs/asset/PROGRESS.md`.
- **ASSET-07 dibuat:** `app/Http/Controllers/App/AparController.php` (base index/show), `resources/js/Pages/App/Apar/Index.vue`, `resources/js/Pages/App/Apar/Show.vue` (base), `tests/Feature/App/AparSecurityTest.php`.
- **ASSET-07 diubah:** `routes/web.php` (route index/show), `resources/js/Components/App/Home/BottomNavigation.vue`.
- **ASSET-06 dibuat:** `app/Http/Controllers/Admin/AparController.php`, `resources/js/Pages/Admin/Apar/{Index,Create,Edit,Show}.vue`, `resources/js/Pages/Admin/Apar/Partials/{AparFilter,AparTable,AparForm}.vue`, `tests/Feature/Admin/AparControllerTest.php`.
- **ASSET-06 diubah:** `routes/web.php` (`admin.apar.*`), `resources/js/Components/Admin/AdminSidebar.vue`.
- **ASSET-05 dibuat:** `app/Support/AssetInspectionPhotoStorage.php`, `app/Support/AssetInspectionRules.php`, `tests/Unit/AssetInspectionFoundationTest.php`.
- **ASSET-04 dibuat:** `app/Models/{Apar,AparInspection,AparInspectionItem,AparInspectionPhoto,Hydrant,HydrantInspection,HydrantInspectionItem,HydrantInspectionPhoto}.php`, `app/Http/Controllers/Concerns/AuthorizesAssetAccess.php`.
- **ASSET-03C dibuat:** 8 migration `database/migrations/2026_11_16_000001`–`000008_create_*_table.php`.
- **Dokumentasi:** `docs/asset/DATABASE_SCHEMA.md`, `docs/asset/PROGRESS.md`.
- **Tidak diubah:** migration & model ASSET-03C/04 (skema), fondasi ASSET-05, fitur APAR Admin ASSET-06, modul scheduling/attendance/patrol, master data existing, database existing/dev/prod. Tidak ada Git/perintah destructive.

## Hasil Pengujian
- **ASSET-03C:** `php -l` 8 migration **PASS**; `migrate --force` SQLite `:memory:` **PASS/DONE**.
- **ASSET-04:** `php -l` 9 file **PASS**; `php artisan test --testsuite=Unit` **PASS** (5 tests, 13 assertions).
- **ASSET-05:** `php -l` 3 file **PASS**; `tests/Unit/AssetInspectionFoundationTest.php` **PASS** (9 tests, 37 assertions).
- **ASSET-06:** `php -l` controller/route/test **PASS**; `tests/Feature/Admin/AparControllerTest.php` **PASS** (10 tests, 61 assertions).
- **ASSET-07:** `php -l` controller/test **PASS**; `tests/Feature/App/AparSecurityTest.php` **PASS** (10 tests, 135 assertions).
- **ASSET-08:** `php -l` controller/test/routes **PASS**; `tests/Feature/App/AparInspectionSubmitTest.php tests/Feature/App/AparSecurityTest.php` **PASS** (20 tests, 207 assertions).
- **ASSET-09:** `php -l` controller/test/routes **PASS**; `tests/Feature/Asset/AparInspectionHistoryTest.php` **PASS** (15 tests, 143 assertions); regresi ASSET-05–09 **PASS** (54 tests, 448 assertions).
- **ASSET-10:** `php -l` support/controller/test **PASS**; `tests/Feature/Asset/AparDocumentOutputTest.php` **PASS** (8 tests, 101 assertions); regresi gabungan ASSET-05–10 **PASS** (62 tests, 549 assertions). Verifikasi route QR/kartu/export admin & app via `routes/web.php`.
- **ASSET-11:** `php -l` controller/routes/test **PASS**; `tests/Feature/Admin/HydrantControllerTest.php` **PASS** (12 tests, 79 assertions); regresi gabungan APAR+ASSET **PASS** (74 tests, 628 assertions). Verifikasi route `admin.hydrant.*` via `routes/web.php`.
- **ASSET-12:** `php -l` controller/routes/test **PASS**; `tests/Feature/App/HydrantSecurityTest.php` **PASS** (12 tests, 197 assertions); regresi gabungan Hydrant+APAR+ASSET **PASS** (120 tests, 1159 assertions); `npm run build` **PASS**. Verifikasi route `app.hydrant.*` via `routes/web.php`.
- **ASSET-13:** `php -l` controller/routes/test **PASS**; `tests/Feature/App/HydrantInspectionSubmitTest.php` **PASS** (16 tests, 107 assertions); regresi gabungan Hydrant+APAR+ASSET **PASS** (126 tests, 1189 assertions); `npm run build` **PASS**. Verifikasi route `app.hydrant.inspect.*` via `routes/web.php`.
- **ASSET-14:** `php -l` controller/routes/test **PASS**; `tests/Feature/Asset/HydrantInspectionHistoryTest.php` **PASS** (15 tests, 143 assertions); regresi gabungan Hydrant+APAR+ASSET **PASS** (110 tests, 1041 assertions). Verifikasi route riwayat/detail/foto admin & app via `routes/web.php`.
- **ASSET-15:** `php -l` export/controller/routes/test **PASS**; `tests/Feature/Asset/HydrantDocumentOutputTest.php` **PASS** (6 tests, 50 assertions); regresi gabungan Hydrant+APAR+ASSET **PASS** (116 tests, 1091 assertions). Verifikasi route QR/export admin & app via `routes/web.php`.
- **ASSET-16:** `npm run build` **PASS** (Vite, 1472 modules); `php -l` test integrasi **PASS**; `tests/Feature/Asset/HydrantIntegrationTest.php` **PASS** (3 tests, 64 assertions); regresi gabungan Hydrant+APAR+ASSET **PASS** (119 tests, 1155 assertions).
- **ASSET-16A:** `php -l` controller/model/test **PASS**; `AparControllerTest` + `HydrantControllerTest` + `AssetModelTargetAuditTest` **PASS** (25 tests, 201 assertions); regresi seluruh Asset Module **PASS** (129 tests, 1250 assertions); `npm run build` **PASS**.
- Semua pengujian dijalankan terisolasi (SQLite in-memory, APP_ENV testing); tidak ada test/run pada DB existing/dev/prod.

## Kendala / Blocker
| ID | Status | Kendala | Dampak / Tindakan |
|---|---|---|---|
| B-01 | OPEN (catatan) | 8 migration acuan pemilik belum tersedia/terverifikasi di repo. | `is_good`/definisi acuan tidak boleh diasumsikan berbeda; implementasi internal ada. **Tindakan:** selaraskan/verifikasi saat acuan tersedia. |
| B-02 | OPEN (catatan) | `Unit::accessibleUnitIds()` tidak tersedia; scoping tersebar di modul lain. | Concern aset menggunakan `Unit::descendantIds()` + pola existing; pertahankan konsistensi. |
| B-03 | OPEN (perlu approval) | Target model Hydrant memakai `code`/`type`, migration aktual `hydrant_code`/`hydrant_type`. | Rename kolom + seluruh referensi (model/controller/Vue/export/PDF/test) belum dilakukan agar aplikasi tidak rusak. Data existing aman dengan rename in-place; snapshot inspeksi tidak diubah. **Tindakan:** setujui migration rename sebelum import SQL. |

## Catatan Temuan Audit yang Harus Dijaga
- Unit: hierarki `parent_id`, `unit_level` (`uid,up,ulp`), `Unit::descendantIds()`. `Unit::accessibleUnitIds()` **belum ada**.
- User `is_active` enforced; Security via `Security -> User -> Unit`; **keanggotaan regu bukan syarat inspeksi**. Security akses/inspeksi aset hanya unit sendiri (aktif+profil, aset aktif); admin own+descendants; superadmin unlimited.
- Histori inspeksi immutable (record baru), tetap dapat diakses meski aset dinonaktifkan; hindari mekanisme hapus histori. Modul scheduling/attendance/patrol **jangan diubah**.
