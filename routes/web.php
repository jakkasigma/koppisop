<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AdminChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardLeaveController;
use App\Http\Controllers\DashboardPayrollController;
use App\Http\Controllers\DashboardMessageController;
use App\Http\Controllers\DiskonController;
use App\Http\Controllers\DashboardStaffActivityController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\KasirShiftController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\MasterOpsiKasirController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\CafeProfileController;
use App\Http\Controllers\PromoBundlingController;
use App\Http\Controllers\StaffLeaveController;
use App\Http\Controllers\StaffMessageController;
use App\Http\Controllers\StrukSettingController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\StaffPortalController;
use App\Http\Controllers\StaffPayrollController;
use App\Http\Controllers\StaffSelfScheduleController;
use App\Http\Controllers\AnnouncementController;
use App\Support\AuthRedirects;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $user = $request->user();

    if ($user) {
        return redirect()->to(AuthRedirects::urlFor($user));
    }

    return redirect()->route('login');
});

Route::get('/absen', [AbsensiController::class, 'form'])->name('absen.form');
Route::get('/absen/masuk', [AbsensiController::class, 'checkInForm'])->name('absen.masuk.page');
Route::post('/absen/masuk', [AbsensiController::class, 'masuk'])->name('absen.masuk');
Route::get('/absen/pulang', [AbsensiController::class, 'checkOutForm'])->name('absen.pulang.page');
Route::post('/absen/pulang', [AbsensiController::class, 'pulang'])->name('absen.pulang');
Route::post('/absen/{absensi}/koreksi-pulang', [AbsensiController::class, 'requestCheckoutCorrection'])->name('absen.checkout_correction.request');

Route::get('/staff/login', [StaffPortalController::class, 'showLogin'])->name('staff.login');
Route::post('/staff/login', [StaffPortalController::class, 'login'])->name('staff.login.submit');
Route::post('/staff/logout', [StaffPortalController::class, 'logout'])->name('staff.logout');
Route::middleware('staff.portal')->group(function (): void {
    Route::get('/staff', [StaffPortalController::class, 'home'])->name('staff.home');
    Route::get('/staff/profil', [StaffPortalController::class, 'profile'])->name('staff.profile');
    Route::get('/staff/profil/edit', [StaffPortalController::class, 'editProfile'])->name('staff.profile.edit');
    Route::post('/staff/profil', [StaffPortalController::class, 'updateProfile'])->name('staff.profile.update');
    Route::get('/staff/jadwal', [StaffPortalController::class, 'jadwal'])->name('staff.jadwal');
    Route::get('/staff/riwayat', [StaffPortalController::class, 'history'])->name('staff.history');
    Route::get('/staff/slip-gaji', [StaffPayrollController::class, 'index'])->name('staff.payroll.index');
    Route::get('/staff/slip-gaji/periode/{period}', [StaffPayrollController::class, 'showPeriod'])->name('staff.payroll.period');
    Route::get('/staff/slip-gaji/{payrollSlip}/print', [StaffPayrollController::class, 'print'])->name('staff.payroll.print');
    Route::get('/staff/slip-gaji/{payrollSlip}', [StaffPayrollController::class, 'show'])->name('staff.payroll.show');
    Route::post('/staff/pengumuman/{announcement}/read', [StaffPortalController::class, 'readAnnouncement'])->name('staff.announcement.read');
    Route::get('/staff/notifikasi', [StaffPortalController::class, 'notificationsIndex'])->name('staff.notifications.index');
    Route::get('/staff/notifikasi/{notification}', [StaffPortalController::class, 'openNotification'])->name('staff.notifications.open');
    Route::get('/staff/izin', [StaffLeaveController::class, 'index'])->name('staff.leave.index');
    Route::post('/staff/izin', [StaffLeaveController::class, 'store'])->name('staff.leave.store');
    Route::get('/staff/pesan', [StaffMessageController::class, 'index'])->name('staff.messages.index');
    Route::get('/staff/pesan/{type}/{id}', [StaffMessageController::class, 'show'])->name('staff.messages.show');
    Route::post('/staff/pesan/{type}/{id}', [StaffMessageController::class, 'store'])->name('staff.messages.store');
    Route::get('/staff/ambil-jadwal', [StaffSelfScheduleController::class, 'index'])->name('staff.self_schedule');
    Route::get('/staff/tukar-jadwal', [StaffSelfScheduleController::class, 'swapIndex'])->name('staff.swap.index');
    Route::get('/staff/tukar-jadwal/available', [StaffSelfScheduleController::class, 'availableForSwap'])->name('staff.swap.available');
    Route::post('/staff/ambil-jadwal', [StaffSelfScheduleController::class, 'pick'])->name('staff.self_schedule.pick');
    Route::post('/staff/ambil-jadwal/batal', [StaffSelfScheduleController::class, 'cancel'])->name('staff.self_schedule.cancel');
    Route::post('/staff/ambil-jadwal/tukar', [StaffSelfScheduleController::class, 'requestSwap'])->name('staff.self_schedule.swap');
    Route::post('/staff/ambil-jadwal/tukar/{swap}/approve', [StaffSelfScheduleController::class, 'approveSwapByStaff'])->name('staff.self_schedule.swap.approve');
    Route::post('/staff/ambil-jadwal/tukar/{swap}/reject', [StaffSelfScheduleController::class, 'rejectSwapByStaff'])->name('staff.self_schedule.swap.reject');
});

// Legacy public jadwal page is unified into Staff Portal.
// Keep a redirect so old bookmarks/links don't confuse users.
Route::get('/jadwal', function (\Illuminate\Http\Request $request) {
    $bulan = (string) $request->query('bulan', '');
    $params = [];
    if ($bulan !== '' && preg_match('/^\d{4}-\d{2}$/', $bulan)) {
        $params['bulan'] = $bulan;
    }
    return redirect()->route('staff.jadwal', $params);
})->name('jadwal.form');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::middleware(['auth', 'role:admin,kasir'])->group(function (): void {
    Route::get('/kasir/absen', [AbsensiController::class, 'kasirForm'])->name('kasir.absen.form');
    Route::post('/kasir/absen', [AbsensiController::class, 'kasirMasuk'])->name('kasir.absen.store');

    Route::middleware('role:kasir')->group(function (): void {
        Route::get('/kasir/shift/start', [KasirShiftController::class, 'start'])->name('kasir.shift.start');
        Route::post('/kasir/shift/start', [KasirShiftController::class, 'store'])->name('kasir.shift.store');
        Route::get('/kasir/shift/report', [KasirShiftController::class, 'report'])->name('kasir.shift.report');
        Route::post('/kasir/shift/report/expense', [KasirShiftController::class, 'addExpense'])->name('kasir.shift.expense.store');
        Route::delete('/kasir/shift/report/expense/{expense}', [KasirShiftController::class, 'deleteExpense'])->name('kasir.shift.expense.destroy');
        Route::get('/kasir/shift/close', [KasirShiftController::class, 'closePreview'])->name('kasir.shift.close');
        Route::post('/kasir/shift/close', [KasirShiftController::class, 'close'])->name('kasir.shift.close.submit');
    });

    Route::middleware(['kasir.shift', 'role:kasir'])->group(function (): void {
        Route::get('/kasir', [KasirController::class, 'index'])->name('kasir.index');
        Route::post('/kasir/preview', [KasirController::class, 'preview'])->name('kasir.preview');
        Route::get('/kasir/checkout', [KasirController::class, 'checkoutPage'])->name('kasir.checkout_page');
        Route::post('/kasir/checkout', [KasirController::class, 'submitCheckout'])->name('kasir.checkout_submit');
        Route::post('/kasir/offline/sync', [KasirController::class, 'syncOfflineTransaction'])->name('kasir.offline_sync');
    });

    Route::get('/kasir/shift/{shift}/struk', [KasirShiftController::class, 'struk'])->name('kasir.shift.struk');

    Route::get('/kasir/receipt/{transaksi}', [KasirController::class, 'receipt'])->name('kasir.receipt');
    Route::get('/kasir/receipt/{transaksi}/nota', [KasirController::class, 'nota'])->name('kasir.nota');
    Route::get('/kasir/checker/{transaksi}', [KasirController::class, 'checker'])->name('kasir.checker');

    Route::middleware('role:admin')->prefix('admin/kasir')->name('admin.kasir.')->group(function (): void {
        Route::get('/', [KasirController::class, 'index'])->name('index');
        Route::post('/preview', [KasirController::class, 'preview'])->name('preview');
        Route::get('/checkout', [KasirController::class, 'checkoutPage'])->name('checkout_page');
        Route::post('/checkout', [KasirController::class, 'submitCheckout'])->name('checkout_submit');
    });

    Route::get('/transaksi', [TransaksiController::class, 'index'])->name('transaksi.index');
    Route::get('/transaksi/export/excel', [TransaksiController::class, 'exportExcel'])->name('transaksi.export_excel');
    Route::get('/transaksi/{transaksi}', [TransaksiController::class, 'show'])->name('transaksi.show');
    Route::get('/transaksi/{transaksi}/nota', [TransaksiController::class, 'nota'])->name('transaksi.nota');
});

Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/master-data', [DashboardController::class, 'masterData'])->name('master.index');
    Route::get('/dashboard/absensi', [AbsensiController::class, 'index'])->name('dashboard.absensi');
    Route::get('/dashboard/absensi/export/excel', [AbsensiController::class, 'exportExcel'])->name('dashboard.absensi.export_excel');
    Route::get('/dashboard/absensi/pengaturan', [AbsensiController::class, 'settingsForm'])->name('dashboard.absensi.settings');
    Route::post('/dashboard/absensi/settings', [AbsensiController::class, 'updateSettings'])->name('dashboard.absensi.settings.update');
    Route::post('/dashboard/absensi/{absensi}/verify', [AbsensiController::class, 'verify'])->name('dashboard.absensi.verify');
    Route::post('/dashboard/absensi/{absensi}/reject', [AbsensiController::class, 'reject'])->name('dashboard.absensi.reject');
    Route::post('/dashboard/absensi/{absensi}/checkout-correction/approve', [AbsensiController::class, 'approveCheckoutCorrection'])->name('dashboard.absensi.checkout_correction.approve');
    Route::post('/dashboard/absensi/{absensi}/checkout-correction/reject', [AbsensiController::class, 'rejectCheckoutCorrection'])->name('dashboard.absensi.checkout_correction.reject');
    Route::post('/dashboard/absensi/{absensi}/checkout-correction/manual', [AbsensiController::class, 'manualCheckoutCorrection'])->name('dashboard.absensi.checkout_correction.manual');
    Route::get('/dashboard/chat', [AdminChatController::class, 'index'])->name('dashboard.chat.index');
    Route::get('/dashboard/aktivitas-staf', [DashboardStaffActivityController::class, 'index'])->name('dashboard.staff_activity.index');
    Route::get('/dashboard/chat/{karyawan}', [AdminChatController::class, 'show'])->name('dashboard.chat.show');
    Route::post('/dashboard/chat/{karyawan}', [AdminChatController::class, 'store'])->name('dashboard.chat.store');
    Route::get('/dashboard/izin', [DashboardLeaveController::class, 'index'])->name('dashboard.leave.index');
    Route::get('/dashboard/izin/{leave}', [DashboardLeaveController::class, 'show'])->name('dashboard.leave.show');
    Route::post('/dashboard/izin/{leave}/approve', [DashboardLeaveController::class, 'approve'])->name('dashboard.leave.approve');
    Route::post('/dashboard/izin/{leave}/reject', [DashboardLeaveController::class, 'reject'])->name('dashboard.leave.reject');
    Route::post('/dashboard/izin/{leave}/message', [DashboardLeaveController::class, 'message'])->name('dashboard.leave.message');
    Route::get('/dashboard/gaji', [DashboardPayrollController::class, 'index'])->name('dashboard.payroll.index');
    Route::post('/dashboard/gaji/aturan', [DashboardPayrollController::class, 'updatePolicy'])->name('dashboard.payroll.policy.update');
    Route::get('/dashboard/gaji/slip/{payrollSlip}/print', [DashboardPayrollController::class, 'print'])->name('dashboard.payroll.print');
    Route::get('/dashboard/gaji/{karyawan}', [DashboardPayrollController::class, 'show'])->name('dashboard.payroll.show');
    Route::post('/dashboard/gaji/{karyawan}', [DashboardPayrollController::class, 'store'])->name('dashboard.payroll.store');
    Route::get('/dashboard/pesan/{type}/{id}', [DashboardMessageController::class, 'show'])->name('dashboard.messages.show');
    Route::post('/dashboard/pesan/{type}/{id}', [DashboardMessageController::class, 'store'])->name('dashboard.messages.store');
    Route::get('/dashboard/jadwal', [JadwalController::class, 'index'])->name('dashboard.jadwal.index');
    Route::get('/dashboard/jadwal/self-schedule', [JadwalController::class, 'selfSchedule'])->name('dashboard.jadwal.self_schedule');
    Route::post('/dashboard/jadwal/self-schedule', [JadwalController::class, 'updateSelfSchedule'])->name('dashboard.jadwal.self_schedule.update');
    Route::get('/dashboard/jadwal/tukar', [JadwalController::class, 'swapRequests'])->name('dashboard.jadwal.swap_requests');
    Route::post('/dashboard/jadwal/tukar/{swap}/approve', [JadwalController::class, 'approveSwap'])->name('dashboard.jadwal.swap_requests.approve');
    Route::post('/dashboard/jadwal/tukar/{swap}/reject', [JadwalController::class, 'rejectSwap'])->name('dashboard.jadwal.swap_requests.reject');
    Route::get('/dashboard/pengumuman', [AnnouncementController::class, 'index'])->name('dashboard.announcements.index');
    Route::get('/dashboard/pengumuman/create', [AnnouncementController::class, 'create'])->name('dashboard.announcements.create');
    Route::post('/dashboard/pengumuman', [AnnouncementController::class, 'store'])->name('dashboard.announcements.store');
    Route::get('/dashboard/pengumuman/{announcement}', [AnnouncementController::class, 'show'])->name('dashboard.announcements.show');
    Route::post('/dashboard/pengumuman/{announcement}/read', [AnnouncementController::class, 'readByAdmin'])->name('dashboard.announcements.read');
    Route::get('/dashboard/pengumuman/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('dashboard.announcements.edit');
    Route::put('/dashboard/pengumuman/{announcement}', [AnnouncementController::class, 'update'])->name('dashboard.announcements.update');
    Route::delete('/dashboard/pengumuman/{announcement}', [AnnouncementController::class, 'destroy'])->name('dashboard.announcements.destroy');
    Route::get('/dashboard/jadwal/{tanggal}', [JadwalController::class, 'edit'])->name('dashboard.jadwal.edit');
    Route::post('/dashboard/jadwal/{tanggal}', [JadwalController::class, 'update'])->name('dashboard.jadwal.update');
    Route::get('/dashboard/share-qr', [DashboardController::class, 'shareQr'])->name('dashboard.share_qr');
    Route::get('/dashboard/share-qr/preview/{kind}', [DashboardController::class, 'previewShareQr'])->name('dashboard.share_qr.preview');
    Route::get('/dashboard/share-qr/download/{kind}', [DashboardController::class, 'downloadShareQr'])->name('dashboard.share_qr.download');
    Route::post('/dashboard/setoran', [DashboardController::class, 'storeSetoran'])->name('dashboard.setoran.store');
    Route::get('/dashboard/keuangan', [DashboardController::class, 'keuangan'])->name('dashboard.keuangan');
    Route::get('/dashboard/keuangan/export/excel', [DashboardController::class, 'exportKeuanganExcel'])->name('dashboard.keuangan.export_excel');
    Route::post('/dashboard/setoran/{setoran}/catatan', [DashboardController::class, 'updateSetoranCatatan'])->name('dashboard.setoran.catatan.update');
    Route::post('/dashboard/setoran/{setoran}/koreksi-nominal', [DashboardController::class, 'koreksiSetoranNominal'])->name('dashboard.setoran.nominal.correct');
    Route::get('/dashboard/ruang-kerja', [DashboardController::class, 'workspace'])->name('dashboard.workspace');
    Route::post('/dashboard/ruang-kerja', [DashboardController::class, 'updateWorkspace'])->name('dashboard.workspace.update');
    Route::get('/dashboard/shift-history', [KasirShiftController::class, 'history'])->name('dashboard.shift_history');
    Route::get('/dashboard/statistik', [DashboardController::class, 'statistik'])->name('dashboard.statistik');
    Route::get('/dashboard/statistik/export/excel', [DashboardController::class, 'exportStatistikExcel'])->name('dashboard.statistik.export_excel');
    Route::post('/dashboard/operasional-reset-hour', [DashboardController::class, 'updateOperationalResetHour'])->name('dashboard.operasional_reset_hour.update');

    Route::post('/transaksi/{transaksi}/batal', [TransaksiController::class, 'batal'])->name('transaksi.batal');
    Route::post('/transaksi/{transaksi}/restore', [TransaksiController::class, 'restore'])->name('transaksi.restore');

    Route::get('/kategori', [KategoriController::class, 'index'])->name('kategori.index');
    Route::get('/kategori/create', [KategoriController::class, 'create'])->name('kategori.create');
    Route::post('/kategori', [KategoriController::class, 'store'])->name('kategori.store');
    Route::get('/kategori/{kategori}/edit', [KategoriController::class, 'edit'])->name('kategori.edit');
    Route::put('/kategori/{kategori}', [KategoriController::class, 'update'])->name('kategori.update');
    Route::delete('/kategori/{kategori}', [KategoriController::class, 'destroy'])->name('kategori.destroy');

    Route::get('/produk', [ProdukController::class, 'index'])->name('produk.index');
    Route::get('/produk/create', [ProdukController::class, 'create'])->name('produk.create');
    Route::post('/produk', [ProdukController::class, 'store'])->name('produk.store');
    Route::get('/produk/{produk}/edit', [ProdukController::class, 'edit'])->name('produk.edit');
    Route::put('/produk/{produk}', [ProdukController::class, 'update'])->name('produk.update');
    Route::delete('/produk/{produk}', [ProdukController::class, 'destroy'])->name('produk.destroy');

    Route::get('/master-opsi-kasir', [MasterOpsiKasirController::class, 'index'])->name('master_opsi_kasir.index');
    Route::get('/master-opsi-kasir/create', [MasterOpsiKasirController::class, 'create'])->name('master_opsi_kasir.create');
    Route::post('/master-opsi-kasir', [MasterOpsiKasirController::class, 'store'])->name('master_opsi_kasir.store');
    Route::get('/master-opsi-kasir/{masterOpsiKasir}/edit', [MasterOpsiKasirController::class, 'edit'])->name('master_opsi_kasir.edit');
    Route::put('/master-opsi-kasir/{masterOpsiKasir}', [MasterOpsiKasirController::class, 'update'])->name('master_opsi_kasir.update');
    Route::delete('/master-opsi-kasir/{masterOpsiKasir}', [MasterOpsiKasirController::class, 'destroy'])->name('master_opsi_kasir.destroy');

    Route::get('/pelanggan', [PelangganController::class, 'index'])->name('pelanggan.index');
    Route::get('/pelanggan/create', [PelangganController::class, 'create'])->name('pelanggan.create');
    Route::post('/pelanggan', [PelangganController::class, 'store'])->name('pelanggan.store');
    Route::get('/pelanggan/{pelanggan}/edit', [PelangganController::class, 'edit'])->name('pelanggan.edit');
    Route::put('/pelanggan/{pelanggan}', [PelangganController::class, 'update'])->name('pelanggan.update');
    Route::delete('/pelanggan/{pelanggan}', [PelangganController::class, 'destroy'])->name('pelanggan.destroy');

    Route::get('/karyawan', [KaryawanController::class, 'index'])->name('karyawan.index');
    Route::get('/karyawan/create', [KaryawanController::class, 'create'])->name('karyawan.create');
    Route::post('/karyawan', [KaryawanController::class, 'store'])->name('karyawan.store');
    Route::get('/karyawan/{karyawan}', [KaryawanController::class, 'show'])->name('karyawan.show');
    Route::get('/karyawan/{karyawan}/edit', [KaryawanController::class, 'edit'])->name('karyawan.edit');
    Route::put('/karyawan/{karyawan}', [KaryawanController::class, 'update'])->name('karyawan.update');
    Route::get('/karyawan/{karyawan}/pin', [KaryawanController::class, 'showPin'])->name('karyawan.pin.show');
    Route::put('/karyawan/{karyawan}/active', [KaryawanController::class, 'updateActive'])->name('karyawan.active.update');
    Route::delete('/karyawan/{karyawan}', [KaryawanController::class, 'destroy'])->name('karyawan.destroy');

    Route::get('/diskon', [DiskonController::class, 'index'])->name('diskon.index');
    Route::get('/diskon/create', [DiskonController::class, 'create'])->name('diskon.create');
    Route::post('/diskon', [DiskonController::class, 'store'])->name('diskon.store');
    Route::get('/diskon/{diskon}/edit', [DiskonController::class, 'edit'])->name('diskon.edit');
    Route::put('/diskon/{diskon}', [DiskonController::class, 'update'])->name('diskon.update');
    Route::delete('/diskon/{diskon}', [DiskonController::class, 'destroy'])->name('diskon.destroy');

    Route::get('/pengaturan-struk', [StrukSettingController::class, 'edit'])->name('struk_setting.edit');
    Route::put('/pengaturan-struk', [StrukSettingController::class, 'update'])->name('struk_setting.update');
    Route::get('/pengaturan-tema', [StrukSettingController::class, 'editTheme'])->name('theme_setting.edit');
    Route::put('/pengaturan-tema', [StrukSettingController::class, 'updateTheme'])->name('theme_setting.update');
    Route::get('/profil-cafe', [CafeProfileController::class, 'edit'])->name('cafe_profile.edit');
    Route::put('/profil-cafe', [CafeProfileController::class, 'update'])->name('cafe_profile.update');

    Route::get('/bundling', [PromoBundlingController::class, 'index'])->name('bundling.index');
    Route::get('/bundling/create', [PromoBundlingController::class, 'create'])->name('bundling.create');
    Route::post('/bundling', [PromoBundlingController::class, 'store'])->name('bundling.store');
    Route::get('/bundling/{bundling}/edit', [PromoBundlingController::class, 'edit'])->name('bundling.edit');
    Route::put('/bundling/{bundling}', [PromoBundlingController::class, 'update'])->name('bundling.update');
    Route::delete('/bundling/{bundling}', [PromoBundlingController::class, 'destroy'])->name('bundling.destroy');
});

Route::middleware('auth')->post('/logout', [AuthController::class, 'logout'])->name('logout');
