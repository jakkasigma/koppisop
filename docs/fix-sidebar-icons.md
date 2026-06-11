# Fix Sidebar Icons - Admin Dashboard

## Masalah
Icon di sidebar navigasi admin tidak muncul dengan baik karena:
1. Class CSS `.admin-nav-icon` tidak memiliki styling
2. Material Symbols font tidak dioptimalkan untuk icon di sidebar
3. Brand mark (logo) juga memerlukan styling khusus

## Solusi yang Diterapkan

### 1. Menambahkan Styling untuk Icon Navigasi
**File**: `resources/css/components/_sidebar.css`

```css
/* Sebelum */
.admin-nav-item svg {
  @apply h-5 w-5 shrink-0;
}

/* Sesudah */
.admin-nav-item svg,
.admin-nav-icon {
  @apply h-5 w-5 shrink-0;
}

/* Material Symbols Icon Support */
.admin-nav-icon.material-symbols-outlined {
  @apply text-lg leading-none;
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20;
}

.admin-nav-item.active .admin-nav-icon.material-symbols-outlined {
  font-variation-settings: 'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 20;
}
```

### 2. Menambahkan Styling untuk Brand Mark
```css
.admin-brand-mark.material-symbols-outlined {
  @apply text-2xl leading-none;
  font-variation-settings: 'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24;
}
```

## Fitur yang Ditambahkan

1. **Icon Support**: Semua icon dengan class `.admin-nav-icon` sekarang memiliki ukuran yang konsisten (20px)
2. **Material Symbols Optimization**: 
   - Icon normal: outline style dengan weight 400
   - Icon active: filled style dengan weight 600
   - Ukuran optical size disesuaikan (20px untuk nav, 24px untuk brand)
3. **Visual Hierarchy**: Icon aktif lebih bold dan filled untuk membedakan halaman yang sedang aktif

## Testing
- [x] Icon di sidebar muncul dengan benar
- [x] Icon active state terlihat jelas (filled)
- [x] Brand mark (logo coffee) terlihat dengan baik
- [x] CSS berhasil di-compile dengan Vite

## File yang Dimodifikasi
1. `resources/css/components/_sidebar.css` - Menambahkan styling untuk icon dan brand mark

## Catatan
- Material Symbols font sudah di-load di `resources/views/layouts/app.blade.php` (line 31)
- CSS tokens sudah terdefinisi dengan benar di `resources/css/tokens.css`
- Semua icon menggunakan class `material-symbols-outlined` dari Google Fonts

## Tanggal
2026-06-05
