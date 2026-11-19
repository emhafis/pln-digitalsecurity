# Panduan Pengguna - Absensi Satpam

Panduan ini ditujukan untuk Superadmin, Admin Unit, dan Satpam.

## 1. Peran dan Akses

| Peran | Akses |
|---|---|
| Superadmin | Semua unit, pengaturan absensi, kesiapan, data absensi, koreksi. |
| Admin Unit | Unit sendiri + unit turunan: kesiapan, data absensi, koreksi. |
| Satpam | Hanya data absensi miliknya: check-in, check-out, ajukan koreksi. |

Menu admin berada di sidebar **Absensi**:

- Kesiapan Absensi
- Data Absensi
- Pengajuan Koreksi
- Pengaturan Absensi (hanya Superadmin)

Satpam membuka menu **Absensi** pada aplikasi.

## 2. Pengaturan Absensi (Superadmin)

Buka **Absensi > Pengaturan Absensi**.

| Pengaturan | Arti | Default |
|---|---|---|
| Radius GPS (meter) | Jarak maksimal perangkat dari pos | 50 |
| Buka check-in sebelum shift (menit) | Check-in lebih awal | 15 |
| Toleransi terlambat (menit) | Batas masih dianggap tepat waktu | 5 |
| Toleransi check-out awal (menit) | Check-out sebelum shift selesai | 0 |
| Batas tutup check-out (menit) | Setelah shift selesai | 60 |
| Akurasi GPS maksimal (meter) | Batas akurasi perangkat | 50 |

Catatan:

- Nilai disalin ke setiap record absensi saat dibuat.
- Perubahan pengaturan tidak mengubah record yang sudah terkunci.
- Pengaturan tidak valid memblokir seluruh pembuatan data absensi.

## 3. Kesiapan Absensi (Admin Unit / Superadmin)

Buka **Absensi > Kesiapan Absensi**.

1. Pilih **Unit** (Superadmin dapat memilih semua unit).
2. Pilih **Periode Jadwal** bila ingin memeriksa periode tertentu.
3. Klik **Periksa Kesiapan**.

Yang diperiksa:

- pengaturan global valid,
- pos jaga memiliki koordinat,
- regu aktif dan memiliki anggota,
- periode memiliki jadwal,
- shift dan jam kerja valid,
- Satpam dan keanggotaan regu valid,
- relasi jadwal lengkap.

Status:

- **Siap**: periode dapat dibuatkan data absensi.
- **Belum Siap**: perbaiki masalah yang tercantum, lalu periksa ulang.

Periode **Belum Siap** tidak boleh dipaksa. Generator bersifat all-or-nothing:
tidak ada record parsial.

## 4. Membuat Data Absensi

### Otomatis

Scheduler menjalankan `attendance:generate` setiap 15 menit. Tidak ada tindakan
manual yang diperlukan bila cron scheduler aktif.

### Manual

1. Buka **Absensi > Kesiapan Absensi**.
2. Pilih unit/periode (opsional).
3. Klik **Buat Data Absensi**.
4. Periksa ringkasan hasil: dibuat, disinkronkan, tidak berubah, terkunci,
   anomali, dan error.

Hasil yang mungkin:

| Hasil | Arti |
|---|---|
| Dibuat | Record baru dibuat. |
| Disinkronkan | Snapshot record yang belum terkunci diperbarui. |
| Tidak berubah | Snapshot sudah sama; tidak ada penulisan. |
| Terkunci | Record sudah memiliki aktivitas; snapshot tidak diubah. |
| Anomali | Data bermasalah yang masih terjadi atau baru diselesaikan. |
| Error | Periode gagal dibuat; tidak ada record parsial. |

Data yang dibuat mengikuti jadwal: shift kerja menjadi `scheduled`, shift libur
menjadi `day_off`.

## 5. Data Absensi

Buka **Absensi > Data Absensi**.

- Filter berdasarkan unit, tanggal kerja, status, jenis shift, Satpam, atau pos.
- Klik baris untuk membuka **Detail Absensi**.
- Detail menampilkan snapshot jadwal, waktu asli, koordinat, jarak, akurasi,
  status efektif, dan riwayat koreksi.

Status yang terlihat:

| Status | Arti |
|---|---|
| Terjadwal | Belum check-in, masih dalam jendela waktu. |
| Menunggu | Ada koreksi yang belum diputuskan. |
| Sedang Bertugas | Sudah check-in, belum check-out. |
| Selesai | Sudah check-in dan check-out. |
| Tidak Hadir | Tidak check-in sampai batas waktu. |
| Libur | Shift libur. |

Catatan: filter "Menunggu" mengikuti status tersimpan; status dinamis
ditampilkan pada detail.

## 6. Pengajuan Koreksi (Admin Unit / Superadmin)

Buka **Absensi > Pengajuan Koreksi**.

1. Pilih pengajuan berstatus **Menunggu**.
2. Periksa alasan, waktu usulan, dan status usulan.
3. Klik **Setujui** atau **Tolak**.
4. Isi catatan peninjau bila perlu.

Aturan:

- Hanya satu koreksi menunggu per record.
- Koreksi yang disetujui mengisi waktu/status efektif dan mengunci snapshot.
- Koreksi ditolak tidak mengubah record.
- Riwayat koreksi tidak dapat dihapus atau diubah.
- Admin Unit hanya melihat koreksi dalam lingkup unitnya.

## 7. Panduan Satpam

### Check-in

1. Buka **Absensi**.
2. Pastikan GPS perangkat aktif dan izin lokasi diberikan.
3. Pilih jadwal hari ini.
4. Klik **Check-in**.
5. Tunggu hingga muncul pesan berhasil.

Check-in gagal bila:

- belum/telah lewat jendela check-in,
- shift bukan shift kerja,
- sudah check-in,
- GPS tidak tersedia,
- jarak melebihi radius,
- akurasi melebihi batas.

### Check-out

1. Buka **Absensi**.
2. Pilih record yang sedang bertugas.
3. Klik **Check-out**.

Check-out hanya tersedia mendekati akhir shift sesuai pengaturan.

### Mengajukan Koreksi

1. Buka detail record.
2. Klik **Ajukan Koreksi**.
3. Isi alasan dan waktu/status yang benar.
4. Kirim. Pengajuan berstatus **Menunggu** sampai diputuskan admin.

Satpam tidak dapat menyetujui koreksinya sendiri dan tidak dapat mengakses
record Satpam lain.

## 8. Perintah Operasional

```bash
# Melihat kesiapan
php artisan attendance:readiness
php artisan attendance:readiness --unit=1 --period=1

# Membuat data absensi
php artisan attendance:generate
php artisan attendance:generate --unit=1 --period=1

# Menetapkan Tidak Hadir
php artisan attendance:evaluate-absent
```

Pastikan scheduler Laravel berjalan:

```bash
* * * * * cd /path/pln-digtyaceh && php artisan schedule:run >> /dev/null 2>&1
```

## 9. Penanganan Masalah

| Gejala | Penyebab umum | Tindakan |
|---|---|---|
| Periode Belum Siap | Koordinat pos kosong, shift tidak lengkap, regu/Satpam tidak valid | Perbaiki data master, periksa ulang. |
| Check-in ditolak "di luar jendela" | Waktu server di luar jendela | Periksa jam server dan pengaturan. |
| Check-in ditolak "terlalu jauh" | Radius terlalu kecil atau GPS tidak akurat | Pindah ke area pos, aktifkan GPS akurasi tinggi. |
| Check-in ditolak "akurasi rendah" | Sinyal GPS buruk | Tunggu hingga akurasi membaik. |
| Tidak bisa check-out | Belum check-in atau di luar jendela check-out | Periksa status dan waktu; gunakan koreksi bila perlu. |
| Record tidak berubah setelah generate | Snapshot terkunci | Gunakan alur koreksi, bukan generate ulang. |
| Tidak Hadir padahal hadir | Check-in tidak tercatat | Ajukan koreksi dengan bukti. |

## 10. Catatan Keamanan Data

- Bukti asli (waktu, koordinat, akurasi) tidak pernah ditimpa.
- Koreksi menyimpan nilai sebelum dan sesudah perubahan.
- Peninjau koreksi tercatat pada riwayat.
- Snapshot yang terkunci tidak dapat diubah oleh generator.
