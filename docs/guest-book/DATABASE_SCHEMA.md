# Database Schema — Digital Guest Book (Aktual)

Dokumentasi skema **sesuai migration aktual** modul Guest Book (TASK 03/07). Tidak ada perubahan struktur `users` maupun modul lain.

## 1. Tabel `units` (penambahan kolom)

| Kolom | Tipe | Null | Default | Constraint/Index | Keterangan |
|---|---|---|---|---|---|
| `guest_book_qr_code` | varchar(36) | yes (existing) | — | unique (existing) | Identifier **QR Check-in** (UUID v4, stabil). Diisi otomatis saat unit dibuat (event `creating`) bila kosong; `unit_code` diset saat `created`. |
| `guest_book_checkout_qr_code` | varchar(36) | yes | — | unique (`units_guest_book_checkout_qr_code_unique`) | Identifier **QR Check-out** (UUID v4). Ditambah oleh migration `2026_11_18_000001_add_guest_book_checkout_qr_code_to_units_table` (`after guest_book_qr_code`). Diisi otomatis saat `creating` bila kosong; backfill hanya untuk baris NULL. |

- QR regenerasi (Superadmin) mengganti UUID kolom terkait; QR lama tidak berlaku untuk transaksi baru. Histori terjaga oleh snapshot di `guest_visits`.

## 2. Tabel `guest_visits` (migration `2026_11_18_000002_create_guest_visits_table`)

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | bigint unsigned, PK, auto increment | no | — | |
| `unit_id` | bigint unsigned, FK | no | — | `->constrained('units')->restrictOnDelete()` (unit dari QR tervalidasi server) |
| `guest_book_qr_code` | varchar(36) | no | — | **Snapshot** identifier QR check-in yang dipakai saat transaksi |
| `full_name` | varchar(255) | no | — | Nama tamu |
| `phone_number` | varchar(20) | no | — | No. HP (lookup checkout: unit+HP ternormalisasi, aktif) |
| `company_name` | varchar(255) | no | — | Instansi/perusahaan |
| `position` | varchar(255) | no | — | Jabatan |
| `visit_purpose` | varchar(500) | no | — | Keperluan |
| `person_to_meet` | varchar(255) | no | — | Orang yang ditemui |
| `vehicle_plate_number` | varchar(20) | yes | — | Opsional |
| `check_in_at` | timestamp | no | — | Waktu check-in (server) |
| `check_out_at` | timestamp | yes | — | Waktu check-out (server). `NULL` = masih berada di lokasi |
| `checkout_source` | varchar(20) | yes | — | `guest` \| `admin` (saat checkout) |
| `checked_out_by` | bigint unsigned, FK nullable | yes | — | `->nullable()->constrained('users')->restrictOnDelete()`; admin pelaku manual checkout (histori identitas terjaga, tanpa ubah `users`) |
| `signature_path` | varchar(255) | yes | — | Path file privat (disk `local`) |
| `selfie_path` | varchar(255) | yes | — | Path file privat (disk `local`) |
| `consent_given` | boolean | no | false | Persetujuan data (wajib true saat check-in valid) |
| `consent_at` | timestamp | yes | — | Waktu persetujuan (server) |
| `unit_name_snapshot` | varchar(255) | no | — | Snapshot nama unit |
| `unit_code_snapshot` | varchar(50) | yes | — | Snapshot kode unit |
| `ip_address` | varchar(45) | yes | — | IPv4/IPv6 |
| `user_agent` | text | yes | — | User agent |
| `created_at`, `updated_at` | timestamps | — | — | |

**Constraint & index (aktual):**
- FK: `unit_id` → `units.id` **RESTRICT** delete; `checked_out_by` → `users.id` **RESTRICT** delete, nullable.
- Index: `idx_guest_visits_unit_id` (`unit_id`), `idx_guest_visits_check_in_at` (`check_in_at`), `idx_guest_visits_unit_check_in` (`unit_id`,`check_in_at`), `idx_guest_visits_check_out_at` (`check_out_at`), `idx_guest_visits_unit_check_out` (`unit_id`,`check_out_at`), `idx_guest_visits_unit_phone_active` (`unit_id`,`phone_number`,`check_out_at`), `idx_guest_visits_full_name` (`full_name`), `idx_guest_visits_company_name` (`company_name`), `idx_guest_visits_checked_out_by` (`checked_out_by`).
- **Tidak ada** kolom/token checkout terpisah (checkout via nomor HP).

## 3. Relasi Antar-Model
- `GuestVisit` **belongsTo** `Unit` (`unit_id`).
- `GuestVisit` **belongsTo** `User` sebagai `checkedOutBy` (`checked_out_by`, nullable).
- `Unit` **hasMany** `GuestVisit` (`guestVisits()`).
- Scope admin mengikuti hierarki unit: Admin = unit sendiri + `Unit::descendantIds()`; Superadmin = seluruh unit.

## 4. Snapshot & Tujuan Kolom Kunci
- `guest_book_qr_code` (visit) / `unit_name_snapshot` / `unit_code_snapshot`: menjaga histori utuh saat QR diregenerasi atau data unit berubah.
- `check_in_at`/`check_out_at`/`consent_at`: waktu server (client tidak dipercaya).
- `checkout_source` + `checked_out_by`: audit sumber checkout (tamu/admin) dan identitas admin (FK restrict, tidak berubah walau akun dinonaktifkan).
- `signature_path`/`selfie_path`: referensi file privat; `ip_address`/`user_agent`: jejak permintaan (proporsional).

## 5. Aturan Penyimpanan File
- Disk **`local`** (private, `storage/app/private`). Path acak: `guest-book/{selfie|signature}/{unitId}/{uuid}.{ext}`; nama file pengguna tidak dipakai.
- Validasi upload: selfie image `jpg/jpeg/png/webp` max 2MB; tanda tangan data URL image (png/jpeg/webp) dengan batas ukuran string. Disajikan hanya via controller terotorisasi & scoped (`admin.guest-book.file`), path divalidasi prefix `guest-book/` dan anti path-traversal. File sementara dibersihkan jika transaksi/penyimpanan gagal.