# UAT Kasir Checklist

Gunakan checklist ini setelah update terakhir (offline sync, varian suhu, cup large +2000, role kasir transaksi).

## 1) Persiapan

- [ ] Jalankan migrasi terbaru:
  - `php artisan migrate`
- [ ] Bersihkan cache view/config:
  - `php artisan optimize:clear`
- [ ] Jalankan server lokal:
  - `php artisan serve --host=0.0.0.0 --port=8000`
- [ ] Pastikan HP dan laptop dalam jaringan Wi-Fi yang sama.

## 2) Login & Akses Role

- [ ] Login sebagai `kasir` dari HP.
- [ ] Menu `Kasir` bisa diakses.
- [ ] Menu `Transaksi` bisa diakses.
- [ ] Di detail transaksi, kasir **tidak** melihat tombol `Batal/Restore`.
- [ ] Endpoint export tidak bisa diakses kasir (`/transaksi/export/excel` => 403).

## 3) Kasir Online (Normal)

- [ ] Pilih produk biasa, checkout sukses.
- [ ] Pilih produk dengan opsi suhu: `Hot`, `Es`, `Less Es` semua valid.
- [ ] Untuk suhu `Hot`, sugar/cup mengikuti aturan saat ini (cup regular).
- [ ] Untuk `Es/Less Es`, sugar & cup muncul sesuai konfigurasi produk.
- [ ] Tambah 2 varian produk sama (contoh Americano Hot + Americano Es) bisa masuk sebagai baris terpisah.

## 4) Harga Cup Large (+2000)

- [ ] Buat 1 item `Regular`, catat harga.
- [ ] Buat item sama `Large`, pastikan naik `Rp 2.000` per item.
- [ ] Cek di:
  - ringkasan sementara kasir,
  - halaman checkout,
  - detail transaksi/nota.
- [ ] Nilai `harga_satuan` transaksi large tersimpan sesuai kenaikan.

## 5) Diskon Online

- [ ] Pilih diskon aktif, total berkurang sesuai aturan.
- [ ] Diskon dengan minimal belanja tidak bisa dipakai jika subtotal kurang.

## 6) Offline Transaksi (HP)

- [ ] Buka checkout saat online (agar service worker/cache siap).
- [ ] Putus internet HP.
- [ ] Lakukan transaksi dan submit (offline queue bertambah).
- [ ] Nyalakan internet lagi.
- [ ] Tekan `Sync Transaksi Offline` atau tunggu auto-sync.
- [ ] Transaksi offline masuk ke server tanpa duplikasi (`offline_ref` unik).

## 7) Offline Snapshot Harga & Diskon

- [ ] Simulasikan transaksi offline dengan produk/cup large.
- [ ] Ubah harga produk di server sebelum sync.
- [ ] Sync offline: total transaksi harus tetap sesuai harga saat offline (snapshot).
- [ ] Simulasikan diskon offline lalu nonaktifkan diskon sebelum sync.
- [ ] Sync offline: diskon snapshot tetap diterapkan pada transaksi tersebut.

## 8) Stok & Integritas

- [ ] Setelah checkout sukses, stok berkurang sesuai qty.
- [ ] Admin batalkan transaksi, stok kembali.
- [ ] Admin restore transaksi, stok berkurang lagi.
- [ ] Tidak ada stok minus.

## 9) Nota / Print

- [ ] Print nota dari kasir sukses.
- [ ] Ganti ukuran 58mm/80mm sukses.  
- [ ] Detail opsi varian tampil benar (`Hot/Es/Less Es`, sugar, cup).

## 10) Closing UAT

- [ ] `php artisan test` semua pass.
- [ ] Cek log error:
  - `storage/logs/laravel.log`
- [ ] Tidak ada error kritikal pada alur kasir utama.

## Catatan Bug (isi saat test)

- Waktu:
- Role/User:
- Langkah:
- Hasil aktual:
- Hasil yang diharapkan:
- Screenshot/Log:

