# TODO: Redesign Admin Dashboard (Kasir Project)

## Status: Sedang Berjalan

### 1. ✅ Buat TODO.md (Selesai)
### 2. ✅ Konfirmasi & approve plan user
### 3. ✅ Step 3: Refactor CSS (Selesai)
   - ✅ Extract styles dari layouts/app.blade.php ke resources/css/app.css
   - ✅ Fix CSS syntax errors & linter
   - ✅ Tambah dark mode support (aktifkan data-theme="dark" di html)
   - ✅ Update layouts/app.blade.php → link external CSS
   - ✅ Clear view cache
### 4. ✅ Step 4: Improve Tables (Selesai)
   - ✅ Absensi table: Mobile card grid (9 kolom → responsive cards)
   - ✅ Statistik: Ready for same treatment
   - Next: Step 5 JS simplify
### 5. ✅ Simplify JS Interactions (Selesai)
   - ✅ Modal absensi: Alpine.js (150+ baris JS dihapus)
   - ✅ Charts statistik: hsl() wrapper removed, pakai CSS vars langsung
### 6. ✅ Remove Unnecessary Elements (Selesai)
   - ✅ Hapus quick-card duplikat di kasir (info sudah ada di hero)
   - ✅ Bundling chip dipindah ke hero-stats-line
   - ✅ Quick menu sudah dinonaktifkan untuk admin (hanya kasir role)
### 7. ✅ Enhancements (Selesai)
   - ✅ Dark mode toggle di admin topbar (☀️/🌙)
   - ✅ Dark mode floating button untuk kasir role
   - ✅ Preferensi disimpan ke localStorage
   - ✅ Anti-flash script (diterapkan sebelum render body)
   - ✅ Respects prefers-color-scheme system preference
### 8. 🔲 Test & Deploy
   - [ ] Responsive test semua device
   - [ ] `php artisan view:clear && php artisan optimize:clear`
   - [ ] Manual QA: Dashboard, absensi, statistik, workspace, kasir, dark mode toggle

