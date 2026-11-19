# Aturan Bisnis — Modul Asset (APAR & Hydrant)

Dokumen ini adalah acuan aturan yang disepakati. Implementasi **wajib** mematuhinya; jangan klaim fitur selesai sebelum terwujud & terverifikasi.

## 1. Akses dan Otorisasi
- **Admin**: dapat mengelola data APAR dan Hydrant sesuai **cakupan unit yang diizinkan** (ikuti pola authorization/scoping proyek; konsisten dan tanpa duplikasi tidak perlu).
- **Security**: akun Security **aktif** dapat melihat daftar & detail APAR/Hydrant **milik unitnya sendiri**.
- **Konteks relasi**: Security terhubung melalui **Security → User → Unit**. **Keanggotaan regu bukan syarat** inspeksi.
- Validasi status aktif mengacu pada `users.is_active` (serta keterhubungan profil security) sesuai mekanisme aplikasi.

## 2. Pelaksanaan Inspeksi
- Security **aktif** dapat melakukan inspeksi terhadap aset **aktif** milik unitnya.
- Inspeksi dapat dilakukan **kapan saja**, termasuk saat libur atau di luar jadwal kerja.
- Inspeksi **tidak bergantung** pada jadwal, shift, regu, absensi, maupun sesi patroli.
- **Waktu inspeksi berasal dari server** (bukan waktu input klien sebagai sumber kebenaran).

## 3. Integritas Data & Riwayat
- Setiap inspeksi merupakan **record baru**. Inspeksi sebelumnya **tidak boleh ditimpa atau dihapus**.
- Riwayat inspeksi **harus tetap tersedia** meskipun aset dinonaktifkan.
- **Jangan** melakukan operasi destruktif terhadap data historis.

## 4. Validasi Form Inspeksi
- **Notes wajib diisi** jika hasil inspeksi **tidak baik**.
- **Maksimal 5 foto** per inspeksi.
- Checklist dan struktur data inspeksi **mengikuti migration/checklist yang diberikan** (termasuk penggunaan kolom `is_good` pada inspection items sesuai migration acuan).

## 4A. Aturan Hydrant MVP
- Hydrant MVP hanya mendukung jenis `fire_pump_diesel`.
- Lima jenis Hydrant lain tetap tersedia dalam enum database tetapi disabled pada dropdown Create.
- Data Hydrant lama dengan jenis selain `fire_pump_diesel` tetap aman saat ditampilkan atau diedit; jenis tidak diubah secara tidak sengaja.
- Checklist Hydrant tetap 11 item untuk Fire Pump Diesel.
- Checklist dinamis berdasarkan jenis Hydrant merupakan pengembangan masa depan, bukan fitur saat ini.

## 5. Batasan Perubahan Sistem:
- **Jangan mengubah** modul **scheduling, attendance, dan patrol**.
- Jangan membuat migration/model/struktur DB asumtif; **struktur migration yang diberikan pemilik proyek adalah acuan**.

## 6. Catatan Teknis dari Audit (harus dipatuhi)
- 8 migration APAR/Hydrant/inspeksi terkait **belum tersedia & belum diverifikasi di repository** → skema & `is_good` belum terverifikasi; implementasi menunggu acuan tersebut (**ASSET-03 BLOCKED, B-01** tercatat di `PROGRESS.md`).
- Unit: relasi `parent_id` dan `Unit::descendantIds()` (own+descendants). `Unit::accessibleUnitIds()` **belum tersedia** di repository.
- Ikuti pola authorization/scoping yang sudah ada; hindari duplikasi yang tidak perlu.
- Status roadmap & task aktif: lihat **`PROGRESS.md`** (roadmap resmi 19 task).
