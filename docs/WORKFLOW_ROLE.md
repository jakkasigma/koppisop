# Catatan Workflow Project Kasir

Dokumen ini merangkum alur kerja aplikasi berdasarkan struktur route, controller, model, dan view yang ada di project. Fokusnya adalah pembagian akses dan aktivitas per role.

## Gambaran Umum

Aplikasi ini adalah sistem kasir dan operasional cafe berbasis Laravel untuk KopiSop. Modul utamanya meliputi POS kasir, transaksi, shift kasir, dashboard admin, master data, absensi, jadwal karyawan, portal staff, pengajuan izin, chat, pengumuman, payroll, setoran/keuangan, serta pengaturan struk dan tema.

Ada tiga kelompok pengguna:

- `admin`: login lewat `/login`, mengelola dashboard, master data, operasional, laporan, dan pengaturan.
- `kasir`: login lewat `/login`, wajib mulai shift sebelum memakai POS kasir.
- `staff/karyawan`: login lewat `/staff/login` memakai data karyawan, nomor telepon, dan PIN. Staff tidak memakai tabel `users`, tetapi session portal staff.

## Alur Autentikasi

### Admin dan Kasir

1. User membuka `/login`.
2. Sistem memvalidasi email/password lewat `AuthController`.
3. Setelah login, redirect ditentukan oleh `App\Support\AuthRedirects`:
   - Admin diarahkan ke `/dashboard`.
   - Kasir diarahkan ke `/kasir` jika punya shift aktif.
   - Kasir diarahkan ke `/kasir/shift/start` jika belum mulai shift.
4. Middleware `role` membatasi halaman berdasarkan `User.role`.
5. Kasir juga dibatasi middleware `kasir.shift` untuk memastikan shift sudah dimulai sebelum masuk POS.

### Staff/Karyawan

1. Staff membuka `/staff/login`.
2. Login memakai identitas karyawan dan PIN.
3. Session menyimpan `staff_karyawan_id`, `staff_karyawan_name`, dan aktivitas terakhir.
4. Middleware `staff.portal`:
   - Mengarahkan staff yang belum login ke `/staff/login`.
   - Memutus session jika idle lebih dari 10 menit.
   - Menolak karyawan yang tidak aktif.
   - Menyediakan data karyawan dan jumlah pesan belum dibaca ke request/view.

## Workflow Role Admin

Admin adalah role pengelola penuh. Menu admin dibagi menjadi Operasional, Master Data, dan Pengaturan.

### Operasional Harian

1. Masuk ke `/dashboard`.
2. Melihat ringkasan penjualan, transaksi, absensi, jadwal, pengumuman, dan indikator operasional.
3. Membuka Statistik untuk analisis omzet, metode pembayaran, kategori, tren harian/bulanan, dan export Excel.
4. Membuka Keuangan untuk memantau setoran, mencatat setoran, memberi catatan, koreksi nominal, dan export.
5. Membuka Transaksi untuk melihat daftar transaksi, detail nota, export, membatalkan transaksi, atau restore transaksi.
6. Membuka Riwayat Shift untuk melihat pembukaan/penutupan shift kasir beserta kas awal, penjualan, pengeluaran, dan ringkasan tutup shift.

### Manajemen Kasir

1. Admin dapat membuka POS lewat `/admin/kasir`.
2. Admin bisa membuat preview dan checkout transaksi dari mode kasir admin.
3. Admin tidak perlu melewati flow mulai shift kasir biasa.
4. Struk/nota/checker tetap memakai alur transaksi yang sama.

### Absensi

1. Admin membuka `/dashboard/absensi`.
2. Admin memfilter data absensi berdasarkan tanggal/karyawan/status koreksi.
3. Admin memverifikasi atau menolak absensi.
4. Admin menangani koreksi pulang:
   - Approve koreksi.
   - Reject koreksi.
   - Input koreksi manual.
5. Admin mengatur absensi di `/dashboard/absensi/pengaturan`, termasuk:
   - Kewajiban login staff.
   - Selfie.
   - Geofence dan radius.
   - Jam shift full time/part time.
   - Toleransi terlambat.
   - Jendela check-in.
6. Admin dapat export absensi ke Excel.

### Jadwal dan Self Scheduling

1. Admin membuka `/dashboard/jadwal`.
2. Admin melihat jadwal bulanan dan status absensi terkait jadwal.
3. Admin mengedit jadwal per tanggal.
4. Admin mengatur self-schedule di `/dashboard/jadwal/self-schedule`, termasuk periode buka, tanggal yang bisa dipilih, kapasitas shift, batas mingguan/bulanan, dan aturan pembatalan.
5. Admin memproses permintaan tukar jadwal di `/dashboard/jadwal/tukar`.
6. Saat permintaan tukar disetujui, sistem menukar jadwal antar karyawan dan mencatat pesan/notifikasi.

### Staff, Izin, Chat, dan Pengumuman

1. Admin mengelola karyawan di `/karyawan`.
2. Admin membuat/mengubah data karyawan, status aktif, jenis kerja full time/part time, gaji, nomor telepon, dan PIN.
3. Admin membaca aktivitas staff di `/dashboard/aktivitas-staf`.
4. Admin membuka chat karyawan di `/dashboard/chat`.
5. Admin memproses pengajuan izin/sakit di `/dashboard/izin`:
   - Approve.
   - Reject.
   - Kirim pesan lanjutan.
6. Admin membuat pengumuman di `/dashboard/pengumuman`.
7. Pengumuman dapat dibaca staff sesuai target role/jabatan dan dapat ditandai sudah dibaca admin.

### Payroll/Gaji

1. Admin membuka `/dashboard/gaji`.
2. Admin memilih periode payroll.
3. Admin melihat ringkasan karyawan dan status slip.
4. Admin membuka detail gaji karyawan.
5. Admin menyimpan slip gaji per karyawan/periode.
6. Admin mengatur kebijakan payroll seperti potongan alpha, potongan terlambat, dan rate lembur.
7. Admin dapat membuka versi print slip.

### Master Data

1. Admin mengelola kategori produk.
2. Admin mengelola produk, stok, harga, opsi, varian, dan custom option group.
3. Admin mengelola master opsi kasir untuk pilihan reusable.
4. Admin mengelola pelanggan.
5. Admin mengelola diskon dan promo bundling.
6. Saat diskon/bundling dibuat atau berakhir, controller memiliki alur pengumuman terkait promo.

### Pengaturan

1. Admin mengatur struk di `/pengaturan-struk`.
2. Admin mengatur tema aplikasi di `/pengaturan-tema`.
3. Admin mengatur profil cafe di `/profil-cafe`.
4. Admin mengatur ruang kerja di `/dashboard/ruang-kerja`.
5. Admin mengunduh/melihat QR share absensi/staff di `/dashboard/share-qr`.

## Workflow Role Kasir

Kasir adalah role operasional POS. Aksesnya terbatas pada POS, shift, receipt, checker, absensi kasir, dan transaksi.

### Mulai Shift

1. Kasir login lewat `/login`.
2. Jika belum ada shift aktif, kasir diarahkan ke `/kasir/shift/start`.
3. Kasir memilih sesi shift aktif.
4. Sistem menetapkan kas awal dari pengaturan atau input.
5. Setelah shift aktif, kasir dapat membuka `/kasir`.

### Transaksi POS

1. Kasir membuka `/kasir`.
2. Kasir memilih produk.
3. Kasir memilih opsi produk seperti suhu, gula, cup, atau custom option jika tersedia.
4. Kasir memilih diskon/promo yang aktif jika memenuhi syarat.
5. Kasir melakukan preview order.
6. Kasir masuk checkout.
7. Kasir memilih metode pembayaran yang diaktifkan di pengaturan:
   - Cash.
   - QRIS.
   - Debit.
   - ShopeeFood.
   - GoFood.
   - GrabFood.
8. Setelah submit checkout, sistem membuat pesanan dan detail pesanan.
9. Stok produk berkurang sesuai item.
10. Kasir dapat membuka receipt, nota, atau checker.

### Offline Transaction

1. POS mendukung sync transaksi offline melalui `/kasir/offline/sync`.
2. Transaksi offline memakai snapshot harga/diskon saat dibuat.
3. `offline_ref` digunakan untuk mencegah duplikasi saat sync.
4. Setelah koneksi kembali, transaksi offline disinkronkan ke server.

### Laporan dan Tutup Shift

1. Kasir membuka `/kasir/shift/report`.
2. Kasir melihat ringkasan shift berjalan.
3. Kasir dapat mencatat pengeluaran shift.
4. Kasir dapat menghapus pengeluaran yang masih terkait shift aktif miliknya.
5. Kasir membuka `/kasir/shift/close`.
6. Sistem menghitung ringkasan penjualan, metode pembayaran, cash, delivery, pengeluaran, dan kas akhir.
7. Kasir menutup shift.
8. Sistem menyimpan ringkasan close dan menyediakan struk shift.

### Akses Transaksi

1. Kasir dapat membuka daftar transaksi di `/transaksi`.
2. Kasir dapat melihat detail transaksi dan nota.
3. Kasir tidak punya akses untuk membatalkan atau restore transaksi karena aksi itu berada di route admin.
4. Export transaksi juga berada di kelompok admin/kasir route, tetapi pembatalan/restore hanya admin.

## Workflow Role Staff/Karyawan

Staff adalah karyawan operasional yang memakai portal mobile. Staff tidak login sebagai `User`, tetapi sebagai `Karyawan`.

### Login Portal Staff

1. Staff membuka `/staff/login`.
2. Staff memasukkan nomor telepon/identitas dan PIN.
3. Jika valid dan aktif, staff masuk ke `/staff`.
4. Staff dapat logout dari drawer/menu portal.
5. Session otomatis habis setelah idle 10 menit.

### Beranda Staff

1. Staff membuka `/staff`.
2. Staff melihat ringkasan personal, pengumuman, jadwal, absensi, notifikasi, dan pesan terkait.
3. Staff dapat menandai pengumuman sebagai sudah dibaca.
4. Staff membuka notifikasi dan diarahkan ke konteks terkait jika tersedia.

### Absensi Staff

1. Staff membuka menu Absen atau `/absen`.
2. Staff memilih Absen Masuk atau Absen Pulang.
3. Sistem memvalidasi karyawan, shift, jendela waktu, selfie/geofence jika diaktifkan, dan status absensi hari itu.
4. Staff melakukan check-in.
5. Saat pulang, staff melakukan check-out.
6. Jika waktu pulang perlu dikoreksi, staff dapat mengajukan koreksi pulang.
7. Admin memproses koreksi tersebut dari dashboard absensi.

### Jadwal

1. Staff membuka `/staff/jadwal`.
2. Staff melihat jadwal bulanan miliknya.
3. Halaman jadwal lama `/jadwal` diarahkan ke staff portal.
4. Staff dapat melihat relasi jadwal dengan status absensi.

### Ambil Jadwal Sendiri

1. Staff membuka `/staff/ambil-jadwal`.
2. Sistem menampilkan slot berdasarkan periode self-schedule, employment type, kapasitas shift, dan batas mingguan/bulanan.
3. Staff memilih slot.
4. Sistem mengecek kapasitas, bentrok jadwal, batas minimal/maksimal, dan periode yang dibuka.
5. Staff dapat membatalkan slot jika aturan pembatalan mengizinkan.

### Tukar Jadwal

1. Staff membuka `/staff/tukar-jadwal`.
2. Staff melihat jadwal yang bisa ditukar.
3. Staff mencari jadwal staff lain yang tersedia untuk swap.
4. Staff mengirim permintaan tukar jadwal.
5. Staff penerima dapat approve atau reject dari portal.
6. Admin juga dapat approve atau reject permintaan tukar dari dashboard.
7. Jika disetujui, jadwal kedua staff ditukar.

### Izin/Sakit

1. Staff membuka `/staff/izin`.
2. Staff membuat pengajuan izin/sakit dengan tanggal, tipe, dan alasan.
3. Pengajuan masuk ke dashboard admin.
4. Admin approve atau reject.
5. Staff melihat status dan pesan terkait pengajuan.

### Pesan

1. Staff membuka `/staff/pesan`.
2. Sistem menampilkan thread yang relevan:
   - Chat langsung dengan admin.
   - Thread izin.
   - Thread tukar jadwal.
   - Thread absensi/koreksi jika ada.
3. Staff membuka thread dan mengirim pesan.
4. Sistem menghitung unread message berdasarkan `staff_message_reads`.

### Payroll Staff

1. Staff membuka `/staff/slip-gaji`.
2. Staff melihat daftar periode slip gaji dan ringkasan live.
3. Staff membuka detail periode tertentu.
4. Staff dapat membuka versi print slip gaji.
5. Komponen gaji mengikuti data karyawan, absensi, keterlambatan, lembur, izin disetujui, dan kebijakan payroll.

### Profil Staff

1. Staff membuka `/staff/profil`.
2. Staff melihat data personal dan status kerja.
3. Staff membuka `/staff/profil/edit` untuk memperbarui profil yang diizinkan.

## Entitas Data Penting

- `User`: akun login admin/kasir.
- `Karyawan`: data staff, jabatan, nomor telepon, PIN, status aktif, tipe kerja, dan gaji.
- `Produk`, `Kategori`, `MasterOpsiKasir`: katalog dan konfigurasi opsi produk.
- `Diskon`, `PromoBundling`: promo transaksi.
- `Pesanan`, `DetailPesanan`: transaksi dan item transaksi.
- `KasirShiftSession`, `KasirShiftPengeluaran`: sesi shift kasir dan pengeluaran shift.
- `Absensi`: check-in/check-out, verifikasi, sumber absensi, selfie/geofence, dan koreksi pulang.
- `JadwalKaryawan`, `JadwalTukarRequest`: jadwal staff dan permintaan tukar shift.
- `LeaveRequest`: pengajuan izin/sakit.
- `StaffMessage`, `StaffMessageRead`: percakapan dan status baca.
- `Announcement`, `AnnouncementRead`, `AnnouncementAdminRead`: pengumuman dan status baca.
- `PayrollSlip`: slip gaji per periode.
- `StaffActivityLog`, `StaffNotification`: log aktivitas dan notifikasi staff.
- `StrukSetting`: pengaturan struk, pembayaran, pajak, shift, absensi, self-schedule, tema, dan payroll.
- `CafeProfile`: profil cafe.

## Ringkasan Hak Akses

| Modul | Admin | Kasir | Staff |
| --- | --- | --- | --- |
| Dashboard operasional | Ya | Tidak | Tidak |
| POS kasir | Ya, mode admin | Ya, wajib shift aktif | Tidak |
| Mulai/tutup shift | Lihat history | Ya | Tidak |
| Daftar/detail transaksi | Ya | Ya | Tidak |
| Batal/restore transaksi | Ya | Tidak | Tidak |
| Master produk/kategori/promo | Ya | Tidak | Tidak |
| Absensi publik/staff | Kelola dan verifikasi | Bisa absen kasir | Ya |
| Jadwal | Kelola | Tidak | Lihat, ambil, tukar |
| Izin/sakit | Approve/reject | Tidak | Ajukan |
| Chat/pesan | Ya | Tidak | Ya |
| Pengumuman | Kelola | Dibaca di POS jika ditargetkan | Dibaca di portal |
| Payroll | Kelola dan print | Tidak | Lihat dan print slip sendiri |
| Pengaturan struk/tema/cafe | Ya | Tidak | Tidak |

## Catatan Teknis Workflow

- `/` akan redirect ke tujuan sesuai role jika user sudah login, atau ke `/login` jika belum.
- Staff portal punya auth sendiri dan tidak bergantung pada `auth` Laravel default.
- Kasir tidak bisa masuk POS sebelum ada `KasirShiftSession` aktif.
- Beberapa route kasir seperti receipt, nota, checker, dan struk shift dapat diakses oleh admin/kasir sesuai middleware `auth` dan `role:admin,kasir`.
- Pengaturan operasional banyak tersentral di `StrukSetting::current()`.
- Full time dan part time memiliki durasi shift dan slot jadwal berbeda:
  - Full time default 8 jam.
  - Part time default 4,5 jam.
- Sistem memakai PWA/service worker untuk pengalaman mobile dan dukungan offline pada kasir.

