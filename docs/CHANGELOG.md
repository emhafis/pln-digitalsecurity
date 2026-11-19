# Log Perubahan Proyek

Catatan perubahan pada proyek **pln-digtyaceh** (Laravel + Inertia + Vue + Tailwind), diurutkan dari awal instalasi.

---

## 1. Kondisi Awal

- Proyek skeleton Laravel 13 (PHP 8.3) dengan Tailwind CSS 4 + Vite 8 sudah tersedia.
- Belum ada Inertia, Vue, maupun autentikasi.

## 2. Instalasi Dependensi

### Composer

| Perintah | Keterangan |
| --- | --- |
| `composer require laravel/boost --dev` | Laravel Boost (guidelines agent AI) — **kemudian diuninstall** (lihat bagian 9) |
| `composer require inertiajs/inertia-laravel` | Adapter server-side Inertia v3 |

### npm

| Paket | Keterangan |
| --- | --- |
| `vue` | Vue 3 |
| `@inertiajs/vue3` | Adapter client-side Inertia |
| `@vitejs/plugin-vue` | Plugin Vite untuk SFC Vue |

## 3. Konfigurasi Server-Side (Inertia)

- Menjalankan `php artisan inertia:middleware` → membuat `app/Http/Middleware/HandleInertiaRequests.php`.
- Mendaftarkan middleware ke grup `web` di `bootstrap/app.php` (append `HandleInertiaRequests`).
- Membuat root view `resources/views/app.blade.php` (memakai `@vite` + `<x-inertia::head />` + `<x-inertia::app />`).
- Mengisi shared props di `HandleInertiaRequests::share()`:
  - `appName`
  - `auth.user` (`id`, `name`, `email`, `role`)
  - `flash.success` / `flash.error`

## 4. Konfigurasi Client-Side (Vue)

- `vite.config.js`:
  - Menambahkan plugin `@vitejs/plugin-vue`.
  - Menambahkan alias `@` → `resources/js`.
- `resources/js/app.js`:
  - `createInertiaApp` dengan resolver halaman `./Pages/**/*.vue`.
  - Title format `{title} - {appName}`.

## 5. Autentikasi & Role

- Migrasi `2026_11_02_162030_add_role_to_users_table.php`:
  - Menambah kolom `users.role` (default `'user'`).
- `app/Models/User.php`:
  - `role` ditambahkan ke `Fillable` dan `casts`.
- `database/factories/UserFactory.php`:
  - Default `role => 'user'`.
- `app/Http/Middleware/EnsureUserRole.php`:
  - Middleware `role:...` untuk membatasi akses per peran.
  - Didaftarkan sebagai alias `role` di `bootstrap/app.php`.
- `lang/en/auth.php`:
  - Pesan gagal login (`auth.failed`).

## 6. Controller

| File | Fungsi |
| --- | --- |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Login (GET/POST) + logout. **Tanpa register.** Redirect: `admin` → `/admin`, selainnya → `/app` |
| `app/Http/Controllers/Public/HomeController.php` | Landing publik (no auth) |
| `app/Http/Controllers/Admin/DashboardController.php` | Dashboard admin (role `admin`) |
| `app/Http/Controllers/App/DashboardController.php` | Dashboard app mobile-friendly (auth saja) |

## 7. Layout (4 Interface)

Dibuat di `resources/js/Layouts/`:

| Layout | File | Kegunaan |
| --- | --- | --- |
| 1. Dashboard Admin | `AdminLayout.vue` | Sidebar desktop + hamburger di mobile, topbar, area konten utama |
| 2. App (Mobile Friendly) | `AppLayout.vue` | Header sticky, konten `max-w-lg`, bottom tab navigation + `safe-area-inset` |
| 3. Guest (Login) | `GuestLayout.vue` | Halaman tengah (centered card) khusus login — **tanpa link register** |
| 4. Public (No Auth) | `PublicLayout.vue` | Navbar + footer publik; tombol Login untuk guest, tautan Dashboard untuk user login |

## 8. Halaman & Route

### Halaman (`resources/js/Pages/`)

- `Public/Home.vue` — landing page (layout Public)
- `Auth/Login.vue` — form login (layout Guest, `useForm` Inertia)
- `Admin/Dashboard.vue` — statistik & aktivitas (layout Admin)
- `App/Dashboard.vue` — aksi cepat, tagihan, info (layout App)

### Route (`routes/web.php`)

| Method | URI | Middleware | Nama |
| --- | --- | --- | --- |
| GET | `/` | — | `home` |
| GET/POST | `/login` | `guest` | `login` / `login.store` |
| POST | `/logout` | `auth` | `logout` |
| GET | `/admin` | `auth`, `role:admin` | `admin.dashboard` |
| GET | `/app` | `auth` | `app.dashboard` |

## 9. Database

- `php artisan migrate:fresh --seed`
- Seeder `DatabaseSeeder` membuat 2 akun:
  - `admin@example.com` / `password` (role: `admin`)
  - `test@example.com` / `password` (role: `user`)

## 10. Perbaikan Bug

- **Namespace salah**: `AuthenticatedSessionController` semula memakai namespace `App\Http\Controllers` padahal file berada di folder `Auth/` → memicu error 500 (`Class "App\Http\Controllers\Auth\Controller" not found`).
  - Diperbaiki menjadi `namespace App\Http\Controllers\Auth;` + import `App\Http\Controllers.Controller`.

## 11. Verifikasi

- `vendor/bin/pint` → **passed**
- `npm run build` → sukses
- Smoke test (server `php artisan serve`):
  - `GET /` → 200 (Public)
  - `GET /login` → 200 (Guest)
  - `GET /admin` (guest) → 302 → `/login`
  - `GET /app` (guest) → 302 → `/login`
  - Login `admin@example.com` → 302 → `/admin` → 200 (Inertia `data-page` ada)
  - Login `test@example.com` → 302 → `/app` → 200; akses `/admin` → 403
  - `POST /logout` → 302 → `/login`; sesi ter-invalidasi

## 12. Pembersihan (Uninstall Boost & File Agent AI)

Dilakukan agar proyek bersih dari konfigurasi agent AI:

### Composer

- `composer remove laravel/boost --dev`
- Package yang ikut terhapus: `laravel/boost`, `laravel/mcp`, `laravel/roster`, `composer/semver`, `symfony/yaml`
- `composer.json` / `composer.lock` kembali bersih (tanpa referensi boost)

### File/direktori yang dihapus

| Path | Asal |
| --- | --- |
| `AGENTS.md` | Boost (`boost:install`) |
| `CLAUDE.md` | Boost (`boost:install`) |
| `boost.json` | Boost (konfigurasi agent/skill) |
| `opencode.json` | Boost (MCP `laravel-boost` untuk OpenCode) |
| `.mcp.json` | Boost (MCP server config) |
| `.agents/` | Boost (skills + `mcp_config.json`) |
| `.claude/` | Boost (skills untuk Claude Code) |

### Pemeriksaan akhir

- Tidak ada lagi file `AGENTS.md`, `CLAUDE.md`, `boost.json`, `opencode.json`, `.mcp.json`, `.agents/`, `.claude/`.
- `composer.json`, `package.json`, `.gitignore` tidak menyebut boost/agent.
- `php artisan --version`, `pint`, dan `npm run build` tetap berjalan normal.

## 13. Pindah Database ke MySQL

- `.env` diubah dari SQLite ke MySQL:
  ```
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=pln_digtyaceh_db
  DB_USERNAME=root
  DB_PASSWORD=
  ```
- Menjalankan `php artisan migrate:fresh --seed` ke MySQL `pln_digtyaceh_db` — semua migrasi sukses (users, cache, jobs, add_role_to_users).
- Seeder membuat 2 user: `admin@example.com` (role admin), `test@example.com` (role user).
- Verifikasi login via MySQL: login admin → `/admin` 200, akses `/app` 200.

## 14. Design System & Redesign Dashboard Admin

### Dokumen

- Menambahkan `Docs/DESIGN_SYSTEM.md` — panduan konsistensi frontend (warna, tipografi, spacing, komponen, struktur folder). Referensi gaya: SiteMinder (clean SaaS).

### Komponen admin baru (`resources/js/Components/Admin/`)

| File | Fungsi |
| --- | --- |
| `AdminSidebar.vue` | Sidebar light (white), section **Data Master**, indikator aktif biru |
| `AdminTopbar.vue` | Header sticky: hamburger, judul, notifikasi, dropdown user |
| `PageHeader.vue` | Judul + deskripsi + slot `actions` |
| `StatCard.vue` | Kartu metrik (label, value, trend, ikon) |
| `Card.vue` | Panel putih + header opsional |
| `DataTable.vue` | Tabel standar + empty state |
| `Badge.vue` | Status: gray/blue/emerald/amber/red |
| `Button.vue` | primary/secondary/ghost/danger |
| `SearchInput.vue` | Input pencarian dengan ikon |
| `EmptyState.vue` | Placeholder konten kosong |

### Refactor

- `AdminLayout.vue` — hanya compose `AdminSidebar` + `AdminTopbar` + slot (best practice: layout tipis, komponen terpisah).
- Aksen berpindah `sky-*` → `blue-600` (brand biru konsisten).
- Sidebar: background putih, border `gray-200` (bukan sidebar gelap).

### Sidebar menu (sementara)

```
Dashboard           /admin
── DATA MASTER ──
Unit                /admin/master/unit
Pos Jaga            /admin/master/pos-jaga
Titik Patroli       /admin/master/titik-patroli
Satpam              /admin/master/satpam
User                /admin/master/user
```

### Halaman & route

- `Admin/Master/{Unit,PosJaga,TitikPatroli,Satpam,User}.vue` — **tampilan dulu** (PageHeader + toolbar + tabel contoh), tanpa CRUD aktif.
- `App\Http\Controllers\Admin\MasterController` — 5 method render Inertia.
- Route prefix `admin/master` dengan middleware `auth` + `role:admin`.
- `Admin/Dashboard` didesain ulang: PageHeader, 4 StatCard, ringkasan Data Master, distribusi peran, tabel aktivitas.

### Verifikasi

- `vendor/bin/pint` → passed (perbaiki unused import di MasterController).
- `npm run build` → sukses.
- Smoke test login admin:
  - `/admin` → 200
  - `/admin/master/unit` → 200
  - `/admin/master/pos-jaga` → 200
  - `/admin/master/titik-patroli` → 200
  - `/admin/master/satpam` → 200
  - `/admin/master/user` → 200

## 15. Ganti Font ke Plus Jakarta Sans

- `vite.config.js`: `bunny('Instrument Sans')` → `bunny('Plus Jakarta Sans', { weights: [400, 500, 600, 700] })`
- `resources/css/app.css`: `--font-sans: 'Plus Jakarta Sans', ...`
- `Docs/DESIGN_SYSTEM.md` diperbarui.
- Build ulang: file `plus-jakarta-sans-400/500/600/700` masuk `public/build/assets`, CSS output memakai `"Plus Jakarta Sans"`.

## 16. Ikon: Heroicons

- Install `npm i @heroicons/vue` (v2.2.0, outline 24px).
- Semua inline SVG (`viewBox="0 0 24 24"`) diganti komponen Heroicons:

| File | Ikon |
| --- | --- |
| `AdminSidebar.vue` | `XMarkIcon`, `HomeIcon`, `BuildingOffice2Icon`, `ShieldCheckIcon`, `MapPinIcon`, `UserGroupIcon`, `UsersIcon` |
| `AdminTopbar.vue` | `Bars3Icon`, `BellIcon`, `ChevronDownIcon` |
| `StatCard.vue` | `ArrowTrendingUpIcon` / `ArrowTrendingDownIcon`; prop `icon` kini berupa komponen |
| `SearchInput.vue` | `MagnifyingGlassIcon` |
| `DataTable.vue`, `EmptyState.vue` | `ArchiveBoxIcon` |
| `AppLayout.vue` | `HomeIcon`, `Squares2X2Icon`, `ClockIcon`, `UserIcon` |
| `Pages/App/Dashboard.vue` | `BanknotesIcon`, `BoltIcon`, `ClockIcon`, `ExclamationTriangleIcon` |
| `Pages/Public/Home.vue` | `ChartBarSquareIcon`, `DevicePhoneMobileIcon`, `ShieldCheckIcon` |
| `Pages/Admin/Dashboard.vue` | prop `icon` StatCard diisi komponen Heroicons |

- Nav dinamis memakai `<component :is="item.icon" />` dengan icon berupa referensi komponen.
- `Login.vue` spinner tetap SVG custom (Heroicons tidak punya spinner).
- `Docs/DESIGN_SYSTEM.md` bagian Ikon diperbarui.

### Verifikasi

- `npm run build` → sukses (perbaiki nama `Squares2X2Icon`, `GaugeIcon` → `BoltIcon`).
- `vendor/bin/pint` → passed.
- Smoke test: `/`, `/admin`, `/admin/master/unit` → 200.

## 17. Scoping Unit pada Post Keamanan (descendantIds)

- **Satu-satunya file kode yang diubah:** `app/Http/Controllers/Admin/SecurityPostController.php` (+ catatan ini di `docs/CHANGELOG.md`). Tabel `security_posts`, model, route, middleware, sidebar, halaman Vue, dan controller lain **tidak diubah**.
- Aturan scope (berbasis `Unit::descendantIds()`):
  - **`superadmin`** (tanpa unit) → `null` (tanpa batas) → lihat/kelola **semua** data.
  - **`security` / `admin`** dengan `unit_id` → daftar ID unit milik user + seluruh unit turunannya (`descendantIds()`).
  - **`security` / `admin` tanpa `unit_id`** (atau role lain) → scope kosong `[]` → tidak ada data yang bisa diakses.
- Perubahan di dalam controller:
  - Helper baru `unitScope(Request): ?array` (`null` = semua, `array` = ID yang diizinkan).
  - `index`: filter `whereIn('security_posts.unit_id', $scope)` saat scope aktif (tetap aman saat sort-by-unit join).
  - `create` / `edit`: opsi dropdown unit (`units()`) dibatasi ke scope; superadmin tetap melihat semua unit.
  - `store` / `update`: validasi `unit_id` → superadmin `exists:units,id`; user scoped `Rule::in($scope)` (ditambah `required`).
  - `show` / `edit` / `update` / `destroy`: `abort(403)` jika post berada di luar scope user.

### Verifikasi

- `vendor/bin/pint` → passed.
- `php artisan route:list --name=security-post` → 7 route tetap terpasang.
- Tanpa pengujian (sesuai permintaan).

## 18. Filter Unit pada Index Post Keamanan (SearchSelect)

- **File yang diubah:**
  - `app/Http/Controllers/Admin/SecurityPostController.php` (hanya `index` + helper baru `resolveFilterUnitId()`).
  - `resources/js/Pages/Admin/SecurityPost/Index.vue`.
  - `resources/js/Pages/Admin/SecurityPost/Partials/SecurityPostFilter.vue`.
  - `docs/CHANGELOG.md` (catatan ini).
- Komponen `SearchSelect` yang sudah ada dipakai ulang (tidak diubah).
- Perilaku filter `unit_id` (scope-safe, di atas batas `unitScope`):
  - **Muat pertama** (tanpa param `unit_id`): user `security`/`admin` → default **unit miliknya sendiri**; `superadmin` → “Semua Unit” (semua data).
  - **“Semua Unit”** (param `unit_id` kosong eksplisit) → tanpa filter unit tambahan, tetap dibatasi scope (`descendantIds()`).
  - **Unit terpilih** → `where(security_posts.unit_id, ...)`; nilai di luar scope / tidak valid **diabaikan** (fallback semua dalam scope).
- UI: `SearchSelect` unit di samping `SearchInput` pada `SecurityPostFilter` (placeholder & reset “Semua Unit”, opsi = unit dalam scope user); state `unit_id` ikut dalam query (search/sort/paginasi tetap).

### Verifikasi

- `vendor/bin/pint` → passed.
- `npm run build` → sukses.
- Tanpa pengujian suite (sesuai permintaan sebelumnya).

## 19. Menu Titik Patroli (CRUD + Scope Unit)

- **Migrasi** `2026_11_07_144702_create_patrol_points_table.php` **tidak diubah**; dijalankan `php artisan migrate` (tabel `patrol_points` + FK `security_post_id` → `security_posts.id` on delete restrict).
- **File baru:**
  - `app/Models/PatrolPoint.php` — `fillable` (`qr_code,name,security_post_id`), hook `created` mengisi `qr_code` UUID bila null, relasi `securityPost()`.
  - `app/Http/Controllers/Admin/PatrolPointController.php` — CRUD Inertia, scope unit via `securityPost.unit_id` (`Unit::descendantIds()`), filter unit pada index (default unit user saat muat pertama; “Semua Unit” = semua dalam scope), search (nama/QR/nama pos), sort (nama/pos/unit/dibuat), paginate 50.
  - `resources/js/Pages/Admin/PatrolPoint/{Index,Create,Edit,Show}.vue` + `Partials/{PatrolPointFilter,PatrolPointForm,PatrolPointTable}.vue` (pola menu Security Post; filter Unit `SearchSelect`).
- **Diubah:**
  - `routes/web.php` — tambah route `admin.patrol-point.*` (`/admin/patrol-point`).
  - `resources/js/Components/Admin/AdminSidebar.vue` — “Data Titik Patroli” `#` → `/admin/patrol-point` (item itu saja).
- Catatan: karena kolom `qr_code` NOT NULL (sesuai migrasi), controller mengisi `qr_code` (UUID) saat `store`; hook model tetap dipertahankan sesuai yang diminta.
- **Tidak diubah:** migrasi, tabel, model/controller modul lain, komponen shared.

### Verifikasi

- `php artisan migrate` → sukses (`create_patrol_points_table`).
- `vendor/bin/pint` → passed.
- `npm run build` → sukses.
- `php artisan route:list --name=patrol-point` → 7 route.
- Tanpa pengujian suite (sesuai permintaan).

## 20. Menu Personel Security (CRUD + Auto User + Upload KTA)

- **Migrasi** `2026_11_07_162323_create_securities_table.php` **tidak diubah**; dijalankan `php artisan migrate` (tabel `securities` + FK `user_id` → `users.id` unique/restrict).
- **File baru:**
  - `app/Models/Security.php` — `fillable` seluruh kolom, casts tanggal, relasi `user()`, auto `registration_number` = `SEC{id}` (pola model lain).
  - `app/Http/Controllers/Admin/SecurityController.php` — CRUD Inertia; scope unit via `user.unit_id` (`descendantIds()`); filter Unit index (default unit user saat muat pertama, “Semua Unit” dalam scope); **create tanpa pilihan user**: otomatis membuat `User` (`username` dari `generateUsername(full_name)`, password `satpam`, role `security`, unit = unit pembuat / pilihan superadmin); upload `photo` & `kta_file` ke disk `public` folder `KTA/` dengan nama `kta_number + full_name` (sanitasi, fallback `registration_number`), hanya gambar maks 1 MB; hapus file saat ganti/hapus; edit menyinkronkan `name`/`unit_id` user.
  - `resources/js/Components/Admin/FileInput.vue` — komponen upload reusable (preview gambar, file tersimpan, batalkan pilihan).
  - `resources/js/Pages/Admin/Security/{Index,Create,Edit,Show}.vue` + `Partials/{SecurityFilter,SecurityForm,SecurityTable}.vue` (pola menu Security Post).
- **Diubah:**
  - `routes/web.php` — route `admin.security.*` (`/admin/security`).
  - `resources/js/Components/Admin/AdminSidebar.vue` — menu **Data Personel Security** → `/admin/security` (Data Master).
- Catatan: menghapus personel juga menghapus akun `User` terkait dan file `photo`/`kta_file`; `php artisan storage:link` dibuat agar file `public` dapat diakses.
- **Tidak diubah:** migrasi, tabel, modul SecurityPost/PatrolPoint/Unit/User lainnya, komponen shared lain.

### Verifikasi

- `php artisan migrate` → sukses (`create_securities_table`).
- `php artisan storage:link` → sukses.
- `vendor/bin/pint` → passed (file baru).
- `npm run build` → sukses.
- `php artisan route:list --name=security.` → 7 route.
- Tanpa pengujian suite (sesuai permintaan).

## 21. Modul Absensi Satpam (Schema, Generator, Aktivitas, Koreksi, UI)

- **Migrasi baru:**
  - `2026_11_11_000001_create_attendance_settings_table.php` — pengaturan global absensi.
  - `2026_11_11_000002_create_attendance_records_table.php` — snapshot absensi per jadwal (`schedule_id` unik).
  - `2026_11_11_000003_create_attendance_corrections_table.php` — riwayat pengajuan/keputusan koreksi.
  - `2026_11_11_000004_create_attendance_generation_anomalies_table.php` — data bermasalah per jadwal.
- **Model baru:** `AttendanceSetting`, `AttendanceRecord`, `AttendanceCorrection`, `AttendanceGenerationAnomaly`.
- **Support:** `AttendanceClock` (konversi WIB <-> zona penyimpanan, waktu jadwal), `GeoDistance` (Haversine).
- **Service baru:**
  - `AttendanceReadinessChecker` — validasi pengaturan global dan kesiapan per periode.
  - `AttendanceGenerator` — pembuatan/sinkronisasi record idempoten per periode (all-or-nothing) + anomaly.
  - `AttendanceStatusEvaluator` — penetapan Tidak Hadir setelah batas check-out.
  - `AttendanceActivityService` — check-in/check-out dengan validasi jendela waktu, GPS, radius, dan akurasi.
  - `AttendanceCorrectionService` — pengajuan, persetujuan, penolakan koreksi (append-only).
- **Controller baru:** `Admin\AttendanceSettingController`, `Admin\AttendanceReadinessController`, `Admin\AttendanceGenerationController`, `Admin\AttendanceRecordController`, `Admin\AttendanceCorrectionController`, `App\AttendanceController`, trait `Concerns\ResolvesAttendanceScope`.
- **Command baru:** `attendance:readiness`, `attendance:generate`, `attendance:evaluate-absent`.
- **Scheduler:** `routes/console.php` menjalankan `attendance:generate` dan `attendance:evaluate-absent` setiap 15 menit dengan `withoutOverlapping()`.
- **Route baru (13):** kesiapan, generate, pengaturan, daftar/detail record, koreksi admin, dan halaman/aksi Satpam (`/app/attendance`).
- **Halaman Vue baru:**
  - `Admin/Attendance/Readiness.vue`
  - `Admin/Attendance/Records/Index.vue`, `Show.vue`
  - `Admin/Attendance/Corrections/Index.vue`
  - `Admin/AttendanceSetting/Edit.vue`
  - `App/Attendance/Index.vue`
- **Diubah:**
  - `resources/js/Components/Admin/AdminSidebar.vue` — bagian menu **Absensi**; perbaikan tag penutup yang menyebabkan build gagal.
  - `resources/js/Layouts/AppLayout.vue` — tab Absensi mengarah ke `/app/attendance`.
- **Perbaikan:** `AttendanceClock::wibToUtc()` kini memperlakukan input sebagai WIB; `AttendanceGenerator` melaporkan `unchanged` secara akurat (tidak lagi selalu `synced`).
- **Test baru:** `tests/Feature/Attendance/` (generator, aktivitas, koreksi, evaluator, HTTP/scope).
- **Dokumentasi baru:** `docs/attendance/{README,BUSINESS_RULES,SYSTEM_FLOW,DATABASE_DESIGN,USER_GUIDE,QA_REPORT}.md`.

Catatan: status `pending` bersifat dinamis (tidak dipersistensi); snapshot terkunci permanen setelah aktivitas; koreksi `pending` menahan sinkronisasi snapshot.

### Verifikasi

- `php artisan test tests/Feature/Attendance` → 35 lulus, 142 assertion.
- `php artisan test` → 261 lulus, 1688 assertion.
- `vendor/bin/pint --test` → passed.
- `npm run build` → sukses.
- `php artisan route:list --name=attendance` → 13 route.
- Verifikasi MySQL 8.4 dan uji browser E2E belum dilakukan; lihat `docs/attendance/QA_REPORT.md`.

---

*Terakhir diperbarui: 11 November 2026*
