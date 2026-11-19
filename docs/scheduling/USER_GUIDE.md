# Panduan Pengguna — Penjadwalan Satpam

Panduan ini ditujukan untuk admin/superadmin yang mengoperasikan modul
penjadwalan. Semua menu berada di bawah prefix `/admin`.

## 1. Peran dan Akses

- `superadmin`: dapat mengakses seluruh unit.
- `admin`: hanya unit miliknya dan unit turunannya.
- `security`: bukan pengguna modul penjadwalan admin.

Route penjadwalan (`shift`, `schedule-pattern`, `schedule-period`,
`schedule-calendar`) dibatasi middleware `role:admin,superadmin`. Controller
tetap menerapkan unit scope, sehingga admin tidak dapat membuka data unit lain
meskipun mengetahui ID-nya.

## 2. Menyiapkan Data Master

Urutan yang disarankan:

1. **Data Unit** — pastikan hierarki unit benar.
2. **Data Post Keamanan** — pos jaga milik unit.
3. **Data Personel Security** — personel terhubung ke user dan unit.
4. **Data Shift** (`/admin/shift`).
5. **Data Pola Rotasi** (`/admin/schedule-pattern`).

### 2.1 Shift

- Nama dan kode wajib, unik per unit.
- Jam mulai/selesai boleh dikosongkan (mis. shift Libur).
- Untuk shift lintas tengah malam (mis. 20:00–04:00), aktifkan opsi
  “berakhir hari berikutnya”.
- Shift nonaktif tidak dapat dipakai pada pola baru atau penyesuaian manual.
- Shift yang sudah dipakai pola/jadwal tidak dapat dihapus; nonaktifkan saja.

### 2.2 Pola Rotasi

- Tentukan `cycle_length` (jumlah hari siklus).
- Isi shift untuk setiap urutan hari `1..cycle_length`; semua wajib terisi.
- Shift harus aktif dan berasal dari unit yang sama.
- Setelah pola dipakai periode/jadwal:
  - `cycle_length` dan unit tidak dapat diubah;
  - detail shift masih dapat diubah, tetapi **tidak** mengubah jadwal lama;
  - pola tidak dapat dihapus.

Contoh pola 2-2-2-2 (8 hari): Pagi, Pagi, Siang, Siang, Malam, Malam, Libur,
Libur.

## 3. Menyiapkan Regu dan Anggota

Menu: **Data Regu** (`/admin/security-team`).

1. Klik **+ Tambah Regu**, pilih unit dan pos jaga, lalu isi kode, nama,
   deskripsi (opsional), dan status.
2. Buka detail regu, lalu klik **+ Tambah Anggota** untuk memilih satpam dan
   tanggal bergabung.
3. Gunakan **Pindah** pada anggota aktif untuk memindahkannya ke regu lain.
   Regu tujuan harus aktif dan berada dalam scope unit pengguna.
4. Gunakan **Akhiri** untuk mengisi tanggal berakhir keanggotaan.

Ketentuan:

- Satu satpam hanya boleh memiliki satu keanggotaan aktif. Perpindahan otomatis
  menutup keanggotaan lama (`left_at = tanggal bergabung baru - 1`) dalam
  transaksi; riwayat tidak dihapus.
- `joined_at` adalah hari pertama aktif, `left_at` adalah hari terakhir aktif
  (inklusif); `left_at = null` berarti masih aktif.
- Unit satpam harus berada dalam scope unit pos jaga regu.
- Regu nonaktif tidak dapat menerima anggota baru atau dipakai untuk periode
  jadwal baru. Periode lama yang sudah memakai regu tersebut tetap dapat diubah
  (kecuali field yang terkunci karena sudah memiliki jadwal).
- Pos jaga regu tidak dapat diubah setelah regu memiliki riwayat anggota atau
  periode jadwal. Regu seperti itu juga tidak dapat dihapus; nonaktifkan saja.
- Perubahan keanggotaan (tambah/pindah/akhiri) tidak mengubah jadwal, snapshot
  periode, atau riwayat lama. Jika satpam masih memiliki jadwal pada periode
  `draft`/`active`, sistem menampilkan peringatan informatif.

## 4. Membuat Periode Jadwal

Menu: **Periode Jadwal** (`/admin/schedule-period`) → **Tambah**.

Isi:

- Unit, Pos Jaga, Regu (harus satu unit).
- Nama periode.
- Tanggal mulai dan selesai (`end_date >= start_date`).
- Status: `draft`, `active`, atau `completed`.

Snapshot anggota:

- **Otomatis**: jika anggota tidak diisi, sistem menyalin anggota regu yang
  aktif pada tanggal mulai periode.
- **Manual**: pilih satpam, pola rotasi, dan `start_offset` (0 sampai
  `cycle_length - 1`).
- **Salin dari periode sebelumnya**: menyalin snapshot anggota saja, bukan
  jadwal.

Aturan penting:

- Periode untuk regu yang sama tidak boleh overlap pada rentang tanggal,
  termasuk periode `draft`/`completed`.
- Snapshot periode bersifat independen. Perubahan anggota regu setelah periode
  dibuat tidak mengubah snapshot tersebut.
- Setelah periode memiliki jadwal, unit/pos/regu/tanggal/snapshot terkunci.
  Hanya nama dan status yang dapat diubah.

## 5. Generate Jadwal

Buka detail periode → **Generate Jadwal** (atau
`/admin/schedule-period/{id}/generate`).

Dua mode:

| Mode | Perilaku |
|---|---|
| **Fill** (default) | Hanya membuat jadwal yang belum ada. Baris existing tidak diubah. Aman dijalankan berulang. |
| **Regenerate** | Menghitung ulang seluruh baris `source = system_generated` pada periode ini. Jadwal `source = adjustment` selalu dipertahankan. |

Langkah:

1. Buka halaman generate.
2. Pilih mode.
3. Periksa ringkasan dan preview baris (create/skip/update/delete/keep manual).
4. Jalankan generate.

Validasi bersifat **all-or-nothing**. Jika ada satu masalah, tidak ada jadwal
yang ditulis. Masalah umum:

- Periode belum punya snapshot anggota.
- Pola/detail/shift tidak lengkap, nonaktif, atau beda unit.
- `start_offset` di luar siklus.
- Satpam sudah punya jadwal pada tanggal yang sama di periode lain.

Jadwal yang dihasilkan memakai `source = system_generated` dan menyimpan pola serta
urutan hari pola untuk audit.

## 6. Penyesuaian Manual

Di halaman detail periode, tabel jadwal menyediakan aksi **Sesuaikan** per baris.

- Pilih shift pengganti (aktif, satu unit dengan periode).
- Isi alasan perubahan (wajib, maksimal 1000 karakter).
- Shift pengganti tidak boleh sama dengan shift saat ini.
- Baris berubah menjadi `source = adjustment`; `updated_by` dan `notes` tersimpan.
- Tanggal dan satpam tidak berubah, sehingga unique `(security_id, date)` tetap
  aman.
- Periode berstatus `completed` tidak dapat diubah.
- Setelah manual, regenerate tetap mempertahankan baris tersebut.

## 7. Kalender dan Monitoring

Menu: **Kalender & Monitoring** (`/admin/schedule-calendar`).

Fitur:

- Tampilan kalender bulanan atau daftar.
- Navigasi bulan sebelumnya/berikutnya.
- Filter: unit, pos jaga, regu, satpam, status periode.
- Menampilkan shift, waktu, sumber (`system_generated`/`adjustment`), catatan, dan user
  yang terakhir mengubah.
- Baris `completed` ditandai tidak dapat diedit (`can_edit = false`).
- Aksi edit mengarah ke halaman detail periode; tidak ada mekanisme edit baru.

Catatan:

- Kalender membaca baris `schedules` aktual, bukan hasil hitung ulang pola.
- Penanda “Libur” ditentukan dari nama/kode shift yang mengandung `libur`,
  `off`, atau `lepas`. Schema belum memiliki flag khusus hari libur.
- Jika periode belum pernah digenerate, kalender tidak menampilkan baris.

## 8. Status Periode

| Status | Makna |
|---|---|
| `draft` | Periode disiapkan; jadwal masih dapat digenerate/diubah. |
| `active` | Periode berjalan; jadwal masih dapat disesuaikan. |
| `completed` | Periode selesai; penyesuaian manual diblokir dan kalender menandai baris tidak dapat diedit. |

Status dapat diubah melalui halaman edit periode. Perubahan status tidak
menghapus jadwal.

## 9. Troubleshooting

| Pesan / Gejala | Penyebab | Tindakan |
|---|---|---|
| “Periode belum memiliki snapshot anggota.” | Tidak ada anggota pada snapshot. | Edit periode dan isi/salin snapshot. |
| “Pola rotasi ... sedang nonaktif.” | Pola nonaktif. | Aktifkan pola atau ganti pola anggota. |
| “Shift ... sedang nonaktif.” | Shift pada detail pola nonaktif. | Aktifkan shift atau ubah detail pola. |
| “sudah memiliki jadwal pada periode lain.” | Bentrok unique `(security_id, date)`. | Sesuaikan tanggal/periode atau batalkan jadwal di periode lain. |
| “Periode jadwal untuk regu ini sudah ada pada rentang tanggal tersebut.” | Overlap periode regu. | Ubah rentang tanggal. |
| “Periode sudah selesai sehingga jadwal tidak dapat diubah.” | Status `completed`. | Ubah status ke `active` bila memang diizinkan. |
| “Satpam pada jadwal ini bukan anggota snapshot periode.” | Baris jadwal tidak konsisten dengan snapshot. | Periksa data jadwal/periode. |
| “Shift pengganti sama dengan shift saat ini.” | Memilih shift yang sama. | Pilih shift berbeda. |

## 10. Checklist Operasional

- [ ] Unit, pos, dan personel sudah benar.
- [ ] Shift aktif dan jam kerja sesuai.
- [ ] Pola rotasi lengkap dan shift-nya aktif.
- [ ] Regu dan anggota aktif sudah ditetapkan.
- [ ] Periode tidak overlap dengan periode regu lain.
- [ ] Snapshot anggota sudah benar sebelum generate.
- [ ] Preview generate diperiksa.
- [ ] Jadwal manual diberi alasan yang jelas.
- [ ] Kalender bulan berjalan diperiksa.
