# Alur Pembuatan Patroli & Penggunaan di Sisi Satpam

Dokumen ini menjelaskan alur operasional modul patroli DSMS: penyiapan data, pembentukan sesi, pelaksanaan oleh Satpam, serta pemantauan admin.

## 1. Prasyarat & Penyiapan (Admin)

1. **Data dasar** tersedia dan konsisten (cakupan unit/pos/regu): Unit, Pos Jaga (`security_posts`), Regu (`security_teams`), Shift, akun Satpam yang terhubung ke data `Security`, serta jadwal.
2. **Titik Patroli & QR Code** (`/admin/patrol-point`, role `admin`/`superadmin`, sesuai scope unit):
   - Titik patroli dikelola per **Pos Jaga** (buat/ubah/hapus, relasi ke pos jaga).
   - Setiap titik memiliki **QR Code (UUID)** unik. Admin dapat mengunduh **PDF QR Code** per pos untuk ditempel di lokasi patroli.
   - Riwayat patroli memakai **snapshot** sesi/titik/scan (bukan master terkini), sehingga perubahan data master tidak mengubah histori.
3. **Jadwal & Periode** (modul penjadwalan):
   - Periode berstatus **`active`** menjadi sumber pembentukan sesi patroli. Periode **`draft`** atau **`completed`** tidak membentuk sesi.
   - Hanya jadwal shift **`working`** (termasuk Stand By) yang dapat membentuk sesi; jadwal **`day_off`/Lepas Dinas** tidak membentuk sesi.
   - Anggota regu dan jadwal per tanggal/shift harus sudah tersusun pada periode aktif.

## 2. Pembentukan Sesi Patroli (Generator)

- **Komponen:** `App\Services\PatrolSessionGenerator`, perintah `php artisan patrol:generate-sessions`.
- **Penjadwalan otomatis:** terdaftar di `routes/console.php`, berjalan **tiap 15 menit** dengan `withoutOverlapping` (bersama scheduler absensi). Membutuhkan cron server: `* * * * * php artisan schedule:run`.
- **Sumber data:** membaca periode berstatus `active` dan jadwal dalam rentang periode, lalu mengelompokkan per **(periode jadwal, tanggal kerja, shift)** untuk shift `working`.
- **Isi sesi (snapshot):**
  - **Sesi** (`patrol_sessions`): status awal **`pending`**, snapshot unit/pos/regu/shift & jam kerja, tanggal kerja.
  - **Anggota** (`patrol_session_members`): dari jadwal kelompok tersebut (snapshot nama & nomor registrasi Satpam, tautan `schedule_id`/`security_id`).
  - **Titik wajib** (`patrol_session_points`): titik patroli milik pos jaga, **urut `id` ASC** sebagai `sequence` (snapshot nama & QR titik).
- **Aturan penting:**
  - Jika pos **tidak memiliki titik patroli**, kelompok dilewati (tercatat/anomali) dan sesi **tidak** dibuat.
  - **Idempotent**: aman dijalankan berulang (transaksi + lock + constraint unik), tidak membuat duplikasi/parsial.

## 3. Akses & Tampilan di Sisi Satpam

- **Akses:** area aplikasi Satpam (`/app`), middleware `auth`, `role:security`, `security.profile`.
- **Identitas:** Satpam ditentukan dari user login (`user_id` → data `Security`), **bukan** dari input frontend.
- **Route utama:**
  - `GET /app/patrol` (`app.patrol.index`) — daftar sesi
  - `GET /app/patrol/{patrolSession}` (`app.patrol.show`) — detail sesi
  - `POST /app/patrol/{patrolSession}/start` (`app.patrol.start`) — mulai sesi
  - `POST /app/patrol/{patrolSession}/scan` (`app.patrol.scan`) — scan titik
  - `GET /app/patrol/{patrolSession}/scans/{scan}/photo` (`app.patrol.scan.photo`) — foto insiden (privat, anggota sesi)
- **Daftar sesi:** hanya sesi di mana Satpam terdaftar sebagai **anggota**. Dikelompokkan menjadi **Hari Ini** dan **Jadwal Lainnya**, menampilkan pos, regu, tanggal, shift/jam, status, dan progres **titik dipindai / titik wajib**.
- **Detail sesi:** menampilkan info snapshot (unit/pos/regu/shift/tanggal/jam, mulai/selesai), progres, daftar titik **berurutan sequence** (sudah/belum dipindai, waktu scan, insiden), dan scan terakhir. Aksi mengikuti status sesi.

## 4. Pelaksanaan Patroli oleh Satpam

### 4.1 Mulai Sesi
- Tersedia saat status **`pending`** (tombol **Mulai Patroli**).
- Hanya **anggota** sesi yang dapat memulai; sesi harus memiliki anggota & titik wajib valid.
- `pending` → **`active`** dan `started_at` dicatat **waktu server**. Jika sudah `active`, permintaan bersifat idempotent. Status terminal tidak dapat dimulai.

### 4.2 Pindai QR Titik (Scan)
- Dilakukan saat sesi **`active`**. UI menggunakan kamera perangkat (`html5-qrcode`, kamera belakang/`environment`) untuk memindai QR titik di lokasi.
- **Validasi & pencatatan:**
  - Hanya **anggota** sesi; sesi harus `active`.
  - **QR Code** harus cocok dengan snapshot titik pada sesi (`patrol_session_points`). QR tidak sesuai ditolak.
  - Titik yang sudah dipindai (duplikat) ditolak (dijaga constraint unik).
  - Identitas pemindai dan **`scanned_at`** berasal dari **user login & server** (disimpan sebagai snapshot pada scan).
- **Lokasi/GPS (opsional, best-effort):** latitude, longitude, dan akurasi dapat dikirim jika tersedia; tidak wajib.
- **Anggota regu lain** dalam sesi yang sama dapat melanjutkan scan selama sesi masih `active`.

### 4.3 Laporan Insiden (opsional per scan)
- Saat scan, Satpam dapat menandai **insiden**:
  - **Deskripsi insiden wajib** diisi bila insiden ditandai.
  - **Foto opsional** (jpg/jpeg/png/webp, tervalidasi & dikompresi di klien, dibatasi ukuran) disimpan **privat** di disk `local` (`patrol-incidents/`) dan hanya dapat diakses anggota sesi melalui endpoint foto privat (bukan URL publik).
  - Pengiriman deskripsi/foto tanpa menandai insiden ditolak.
- Insiden tercatat pada data scan (snapshot identitas pelapor & titik).

### 4.4 Penyelesaian Sesi
- Saat jumlah scan mencapai **jumlah titik wajib**, sesi otomatis menjadi **`completed`** dan `ended_at` dicatat (waktu server).
- Sesi berstatus terminal (**`completed`**, **`incomplete`**, **`not_started`**) **tidak** menerima scan baru.

## 5. Status Sesi

| Status | Keterangan |
|---|---|
| `pending` | Sesi terbentuk, belum dimulai |
| `active` | Sedang berlangsung (ada `started_at`), menerima scan |
| `completed` | Seluruh titik wajib telah dipindai |
| `incomplete` | Sesi tidak tuntas (sesuai status aktual) |
| `not_started` | Sesi tidak dimulai |

Status dan progres ditentukan oleh backend/snapshot; frontend tidak menghitung ulang aturan bisnis.

## 6. Pemantauan & Laporan (Admin)

- **Monitoring Patroli** (`/admin/patrol-session`, `admin.patrol-session.*`, scope unit): daftar & detail sesi **read-only** berbasis snapshot—progres, anggota, titik per sequence, hasil scan (waktu, pemindai, GPS), insiden & foto (akses foto insiden privat khusus admin berwenang).
- **Laporan Patroli** (`/admin/patrol-report`, `admin.patrol-report.*`): rekap & statistik sesuai filter/scope unit serta **ekspor Excel**—read-only, tidak mengubah data.

## 7. Catatan Operasional

- Pastikan **periode `active`**, jadwal/anggota regu, dan **titik patroli pos** tersedia agar sesi terbentuk. Kelompok tanpa titik patroli dilewati.
- **Cron scheduler** (`php artisan schedule:run` tiap menit) harus aktif agar pembentukan sesi berjalan otomatis periodik (atau jalankan `php artisan patrol:generate-sessions` secara manual bila diperlukan).
- Integritas riwayat dijaga oleh **snapshot** (sesi/anggota/titik/scan) serta constraint unik/FK. Modul patroli tidak mengubah aturan/modul penjadwalan dan absensi.
- Foto insiden disimpan di penyimpanan **privat** (`local`) dan diakses melalui endpoint terotorisasi (bukan URL publik). Verifikasi kamera/GPS/upload pada perangkat fisik (mobile/browser) tetap disarankan saat UAT.
