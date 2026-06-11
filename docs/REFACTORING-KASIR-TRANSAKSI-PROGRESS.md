# 🎨 Progress Refactoring Kasir Admin & Transaksi

**Tanggal:** 7 Juni 2026  
**Tujuan:** Merombak total tampilan Kasir Admin dan Transaksi menggunakan shadcn/ui design system agar lebih clean, modern, dan konsisten dengan menu admin lainnya.

---

## 📋 Ringkasan Masalah Awal

### **Struktur CSS yang Ada:**
```
resources/css/
├── admin.css          # CSS untuk semua halaman admin
├── kasir.css          # CSS untuk kasir role (non-admin) 
├── transaksi.css      # CSS untuk transaksi kasir role
├── app.css            # Base CSS untuk semua
└── tokens.css         # Design tokens (HSL colors)
```

### **Loading Logic di layouts/app.blade.php:**
```php
@vite(['resources/css/app.css'])  // Selalu load

// Kasir & Admin Kasir → pakai kasir.css
@if(request()->routeIs('kasir.*') || request()->routeIs('admin.kasir.*'))
    @vite('resources/css/kasir.css')
@endif

// Transaksi NON-admin → pakai transaksi.css
@if(request()->routeIs('transaksi.*') && !$isAdminCss)
    @vite('resources/css/transaksi.css')
@endif

// Semua halaman admin → pakai admin.css
@if($isAdminCss)
    @vite(['resources/css/admin.css'])
@endif
```

### **Masalah yang Ditemukan:**

1. **Kasir Admin** (route: `admin.kasir.*`)
   - ✅ Load: `app.css` + `kasir.css` + `admin.css`
   - ❌ Masalah: Pakai class dari `kasir.css` yang konflik dengan `admin.css`
   - ❌ Masalah: Banyak enhanced class custom yang tidak konsisten

2. **Transaksi Admin** (route: `transaksi.*` dengan role admin)
   - ✅ Load: `app.css` + `admin.css`
   - ❌ Masalah: View pakai enhanced class yang tidak ada di `admin.css`
   - ❌ Masalah: Tidak konsisten dengan menu admin lain (Karyawan, Produk, dll)

3. **Tidak Konsisten dengan Menu Admin Lain:**
   - Menu admin lain (Karyawan, Produk, Dashboard) menggunakan class admin standar
   - Kasir Admin & Transaksi menggunakan custom enhanced class dengan Material Icons

---

## ✅ Yang Sudah Dikerjakan

### **1. Halaman Transaksi (Admin Role)** ✅ SELESAI

**File:** `resources/views/transaksi/index.blade.php`

**Perubahan:**
- ✅ **Rombak total** dari nol menggunakan shadcn/ui design
- ✅ Hapus semua enhanced class custom
- ✅ Gunakan class admin standar:
  - `.admin-page-head` untuk header
  - `.admin-section-card` untuk card wrapper
  - `.admin-card-header`, `.admin-card-title`, `.admin-card-description`
  - `.admin-table-wrapper`, `.admin-table` untuk tabel
  - `.admin-chip` untuk badges
  - `.btn`, `.btn-default`, `.btn-outline`, `.btn-secondary` untuk tombol
  - `.admin-card-badge` untuk status badges

**CSS yang Ditambahkan di `admin.css`:**
```css
/* Badge Variants untuk Metode Pembayaran */
.admin-card-badge.cash { ... }   // Hijau untuk cash
.admin-card-badge.qris { ... }   // Biru untuk QRIS
.admin-card-badge.debit { ... }  // Kuning untuk debit
.admin-card-badge.delivery { ... } // Ungu untuk delivery

/* Container & Layout Improvements */
.container { max-width: 1280px; ... }
.admin-page-head { ... }
.admin-page-actions { ... }
.admin-chip { ... }
```

**Struktur HTML Baru:**
```html
<div class="container">
  <div class="admin-page-head">
    <div>
      <div class="admin-page-label">Operasional</div>
      <h1>Riwayat Transaksi</h1>
      <p>Deskripsi...</p>
    </div>
    <div class="admin-page-actions">
      <span class="admin-chip soft">Stats</span>
    </div>
  </div>

  <div class="admin-section-card">
    <!-- Quick filters -->
    <div class="admin-card-header">...</div>
    
    <!-- Filter form -->
    <form>...</form>
    
    <!-- Table -->
    <div class="admin-table-wrapper">
      <table class="admin-table">...</table>
    </div>
    
    <!-- Pagination -->
    <div>{{ $pesanan->links() }}</div>
  </div>
</div>
```

**Fitur:**
- ✅ Clean shadcn/ui design
- ✅ Responsive untuk mobile
- ✅ Badge warna untuk metode pembayaran (cash, qris, debit, delivery)
- ✅ Badge status (lunas, dibatalkan)
- ✅ Filter dengan date range picker
- ✅ Quick filter (Hari Ini, Kemarin, Transaksi App/Kasir)
- ✅ Jump to page pagination
- ✅ Stats chips (Total Data, Halaman, Per Halaman)
- ✅ Export Excel button

---

### **2. CSS Admin Enhancements** ✅ SELESAI

**File:** `resources/css/admin.css`

**Yang Ditambahkan:**
```css
/* Transaksi & Kasir Admin Enhancements (Shadcn/UI Style) */

/* Badge Variants untuk Metode Pembayaran */
.admin-card-badge.cash, .qris, .debit, .delivery { ... }

/* Dark mode adjustments */
body.admin-ui .admin-card-badge.cash { ... }

/* Container max-width untuk consistency */
.container { ... }

/* Admin Page Head Improvements */
.admin-page-head { ... }
.admin-page-label { ... }
.admin-page-actions { ... }
.admin-chip { ... }

/* Responsive adjustments */
@media (max-width: 768px) { ... }
```

**Design Tokens yang Digunakan:**
- `hsl(var(--card))` - Background card
- `hsl(var(--foreground))` - Text color
- `hsl(var(--muted))` - Muted backgrounds
- `hsl(var(--muted-foreground))` - Muted text
- `hsl(var(--border))` - Border color
- `hsl(var(--primary))` - Primary color
- `hsl(var(--destructive))` - Error/danger color
- `hsl(var(--chart-2))` - Success color

---

## 🚧 Yang Belum Selesai

### **3. Halaman Kasir Admin** ⏳ IN PROGRESS

**File:** `resources/views/kasir/index.blade.php`

**Status:** Masih menggunakan structure lama dengan enhanced class

**Yang Perlu Dilakukan:**
1. ✅ Sudah dibaca filenya (line 1-100)
2. ⏳ **Perlu dirombak total** menggunakan shadcn/ui design
3. ⏳ Hapus semua enhanced class
4. ⏳ Gunakan class admin standar
5. ⏳ Sederhanakan struktur HTML

**Komponen yang Perlu Dirombak:**
- Hero section dengan logo dan stats
- Quick summary card
- Promo announcements section
- Kategori navigation (sticky tabs)
- Product cards grid (per kategori)
- Bundling cards
- Summary sidebar (ringkasan pesanan realtime)
- Modal untuk varian produk
- Footer dengan tombol checkout

**Referensi Design:**
- Gunakan `.admin-section-card` untuk setiap section
- Gunakan grid layout untuk product cards
- Gunakan sticky sidebar untuk summary
- Badge untuk status stok (ready, low, empty)
- Badge untuk opsi kasir (Suhu, Sugar, Cup, Pedas)

---

## 📂 Struktur File

```
resources/
├── views/
│   ├── kasir/
│   │   └── index.blade.php          ⏳ IN PROGRESS
│   ├── transaksi/
│   │   └── index.blade.php          ✅ SELESAI
│   └── layouts/
│       └── app.blade.php            (Loading logic sudah benar)
│
├── css/
│   ├── admin.css                    ✅ UPDATED (tambah badge variants)
│   ├── kasir.css                    (Untuk kasir role, belum diubah)
│   ├── transaksi.css                (Untuk kasir role, belum diubah)
│   ├── tokens.css                   (Design tokens HSL)
│   └── components/
│       ├── _card.css
│       ├── _table.css
│       ├── _badge.css
│       └── _button.css
│
└── docs/
    └── REFACTORING-KASIR-TRANSAKSI-PROGRESS.md  (File ini)
```

---

## 🎯 Next Steps

### **Prioritas 1: Selesaikan Kasir Admin**

1. **Rombak Hero Section**
   ```html
   <div class="admin-page-head">
     <div>
       <div class="admin-page-label">Kasir</div>
       <h1>Transaksi Kasir</h1>
       <p>Pilih produk dan kelola pesanan</p>
     </div>
     <div class="admin-page-actions">
       <span class="admin-chip">Produk: X</span>
       <span class="admin-chip">Kategori: Y</span>
     </div>
   </div>
   ```

2. **Rombak Kategori Navigation**
   ```html
   <div class="admin-section-card">
     <div style="position: sticky; top: 1rem;">
       <nav style="display: flex; gap: 0.5rem; overflow-x: auto;">
         <a class="btn btn-outline" href="#kategori-1">Kategori 1</a>
         <a class="btn btn-outline" href="#kategori-2">Kategori 2</a>
       </nav>
     </div>
   </div>
   ```

3. **Rombak Product Cards**
   ```html
   <div class="admin-section-card">
     <div class="admin-card-header">
       <h3 class="admin-card-title">Nama Kategori</h3>
     </div>
     <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
       <!-- Product card -->
       <div style="border: 1px solid hsl(var(--border)); border-radius: 0.5rem; padding: 1rem;">
         <h4>Nama Produk</h4>
         <p>Rp XXX</p>
         <div>
           <span class="admin-card-badge ok">Stok: XX</span>
         </div>
         <button class="btn btn-default">Tambah</button>
       </div>
     </div>
   </div>
   ```

4. **Rombak Summary Sidebar**
   ```html
   <aside style="position: sticky; top: 1rem;">
     <div class="admin-section-card">
       <h3>Ringkasan Pesanan</h3>
       <div>
         <div>Total Item: <strong>X</strong></div>
         <div>Estimasi Total: <strong>Rp XXX</strong></div>
       </div>
       <button class="btn btn-default">Checkout</button>
     </div>
   </aside>
   ```

5. **CSS Tambahan yang Perlu Ditambah di admin.css**
   ```css
   /* Kasir Product Grid */
   .kasir-product-grid {
     display: grid;
     grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
     gap: 1rem;
   }
   
   /* Kasir Summary Sidebar */
   .kasir-summary-sidebar {
     position: sticky;
     top: 1rem;
   }
   
   /* Badge untuk Stok */
   .admin-card-badge.stock-ready { ... }
   .admin-card-badge.stock-low { ... }
   .admin-card-badge.stock-empty { ... }
   ```

### **Prioritas 2: Testing & Refinement**

1. Test di role Admin
2. Test di role Kasir (pastikan tidak broken)
3. Test responsive mobile/tablet
4. Test dark mode
5. Clean up unused CSS di kasir.css dan transaksi.css

---

## 📝 Catatan Penting

### **Design Principles Shadcn/UI:**
- **Composable:** Setiap komponen bisa dikombinasikan
- **Accessible:** Semantic HTML
- **Consistent:** Pakai design tokens HSL
- **Responsive:** Mobile-first approach
- **Clean:** Minimal classes, inline styles untuk spacing

### **Class Naming Convention:**
```css
/* Admin Components */
.admin-page-head           /* Page header */
.admin-page-label          /* Small label above title */
.admin-page-actions        /* Action buttons area */
.admin-section-card        /* Main card wrapper */
.admin-card-header         /* Card header */
.admin-card-title          /* Card title */
.admin-card-description    /* Card description */
.admin-card-badge          /* Small badge/pill */
.admin-table-wrapper       /* Table container */
.admin-table               /* Table element */
.admin-chip                /* Stats chip */

/* Buttons */
.btn                       /* Base button */
.btn-default               /* Primary button */
.btn-outline               /* Outline button */
.btn-secondary             /* Secondary button */
.btn-destructive           /* Danger button */
.btn-sm                    /* Small button */

/* Badge Variants */
.admin-card-badge.ok       /* Success/green */
.admin-card-badge.err      /* Error/red */
.admin-card-badge.warn     /* Warning/yellow */
```

### **Inline Styles untuk Spacing:**
Gunakan inline styles untuk spacing yang spesifik per halaman:
```html
<div style="margin-top: 1rem;">...</div>
<div style="display: grid; gap: 1rem;">...</div>
<div style="padding: 1rem;">...</div>
```

### **Design Tokens Reference:**
```css
/* Colors */
--background      /* Page background */
--foreground      /* Main text */
--card            /* Card background */
--card-foreground /* Card text */
--muted           /* Muted background */
--muted-foreground/* Muted text */
--border          /* Border color */
--input           /* Input border */
--primary         /* Primary color */
--primary-foreground
--secondary       /* Secondary color */
--destructive     /* Error color */
--chart-2         /* Success color */
--chart-4         /* Warning color */
```

---

## 🔗 Referensi

- **Shadcn/UI Documentation:** https://ui.shadcn.com/
- **Components:**
  - Badge: https://ui.shadcn.com/docs/components/badge
  - Card: https://ui.shadcn.com/docs/components/card
  - Table: https://ui.shadcn.com/docs/components/table
  - Button: https://ui.shadcn.com/docs/components/button

---

## ✅ Checklist Progress

- [x] Analisis struktur CSS yang ada
- [x] Identifikasi masalah
- [x] Rombak halaman Transaksi (Admin)
- [x] Tambah CSS enhancements di admin.css
- [x] Rombak halaman Kasir Admin — hapus semua *-enhanced class dan Material Icons
- [x] kasir.css rewrite menggunakan CSS variables (--kasir-* tokens)
- [x] body.admin-ui.kasir-ui dark theme variable override
- [x] Fix modal backdrop kasir (konflik dengan admin CSS)
- [x] Fix harga visibility (meta-pill.price kontras)
- [x] Ganti card-note verbose → card-varian-tags pill kecil
- [x] Clean up — hapus 860 baris *-enhanced CSS dari kasir.css
- [x] Fix hero putih (kasir-admin-hero background di admin dark theme)
- [ ] Testing semua fitur kasir (tambah produk, checkout, shift)
- [ ] Testing responsive mobile
- [ ] Documentation final

**Last Updated:** 2026-06-08  
**Status:** ✅ 85% Complete — Siap Testing

---

**Last Updated:** 2026-06-07 16:10 UTC  
**Status:** 🚧 In Progress (50% Complete)
