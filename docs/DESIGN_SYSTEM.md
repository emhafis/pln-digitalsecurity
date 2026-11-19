# Design System — Frontend

Panduan konsistensi UI untuk seluruh frontend proyek **pln-digtyaceh**.
Referensi gaya: SiteMinder (clean SaaS dashboard) — light theme, navy/blue accent, whitespace legih, hierarki tegas.

---

## 1. Prinsip

1. **Clean & dense** — informasi mudah dipindai, tanpa dekorasi berlebih.
2. **Konsisten** — komponen dipakai ulang, jangan style inline untuk kebutuhan yang sudah punya komponen.
3. **Accessible** — kontras teks ≥ WCAG AA, status tidak hanya mengandalkan warna (pakai ikon/label).
4. **Responsive** — mobile-first; sidebar jadi drawer di layar kecil.

---

## 2. Warna

### Brand / Primary

| Token Tailwind | Hex | Pemakaian |
| --- | --- | --- |
| `blue-50` | `#EFF6FF` | bg ringan aktif/nav aktif |
| `blue-100` | `#DBEAFE` | badge info ringan |
| `blue-600` | `#2563EB` | tombol utama, link, aksi aktif |
| `blue-700` | `#1D4ED8` | hover tombol utama |
| `blue-800` | `#1E40AF` | teks aksen di bg terang |

> Ganti semua aksen `sky-*` lama ke `blue-*` agar selaras dengan brand.

### Neutral (permukaan & teks)

| Token | Pemakaian |
| --- | --- |
| `white` (`#FFF`) | sidebar, card, topbar |
| `gray-50` (`#F9FAFB`) | background halaman |
| `gray-100` (`#F3F4F6`) | bg ikon/hover lembut |
| `gray-200` (`#E5E7EB`) | border pemisah |
| `gray-400` (`#9CA3AF`) | placeholder, ikon sekunder |
| `gray-500` (`#6B7280`) | teks sekunder |
| `gray-700` (`#374151`) | teks body kuat |
| `gray-900` (`#111827`) | judul, teks utama |

### Sidebar

- Background: **`white`**, border kanan `gray-200` (bukan sidebar gelap).
- Item aktif: `bg-blue-50 text-blue-700 font-semibold` + indikator kiri (bar 3px `blue-600`).
- Item hover: `bg-gray-100 text-gray-900`.
- Label section (mis. “Data Master”): `text-[11px] font-semibold uppercase tracking-wider text-gray-400`.

### Semantic (status)

| Status | Contoh kelas |
| --- | --- |
| Success | `bg-emerald-50 text-emerald-700` (+ ikon) |
| Warning | `bg-amber-50 text-amber-700` |
| Danger | `bg-red-50 text-red-700` |
| Info / neutral | `bg-gray-100 text-gray-600` / `bg-blue-50 text-blue-700` |

### Chart / data (ke depan)

- Urutkan dengan satu hue: `blue-50 → blue-600`.
- Kategorikal: max 4–5 warna (blue, emerald, amber, rose, violet).

---

## 3. Tipografi

- Font: **Plus Jakarta Sans** (via Vite fonts), fallback `ui-sans-serif, system-ui`.
- Scale:

| Elemen | Kelas |
| --- | --- |
| Page title | `text-xl font-semibold text-gray-900` |
| Section title | `text-sm font-semibold text-gray-900` |
| Body | `text-sm text-gray-600` |
| Meta / helper | `text-xs text-gray-500` |
| Stat value | `text-2xl font-semibold tracking-tight text-gray-900` |
| Table header | `text-xs font-semibold uppercase tracking-wider text-gray-500` |

- Angka (IDR, kode): `font-medium tabular-nums`.

---

## 4. Spacing & Layout

- Base unit: **4px**.
- Padding card: `p-4` (rapat) / `p-5`–`p-6` (lapang).
- Gap grid: `gap-4` (default), `gap-6` antar blok halaman.
- Radius: card `rounded-xl`, tombol/input `rounded-lg`, badge `rounded-full` / `rounded-md`.
- Border: `border border-gray-200`.
- Shadow: `shadow-sm` saja — jangan shadow tebal di dashboard.
- Max width konten admin: full (sidebar 256px / `w-64`, content `flex-1`).

### Struktur halaman admin

```
PageHeader (judul + deskripsi + aksi kanan)
  └─ grid StatCard (opsional)
  └─ Card / DataTable
```

---

## 5. Komponen

Semua komponen admin ada di `resources/js/Components/Admin/` — **wajib pakai**, jangan duplikasi markup.

| Komponen | File | Fungsi |
| --- | --- | --- |
| `AdminSidebar` | `AdminSidebar.vue` | Navigasi kiri + menu Data Master |
| `AdminTopbar` | `AdminTopbar.vue` | Header sticky: hamburger, judul, user menu |
| `PageHeader` | `PageHeader.vue` | Judul halaman + slot aksi |
| `StatCard` | `StatCard.vue` | Kartu metrik (label, value, sub) |
| `Card` | `Card.vue` | Panel putih pembungkus konten |
| `DataTable` | `DataTable.vue` | Tabel standar (head/body slot) |
| `Badge` | `Badge.vue` | Label status |
| `Button` | `Button.vue` | Tombol `primary` \| `secondary` \| `ghost` \| `danger` |
| `SearchInput` | `SearchInput.vue` | Input cari dengan ikon |
| `EmptyState` | `EmptyState.vue` | Kosong / belum ada data |

Layout: `resources/js/Layouts/AdminLayout.vue` hanya **meng-compose** `AdminSidebar` + `AdminTopbar` + `<slot />`.

### Aturan pemakaian

- Halaman = `AdminLayout` + `PageHeader` + komponen di atas.
- Aksi utama 1 per halaman (biasanya kanan `PageHeader`).
- Tabel selalu lewat `DataTable`; status selalu `Badge`.

---

## 6. Ikon

- Package: **`@heroicons/vue`** (outline 24px) — import dari `@heroicons/vue/24/outline`.
- Contoh: `import { HomeIcon } from '@heroicons/vue/24/outline';`
- Pakai `<component :is="icon" class="h-5 w-5" />` atau langsung `<HomeIcon class="h-5 w-5" />`.
- Ukuran: `h-5 w-5` di nav/aksi, `h-4 w-4` inline, `h-6 w-6` empty state.
- Warna ikon ikut konteks (`text-current` / class warna pada parent).

---

## 7. Struktur folder (best practice)

```
resources/js/
├── Components/Admin/     # UI generik admin (tanpa logika bisnis)
├── Layouts/              # Shell halaman (Admin, App, Guest, Public)
├── Pages/Admin/          # Halaman per route
│   └── Master/           # CRUD tampilan Data Master
└── app.js
```

- **Page** hanya: ambil props → render layout + komponen.
- **Component** presentasional; state lokal UI (open/close) boleh, data server lewat props.
- Naming file = nama komponen PascalCase; folder `Master` untuk menu Data Master.

---

## 8. Menu sidebar (sementara)

```
Dashboard              /admin
── DATA MASTER ──
Unit                   /admin/master/unit
Pos Jaga               /admin/master/pos-jaga
Titik Patroli          /admin/master/titik-patroli
Satpam                 /admin/master/satpam
User                   /admin/master/user
```

> Semua halaman master saat ini **tampilan dulu** (placeholder + tabel contoh), tanpa aksi CRUD aktif.

---

## 9. Do / Don’t

| Do | Don’t |
| --- | --- |
| Pakai komponen `Components/Admin` | Copy-paste markup card/table antar halaman |
| `blue-600` untuk aksen utama | Campur `sky`, `indigo`, `blue` tanpa aturan |
| `shadow-sm` + border | Shadow besar / glass berlebihan |
| Badge status + teks | Hanya warna untuk status |
| Label section kapital kecil di sidebar | Submenu 3+ level |

---

*Terakhir diperbarui: 3 November 2026*
