# Dashboard Chart Setup - React & Recharts

## Perubahan yang Dilakukan

### 1. Komponen React
Dibuat komponen `ChartAreaInteractive` menggunakan React dan Recharts di:
- `resources/js/components/ChartAreaInteractive.jsx`

### 2. Entry Point
- `resources/js/dashboard-chart.jsx` - Mount komponen chart ke dashboard
- `resources/js/app.jsx` - Renamed dari app.js untuk support JSX

### 3. Vite Configuration
Updated `vite.config.js` untuk include:
- `resources/js/app.jsx`
- `resources/js/dashboard-chart.jsx`

### 4. Dashboard Blade Template
Updated `resources/views/dashboard/index.blade.php`:
- Mengganti custom SVG chart dengan React component
- Menambahkan div dengan id `react-dashboard-chart` dan data attributes
- Menghapus JavaScript chart rendering yang lama

### 5. Layout Update
Updated `resources/views/layouts/app.blade.php`:
- Mengubah `@vite(['resources/css/app.css', 'resources/js/app.js'])` 
- Menjadi `@vite(['resources/css/app.css', 'resources/js/app.jsx'])`

## Fitur Chart Baru

### Interactive Features
- **Filter waktu**: 7 hari, 30 hari, 90 hari terakhir
- **Tooltip interaktif**: Menampilkan tanggal, omzet (Rupiah), dan jumlah transaksi
- **Gradient area**: Visual yang lebih modern dengan gradient fill
- **Responsive**: Otomatis menyesuaikan ukuran layar
- **Legend**: Menampilkan keterangan data

### Data Format
Chart menerima data dalam format:
```json
[
  {
    "date": "2024-04-01",
    "value": 1000000,
    "meta": "10 trx"
  }
]
```

## Cara Build Assets

### Development
```bash
npm run dev
```

### Production
```bash
npm run build
```

## Dependencies
- React 19.2.7
- React DOM 19.2.7
- Recharts 3.8.1
- Vite 7.0.7
- @vitejs/plugin-react 5.2.0

## Cara Menggunakan

1. Build assets terlebih dahulu:
   ```bash
   npm run build
   ```

2. Pastikan Laravel app sudah running:
   ```bash
   php artisan serve
   ```

3. Buka dashboard admin untuk melihat chart baru

## Troubleshooting

### Chart tidak muncul
- Pastikan `npm run build` berhasil tanpa error
- Cek console browser untuk error JavaScript
- Pastikan data `$salesTrend` tersedia dari controller

### Build error
- Hapus folder `node_modules` dan jalankan `npm install` lagi
- Pastikan semua dependencies terinstall dengan benar

### Styling tidak sesuai
- Chart menggunakan CSS variables seperti `--chart-1`, `--border`, `--muted-foreground`
- Pastikan CSS variables ini sudah didefinisikan di `resources/css/admin.css`

## Catatan
- Chart ini menggantikan implementasi SVG custom yang lama
- Data tetap dikirim dari Laravel controller seperti sebelumnya
- Format data disesuaikan di blade template (label → date)
