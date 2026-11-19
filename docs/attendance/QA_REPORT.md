# Laporan QA - Absensi Satpam

Tanggal audit: **11 November 2026**.

## 1. Ringkasan

| Item | Hasil |
|---|---|
| Test fitur absensi | **35 lulus**, 142 assertion |
| Test suite penuh | Lihat bagian 4 |
| Laravel Pint | Lulus |
| Build frontend (`npm run build`) | Lulus |
| Verifikasi MySQL 8.4 langsung | Belum dilakukan |
| Uji browser end-to-end | Belum dilakukan |

## 2. Cakupan Pengujian

### Generator

- Membuat record shift kerja dan Libur beserta snapshot.
- Idempoten: eksekusi kedua menghasilkan `created=0`, `synced=0`,
  `unchanged=1`.
- Memblokir periode bila koordinat pos kosong dan membuat anomaly.
- Menyelesaikan anomaly saat data diperbaiki.
- Memblokir jam kerja tidak lengkap.
- Memblokir seluruh proses bila pengaturan global tidak ada.
- Snapshot terkunci tidak disinkronkan setelah check-in.
- Koreksi `pending` menahan sinkronisasi.
- Snapshot tersinkron saat belum ada aktivitas.
- Tidak membuat record untuk Satpam tidak valid.
- Snapshot pengaturan memakai nilai saat pembuatan.

### Aktivitas

- Check-in dalam jendela berhasil, status `in_progress`, snapshot terkunci.
- Check-in sebelum jendela ditolak.
- Check-in setelah shift selesai ditolak.
- Check-in di luar radius ditolak.
- Check-in dengan akurasi buruk ditolak.
- Check-in tanpa koordinat ditolak.
- Check-out sebelum shift selesai ditolak.
- Check-out setelah batas tutup ditolak.
- Check-out dalam jendela menyelesaikan record.

### Evaluator Tidak Hadir

- Menandai `absent` setelah batas `shift_end + checkout_close`.
- Tidak menandai sebelum batas atau bila sudah check-in.

### Koreksi

- Pengajuan + persetujuan menerapkan nilai koreksi dan mengunci snapshot.
- Hanya satu koreksi `pending` per record.
- Penolakan tercatat tanpa mengubah record.
- Koreksi tidak dapat disetujui dua kali.
- Status dinamis memerlukan status tujuan eksplisit.
- Status `completed` memerlukan check-in dan check-out.
- Koreksi disetujui menimpa status check-in.

### HTTP dan Hak Akses

- Hanya Superadmin dapat mengubah pengaturan.
- Halaman kesiapan dapat diakses Admin, ditolak untuk Satpam.
- Daftar record dibatasi lingkup unit Admin.
- Satpam melihat record sendiri dan dapat check-in.
- Satpam tidak dapat bertindak pada record Satpam lain.
- Admin hanya dapat menyetujui koreksi dalam lingkup unitnya.
- Seluruh route absensi memerlukan autentikasi.

## 3. Perbaikan Selama Pengembangan

| Temuan | Perbaikan |
|---|---|
| `AttendanceClock::wibToUtc()` memperlakukan input sebagai zona penyimpanan | Input diperlakukan sebagai WIB, lalu dikonversi ke zona penyimpanan. |
| `AttendanceGenerator` melaporkan `synced` setiap kali karena `last_synced_at` selalu berubah | Perbandingan snapshot dilakukan sebelum menulis; `unchanged` kini akurat. |
| `AdminSidebar.vue` kehilangan tag penutup setelah penambahan menu | Struktur template diperbaiki; build kembali lulus. |
| Import dan format tidak konsisten | Dijalankan Laravel Pint. |
| Tab aplikasi masih mengarah ke route lama | Tab Absensi diarahkan ke `/app/attendance`. |

## 4. Perintah Verifikasi

```bash
php artisan test tests/Feature/Attendance
php artisan test
vendor/bin/pint --test
npm run build
php artisan route:list --name=attendance
```

Route absensi terdaftar: **13 route**.

- `admin.attendance.readiness`
- `admin.attendance.generate`
- `admin.attendance-setting.edit`
- `admin.attendance-setting.update`
- `admin.attendance-record.index`
- `admin.attendance-record.show`
- `admin.attendance-correction.index`
- `admin.attendance-correction.approve`
- `admin.attendance-correction.reject`
- `app.attendance.index`
- `app.attendance.check-in`
- `app.attendance.check-out`
- `app.attendance.correction`

## 5. Batasan dan Risiko

1. **MySQL 8.4 belum diuji langsung.** Semua test berjalan pada SQLite
   in-memory. Tipe kolom dan index dipilih portabel, tetapi perilaku konkurensi
   MySQL (row lock, deadlock) belum diverifikasi.
2. **Belum ada uji browser/E2E.** Alur Vue (izin GPS, dialog koreksi, filter)
   belum diuji otomatis.
3. **Filter status "Menunggu" pada daftar record.** Status ini dinamis dan tidak
   dipersistensi; filter mengikuti status tersimpan. Detail record tetap
   menampilkan status efektif dengan benar.
4. **Koreksi `pending` dan generator.** Generator melewati record yang memiliki
   koreksi `pending`; setelah koreksi diputuskan, sinkronisasi tidak lagi
   otomatis karena snapshot sudah terkunci.
5. **Anomali global tidak tersimpan.** Masalah pengaturan hanya tampil pada
   halaman kesiapan sehingga tidak ada jejak riwayat.
6. **Check-in hanya untuk shift kerja.** Shift Libur tidak memiliki aksi
   aktivitas.
7. **Perubahan master setelah snapshot.** Perubahan nama Satpam, pos, atau shift
   tidak memengaruhi record lama; ini disengaja.
8. **Konsistensi zona waktu.** Seluruh konversi wajib melalui
   `AttendanceClock`; penambahan kode baru harus mematuhi aturan ini.

## 6. Rekomendasi Lanjutan

- Jalankan migrasi dan uji alur utama pada MySQL 8.4 staging.
- Tambahkan uji E2E untuk check-in/check-out dan pengajuan koreksi.
- Pertimbangkan laporan rekap absensi (harian/bulanan) dan ekspor.
- Pertimbangkan notifikasi untuk koreksi baru dan record Tidak Hadir.
- Pertimbangkan penanganan cuti/izin sebagai jenis shift tersendiri.
