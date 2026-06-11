<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\JadwalKaryawan;
use App\Models\Karyawan;
use App\Models\StaffMessage;
use App\Models\StaffNotification;
use App\Models\StrukSetting;
use App\Services\AbsensiCorrectionService;
use App\Services\StaffActivityLogger;
use App\Services\StaffNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbsensiController extends Controller
{
    private function resolveShiftStartAt(string $date, int $shiftNo, StrukSetting $setting, ?string $employmentType = null): ?Carbon
    {
        if ($shiftNo <= 0) {
            return null;
        }

        $timeStr = $setting->shiftStartTimeFor($shiftNo, $employmentType);

        return Carbon::parse($date . ' ' . $timeStr . ':00');
    }

    private function buildAbsensiWindow(string $date, int $shiftNo, StrukSetting $setting, ?string $employmentType = null): ?array
    {
        $startAt = $this->resolveShiftStartAt($date, $shiftNo, $setting, $employmentType);
        if (! $startAt) {
            return null;
        }

        $beforeMinutes = max(0, (int) ($setting->absensi_checkin_before_minutes ?? 30));
        $afterMinutes = max(0, (int) ($setting->absensi_checkin_after_minutes ?? 60));
        $tolerance = max(0, (int) ($setting->absensi_late_tolerance_minutes ?? 10));

        return [
            'startAt' => $startAt,
            'openAt' => $startAt->copy()->subMinutes($beforeMinutes),
            'lateAt' => $startAt->copy()->addMinutes($tolerance),
            'closeAt' => $startAt->copy()->addMinutes($afterMinutes),
            'beforeMinutes' => $beforeMinutes,
            'afterMinutes' => $afterMinutes,
            'tolerance' => $tolerance,
        ];
    }

    private function evaluateAbsensiWindow(Carbon $now, array $window): string
    {
        if ($now->lt($window['openAt'])) {
            return 'too_early';
        }
        if ($now->gt($window['closeAt'])) {
            return 'too_late';
        }
        if ($now->lt($window['startAt'])) {
            return 'early';
        }
        if ($now->lte($window['lateAt'])) {
            return 'on_time';
        }

        return 'alpa';
    }

    private function windowStateLabel(string $state, array $window): array
    {
        return match ($state) {
            'too_early' => [
                'label' => 'Belum dibuka. Mulai ' . $window['openAt']->format('H:i') . '.',
                'class' => 'bad',
            ],
            'too_late' => [
                'label' => 'Lewat batas. Maksimal ' . $window['closeAt']->format('H:i') . '.',
                'class' => 'bad',
            ],
            'early' => [
                'label' => 'Lebih awal (absen dibuka).',
                'class' => 'ok',
            ],
            'on_time' => [
                'label' => 'Dalam toleransi.',
                'class' => 'ok',
            ],
            default => [
                'label' => 'Lewat toleransi (Alpa).',
                'class' => 'bad',
            ],
        };
    }

    private function absensiTimingInfo(string $date, ?Carbon $waktuMasuk, ?int $shiftNo, StrukSetting $setting, ?string $employmentType = null): array
    {
        if (! $waktuMasuk || ! $shiftNo || $shiftNo <= 0) {
            return [
                'masuk' => null,
                'deltaLabel' => null,
                'deltaClass' => null,
            ];
        }

        $window = $this->buildAbsensiWindow($date, (int) $shiftNo, $setting, $employmentType);
        if (! $window) {
            return [
                'masuk' => $waktuMasuk->format('H:i'),
                'deltaLabel' => null,
                'deltaClass' => null,
            ];
        }

        $start = $window['startAt'];
        $deltaMin = (int) $waktuMasuk->diffInMinutes($start, false); // + means after start

        $label = null;
        $class = 'ok';
        if ($deltaMin > (int) $window['afterMinutes']) {
            $label = 'Lewat batas ' . $deltaMin . 'm';
            $class = 'bad';
        } elseif ($deltaMin > (int) $window['tolerance']) {
            $label = 'Alpa ' . $deltaMin . 'm';
            $class = 'bad';
        } elseif ($deltaMin > 0) {
            $label = 'Lewat ' . $deltaMin . 'm';
            $class = 'ok';
        } elseif ($deltaMin < 0) {
            $label = 'Lebih awal ' . abs($deltaMin) . 'm';
            $class = 'ok';
        } else {
            $label = 'Tepat waktu';
            $class = 'ok';
        }

        return [
            'masuk' => $waktuMasuk->format('H:i'),
            'deltaLabel' => $label,
            'deltaClass' => $class,
        ];
    }

    private function workedDurationLabel(?Carbon $waktuMasuk, ?Carbon $waktuPulang): ?string
    {
        if (! $waktuMasuk || ! $waktuPulang || $waktuPulang->lte($waktuMasuk)) {
            return null;
        }

        $minutes = (int) $waktuMasuk->diffInMinutes($waktuPulang);
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . ' jam';
        }
        if ($remainingMinutes > 0) {
            $parts[] = $remainingMinutes . ' menit';
        }

        return $parts !== [] ? implode(' ', $parts) : '0 menit';
    }

    private function correctionService(): AbsensiCorrectionService
    {
        return app(AbsensiCorrectionService::class);
    }

    private function checkoutCorrectionDeadlineAt(Absensi $absensi): Carbon
    {
        return $this->correctionService()->deadlineAt($absensi);
    }

    private function checkoutCorrectionState(
        Absensi $absensi,
        StrukSetting $setting,
        ?Karyawan $karyawan = null,
        ?Carbon $now = null,
    ): array {
        return $this->correctionService()->state($absensi, $setting, $karyawan, $now);
    }

    private function readyForAdminFinalization(Absensi $absensi): bool
    {
        if ($absensi->waktu_masuk && ! $absensi->waktu_pulang) {
            return false;
        }

        return true;
    }

    private function notifyAbsensiThread(
        Absensi $absensi,
        string $message,
        string $senderRole,
        ?int $senderKaryawanId = null,
        ?int $senderUserId = null,
    ): void {
        StaffMessage::query()->create([
            'thread_type' => 'absensi',
            'thread_id' => (int) $absensi->id_absensi,
            'sender_role' => $senderRole,
            'sender_karyawan_id' => $senderKaryawanId,
            'sender_user_id' => $senderUserId,
            'message' => $message,
            'meta' => [
                'action' => [
                    'type' => 'absensi',
                    'id' => (int) $absensi->id_absensi,
                ],
            ],
        ]);
    }

    private function activeKaryawanQuery()
    {
        $query = Karyawan::query();
        if (Schema::hasColumn('karyawan', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query;
    }

    private function attendancePageData(Request $request): array|RedirectResponse
    {
        $today = now()->toDateString();
        $setting = StrukSetting::current();

        $staffId = (int) ($request->session()->get('staff_karyawan_id') ?? 0);
        // Absensi always requires staff portal login.
        if ($staffId <= 0) {
            $request->session()->put('staff_redirect_after_login', url('/absen'));
            return redirect()->route('staff.login');
        }

        $staffKaryawan = null;
        if ($staffId > 0) {
            $staffKaryawan = $this->activeKaryawanQuery()
                ->where('id_karyawan', $staffId)
                ->first(['id_karyawan', 'nama_karyawan', 'jabatan', 'no_telepon', 'employment_type']);
        }
        if (! $staffKaryawan) {
            $request->session()->forget(['staff_karyawan_id', 'staff_karyawan_name']);
            $request->session()->regenerate();
            $request->session()->put('staff_redirect_after_login', url('/absen'));
            return redirect()->route('staff.login');
        }

        $request->attributes->set('staff_karyawan', $staffKaryawan);

        $absenHariIni = Absensi::query()
            ->where('id_karyawan', (int) $staffKaryawan->id_karyawan)
            ->whereDate('tanggal', $today)
            ->first(['tanggal', 'waktu_masuk', 'waktu_pulang', 'status', 'shift_no', 'verification_status']);

        $openHistoricalAbsensi = Absensi::query()
            ->where('id_karyawan', (int) $staffKaryawan->id_karyawan)
            ->whereNotNull('waktu_masuk')
            ->whereNull('waktu_pulang')
            ->whereDate('tanggal', '<', $today)
            ->orderByDesc('tanggal')
            ->first([
                'id_absensi',
                'tanggal',
                'waktu_masuk',
                'shift_no',
                'checkout_correction_status',
                'checkout_requested_pulang',
                'checkout_request_note',
                'checkout_review_note',
            ]);

        $alreadyMasuk = (bool) ($absenHariIni && $absenHariIni->waktu_masuk);
        $alreadyPulang = (bool) ($absenHariIni && $absenHariIni->waktu_pulang);

        $absenInfo = null;
        if ($absenHariIni) {
            $absenInfo = $this->absensiTimingInfo($today, $absenHariIni->waktu_masuk, (int) ($absenHariIni->shift_no ?? 0), $setting, $staffKaryawan->employment_type ?? null);
            $status = (string) ($absenHariIni->status ?? '');
            $statusLabel = $alreadyMasuk && ! $alreadyPulang
                ? 'Sudah Absen Masuk'
                : ($alreadyPulang
                    ? 'Absen Hari Ini Lengkap'
                    : ($status === 'alpa'
                        ? 'Alpa'
                        : ($status === 'telat'
                            ? 'Telat'
                            : ($status === 'hadir'
                                ? 'Hadir'
                                : ($status === 'tidak_dijadwalkan' ? 'Tidak dijadwalkan' : 'OK')))));
            $absenInfo['statusLabel'] = $statusLabel;
            $absenInfo['statusClass'] = $status === 'alpa' ? 'bad' : (($alreadyPulang || $alreadyMasuk) ? 'ok' : ($status === 'telat' ? 'bad' : 'ok'));
            $absenInfo['shiftNo'] = (int) ($absenHariIni->shift_no ?? 0);
            $absenInfo['pulang'] = $absenHariIni->waktu_pulang?->format('H:i');
            $absenInfo['durasiLabel'] = $this->workedDurationLabel($absenHariIni->waktu_masuk, $absenHariIni->waktu_pulang);
            $absenInfo['verificationLabel'] = match ((string) ($absenHariIni->verification_status ?? 'pending')) {
                'verified' => 'Sudah diverifikasi admin',
                'rejected' => 'Perlu diperbaiki / ditinjau ulang',
                default => 'Menunggu verifikasi admin',
            };
        }

        $windowInfo = null;
        $jadwalHariIni = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $staffKaryawan->id_karyawan)
            ->whereDate('tanggal', $today)
            ->first(['shift_ke']);
        $shiftNo = $jadwalHariIni ? (int) ($jadwalHariIni->shift_ke ?? 0) : 0;
        if ($shiftNo > 0) {
            $window = $this->buildAbsensiWindow($today, $shiftNo, $setting, $staffKaryawan->employment_type ?? null);
            if ($window) {
                $state = $this->evaluateAbsensiWindow(now(), $window);
                $label = $this->windowStateLabel($state, $window);
                $windowInfo = [
                    'shiftNo' => $shiftNo,
                    'shiftCode' => $setting->shiftCodeFor($shiftNo, $staffKaryawan->employment_type ?? null),
                    'startAt' => $window['startAt']->format('H:i'),
                    'openAt' => $window['openAt']->format('H:i'),
                    'closeAt' => $window['closeAt']->format('H:i'),
                    'state' => $state,
                    'label' => $label['label'],
                    'class' => $label['class'],
                ];
            }
        }

        $pendingCorrection = null;
        $blockTodayForm = false;
        if ($openHistoricalAbsensi) {
            $correctionState = $this->checkoutCorrectionState($openHistoricalAbsensi, $setting, $staffKaryawan);
            $openShiftNo = (int) ($openHistoricalAbsensi->shift_no ?? 0);
            $pendingCorrection = [
                'id' => (int) $openHistoricalAbsensi->id_absensi,
                'tanggal' => $openHistoricalAbsensi->tanggal?->toDateString(),
                'tanggalLabel' => $openHistoricalAbsensi->tanggal?->translatedFormat('d M Y') ?? '-',
                'masuk' => $openHistoricalAbsensi->waktu_masuk?->format('H:i') ?? '-',
                'shiftLabel' => $openShiftNo > 0
                    ? $setting->shiftCodeFor($openShiftNo, $staffKaryawan->employment_type ?? null)
                    : 'Tanpa shift',
                'stateKey' => $correctionState['key'],
                'stateLabel' => $correctionState['label'],
                'stateClass' => $correctionState['class'],
                'note' => $correctionState['note'],
                'deadlineLabel' => $correctionState['deadline_label'],
                'canRequest' => (bool) $correctionState['can_request'],
                'requestedPulangLabel' => $correctionState['requested_pulang_label'],
                'requestedPulangInput' => old(
                    'requested_pulang',
                    $openHistoricalAbsensi->checkout_requested_pulang?->format('Y-m-d\TH:i')
                ),
                'requestNote' => old('request_note', (string) ($openHistoricalAbsensi->checkout_request_note ?? '')),
                'reviewNote' => (string) ($openHistoricalAbsensi->checkout_review_note ?? ''),
            ];
            $blockTodayForm = true;
        }

        $karyawan = $this->activeKaryawanQuery()
            ->orderBy('nama_karyawan')
            ->get(['id_karyawan', 'nama_karyawan', 'jabatan']);

        return [
            'today' => $today,
            'karyawan' => $karyawan,
            'staffKaryawan' => $staffKaryawan,
            'requireSelfie' => (bool) ($setting->absensi_require_selfie ?? false),
            'requireGeofence' => (bool) ($setting->absensi_require_geofence ?? false),
            'geoLat' => $setting->absensi_geo_lat ?? null,
            'geoLng' => $setting->absensi_geo_lng ?? null,
            'geoRadiusM' => (int) ($setting->absensi_geo_radius_m ?? 150),
            'geoMaxAccuracyM' => (int) ($setting->absensi_geo_max_accuracy_m ?? 80),
            'absenInfo' => $absenInfo,
            'alreadyAbsen' => $alreadyMasuk,
            'alreadyMasuk' => $alreadyMasuk,
            'alreadyPulang' => $alreadyPulang,
            'windowInfo' => $windowInfo,
            'pendingCorrection' => $pendingCorrection,
            'blockTodayForm' => $blockTodayForm,
        ];
    }

    public function form(Request $request): View|RedirectResponse
    {
        $data = $this->attendancePageData($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('absensi.form', $data);
    }

    public function checkInForm(Request $request): View|RedirectResponse
    {
        $data = $this->attendancePageData($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('absensi.action', array_merge($data, [
            'focusMode' => 'checkin',
        ]));
    }

    public function checkOutForm(Request $request): View|RedirectResponse
    {
        $data = $this->attendancePageData($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('absensi.action', array_merge($data, [
            'focusMode' => 'checkout',
        ]));
    }

    public function masuk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:255'],
            'selfie' => ['nullable', 'image', 'max:3072'],
            'selfie_source' => ['nullable', 'string', 'max:30'],
            'geo_lat' => ['nullable', 'numeric'],
            'geo_lng' => ['nullable', 'numeric'],
            'geo_accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        $today = now()->toDateString();
        $catatan = trim((string) ($data['catatan'] ?? '')) ?: null;
        $setting = StrukSetting::current();
        $staffId = (int) ($request->session()->get('staff_karyawan_id') ?? 0);

        $karyawanId = null;
        if ($staffId > 0) {
            $karyawanId = $staffId;
        }
        if (! $karyawanId) {
            session(['staff_redirect_after_login' => url('/absen')]);
            return redirect()->route('staff.login');
        }

        $employmentType = Karyawan::query()
            ->where('id_karyawan', $karyawanId)
            ->value('employment_type');

        $openHistoricalAbsensi = Absensi::query()
            ->where('id_karyawan', $karyawanId)
            ->whereNotNull('waktu_masuk')
            ->whereNull('waktu_pulang')
            ->whereDate('tanggal', '<', $today)
            ->orderByDesc('tanggal')
            ->first(['tanggal']);

        if ($openHistoricalAbsensi) {
            $tanggalLabel = $openHistoricalAbsensi->tanggal?->translatedFormat('d M Y') ?? $openHistoricalAbsensi->tanggal?->toDateString() ?? 'sebelumnya';

            return back()->withErrors([
                'absensi' => 'Absensi tanggal ' . $tanggalLabel . ' belum lengkap. Selesaikan koreksi jam pulang dulu sebelum absen hari ini.',
            ])->withInput();
        }

        $requireSelfie = (bool) ($setting->absensi_require_selfie ?? false);
        $requireGeofence = (bool) ($setting->absensi_require_geofence ?? false);

        if ($requireSelfie && ! $request->hasFile('selfie')) {
            return back()->withErrors(['selfie' => 'Selfie wajib diambil untuk absensi.'])->withInput();
        }
        if ($requireSelfie) {
            $src = trim((string) ($data['selfie_source'] ?? ''));
            if ($src !== 'camera') {
                return back()->withErrors(['selfie' => 'Selfie harus diambil langsung dari kamera.'])->withInput();
            }
        }

        $geoLat = $requireGeofence ? (float) ($setting->absensi_geo_lat ?? 0) : 0.0;
        $geoLng = $requireGeofence ? (float) ($setting->absensi_geo_lng ?? 0) : 0.0;
        $geoRadius = (int) ($setting->absensi_geo_radius_m ?? 150);
        $geoMaxAcc = (int) ($setting->absensi_geo_max_accuracy_m ?? 80);

        $geoLatIn = isset($data['geo_lat']) ? (float) $data['geo_lat'] : null;
        $geoLngIn = isset($data['geo_lng']) ? (float) $data['geo_lng'] : null;
        $geoAccIn = isset($data['geo_accuracy_m']) ? (int) round((float) $data['geo_accuracy_m']) : null;

        if ($requireGeofence) {
            if ($geoLat === 0.0 && $geoLng === 0.0) {
                return back()->withErrors(['geo' => 'Lokasi cafe belum disetting oleh admin.'])->withInput();
            }
            if ($geoLatIn === null || $geoLngIn === null) {
                return back()->withErrors(['geo' => 'Lokasi wajib diambil untuk absensi.'])->withInput();
            }
            if ($geoAccIn !== null && $geoAccIn > $geoMaxAcc) {
                return back()->withErrors([
                    'geo' => 'Akurasi GPS terlalu rendah: ' . $geoAccIn . 'm (batas ' . $geoMaxAcc . 'm). Coba refresh lokasi di tempat terbuka.',
                ])->withInput();
            }
            $dist = $this->distanceMeters($geoLat, $geoLng, $geoLatIn, $geoLngIn);
            if ($dist > $geoRadius) {
                return back()->withErrors(['geo' => 'Lokasi di luar radius cafe.'])->withInput();
            }
        }

        $exists = Absensi::query()
            ->where('id_karyawan', $karyawanId)
            ->whereDate('tanggal', $today)
            ->exists();

        if ($exists) {
            $existing = Absensi::query()
                ->where('id_karyawan', $karyawanId)
                ->whereDate('tanggal', $today)
                ->first(['waktu_masuk', 'status', 'shift_no']);
            $timing = $existing && $existing->waktu_masuk
                ? $this->absensiTimingInfo($today, $existing->waktu_masuk, (int) ($existing->shift_no ?? 0), $setting, $employmentType)
                : null;
            $extra = '';
            if ($timing && !empty($timing['masuk'])) {
                $extra .= ' (Masuk ' . $timing['masuk'] . ')';
            }
            return back()->withErrors([
                'absensi' => 'Absen masuk hari ini sudah tercatat.' . $extra,
            ])->withInput();
        }

        $waktuMasuk = now();
        $shiftNo = $this->resolveShiftNoForKaryawan((int) $karyawanId, $today);
        $status = 'tidak_dijadwalkan';
        if ($shiftNo) {
            $window = $this->buildAbsensiWindow($today, $shiftNo, $setting, $employmentType);
            if ($window) {
                $state = $this->evaluateAbsensiWindow($waktuMasuk, $window);
                if ($state === 'too_early') {
                    $msg = 'Absen belum dibuka. Mulai ' . $window['openAt']->format('H:i') . ' (' . $window['beforeMinutes'] . ' menit sebelum shift).';
                    return back()->withErrors(['absensi_window' => $msg])->withInput();
                }
                if ($state === 'too_late') {
                    $msg = 'Batas absen sudah lewat. Maksimal sampai ' . $window['closeAt']->format('H:i') . '. Status otomatis: Alpa.';
                    $exists = Absensi::query()
                        ->where('id_karyawan', $karyawanId)
                        ->whereDate('tanggal', $today)
                        ->exists();
                    if (! $exists) {
                        Absensi::query()->create([
                            'id_karyawan' => $karyawanId,
                            'tanggal' => $today,
                            'waktu_masuk' => null,
                            'waktu_pulang' => null,
                            'catatan' => 'Alpa otomatis (telat lewat batas).',
                            'status' => 'alpa',
                            'verification_status' => 'pending',
                            'absensi_source' => null,
                            'shift_no' => $shiftNo,
                            'selfie_path' => null,
                            'geo_lat' => null,
                            'geo_lng' => null,
                            'geo_accuracy_m' => null,
                        ]);
                    }
                    return back()->withErrors(['absensi_window' => $msg])->withInput();
                }
                $status = $state === 'alpa' ? 'alpa' : 'hadir';
            } else {
                $status = 'hadir';
            }
        }

        $selfiePath = null;
        if ($request->hasFile('selfie')) {
            $selfiePath = $request->file('selfie')->storePublicly('absensi-selfie', 'public');
        }

        Absensi::query()->create([
            'id_karyawan' => $karyawanId,
            'tanggal' => $today,
            'waktu_masuk' => $waktuMasuk,
            'waktu_pulang' => null,
            'catatan' => $catatan,
            'status' => $status,
            'verification_status' => 'pending',
            'absensi_source' => 'portal',
            'shift_no' => $shiftNo,
            'selfie_path' => $selfiePath,
            'geo_lat' => $geoLatIn,
            'geo_lng' => $geoLngIn,
            'geo_accuracy_m' => $geoAccIn,
        ]);

        $timing = $this->absensiTimingInfo($today, $waktuMasuk, (int) ($shiftNo ?? 0), $setting, $employmentType);
        $success = 'Absen masuk berhasil.';
        if ($shiftNo) {
            $success .= ' ' . $setting->shiftCodeFor((int) $shiftNo, $employmentType) . '.';
        }
        if (!empty($timing['masuk'])) {
            $success .= ' Masuk ' . $timing['masuk'] . '.';
        }
        if (!empty($timing['deltaLabel'])) {
            $success .= ' ' . $timing['deltaLabel'] . '.';
        }
        if ($status === 'alpa') {
            $success .= ' Status: Alpa.';
        }

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawanId,
            'staff.absen.masuk',
            'Absen Masuk',
            'Melakukan absen masuk untuk tanggal ' . $today . '.',
            [
                'tanggal' => $today,
                'shift' => $shiftNo ? $setting->shiftCodeFor((int) $shiftNo, $employmentType) : 'Tidak dijadwalkan',
                'status' => $status,
            ],
            'absensi',
            null,
            $today,
        );

        return redirect()->route('absen.form')->with('success', $success);
    }

    public function kasirForm(): View
    {
        $today = now()->toDateString();
        $setting = StrukSetting::current();
        $shiftNo = $this->resolveCurrentShiftNo($setting);
        $karyawanQuery = $this->activeKaryawanQuery();

        if ($shiftNo) {
            $ids = JadwalKaryawan::query()
                ->whereDate('tanggal', $today)
                ->where('shift_ke', $shiftNo)
                ->pluck('id_karyawan');
            $karyawanQuery->whereIn('id_karyawan', $ids);
        } else {
            $karyawanQuery->whereRaw('1=0');
        }

        $karyawan = $karyawanQuery
            ->orderBy('nama_karyawan')
            ->get(['id_karyawan', 'nama_karyawan', 'jabatan']);

        return view('kasir.absen', [
            'today' => $today,
            'karyawan' => $karyawan,
            'shiftNo' => $shiftNo,
        ]);
    }

    public function kasirMasuk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_karyawan' => ['required', 'integer', 'exists:karyawan,id_karyawan'],
            'pin' => ['required', 'string', 'regex:/^[0-9]{4,8}$/'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $today = now()->toDateString();
        $karyawanId = (int) $data['id_karyawan'];
        $catatan = trim((string) ($data['catatan'] ?? '')) ?: null;
        $setting = StrukSetting::current();

        $pin = trim((string) ($data['pin'] ?? ''));
        $digest = \App\Models\Karyawan::pinDigest($pin);
        $karyawanRow = Karyawan::query()
            ->where('id_karyawan', $karyawanId)
            ->first(['pin_digest', 'employment_type']);
        $karyawanPin = $karyawanRow?->pin_digest;
        if (!is_string($karyawanPin) || trim((string) $karyawanPin) === '') {
            return back()->withErrors(['pin' => 'Akun ini belum memiliki PIN. Hubungi admin.'])->withInput();
        }
        if (! hash_equals((string) $karyawanPin, (string) $digest)) {
            return back()->withErrors(['pin' => 'PIN tidak cocok.'])->withInput();
        }
        $selfieData = $request->validate([
            'selfie' => ['required', 'image', 'max:3072'],
            'selfie_source' => ['nullable', 'string', 'max:30'],
        ]);
        $src = trim((string) ($selfieData['selfie_source'] ?? ''));
        if ($src !== 'camera') {
            return back()->withErrors(['selfie' => 'Selfie harus diambil langsung dari kamera.'])->withInput();
        }

        $exists = Absensi::query()
            ->where('id_karyawan', $karyawanId)
            ->whereDate('tanggal', $today)
            ->exists();

        if ($exists) {
            $existing = Absensi::query()
                ->where('id_karyawan', $karyawanId)
                ->whereDate('tanggal', $today)
                ->first(['waktu_masuk', 'status', 'shift_no']);
            $timing = $existing && $existing->waktu_masuk
                ? $this->absensiTimingInfo($today, $existing->waktu_masuk, (int) ($existing->shift_no ?? 0), $setting, $karyawanRow?->employment_type)
                : null;
            $extra = '';
            if ($timing && !empty($timing['masuk'])) {
                $extra .= ' (Masuk ' . $timing['masuk'] . ')';
            }
            return back()->withErrors([
                'absensi' => 'Absen masuk hari ini sudah tercatat.' . $extra,
            ])->withInput();
        }

        $waktuMasuk = now();
        $shiftNo = $this->resolveShiftNoForKaryawan((int) $karyawanId, $today);
        $status = 'tidak_dijadwalkan';
        if ($shiftNo) {
            $window = $this->buildAbsensiWindow($today, $shiftNo, $setting, $karyawanRow?->employment_type);
            if ($window) {
                $state = $this->evaluateAbsensiWindow($waktuMasuk, $window);
                if ($state === 'too_early') {
                    $msg = 'Absen belum dibuka. Mulai ' . $window['openAt']->format('H:i') . ' (' . $window['beforeMinutes'] . ' menit sebelum shift).';
                    return back()->withErrors(['absensi_window' => $msg])->withInput();
                }
                if ($state === 'too_late') {
                    $msg = 'Batas absen sudah lewat. Maksimal sampai ' . $window['closeAt']->format('H:i') . '. Status otomatis: Alpa.';
                    $exists = Absensi::query()
                        ->where('id_karyawan', $karyawanId)
                        ->whereDate('tanggal', $today)
                        ->exists();
                    if (! $exists) {
                        Absensi::query()->create([
                            'id_karyawan' => $karyawanId,
                            'tanggal' => $today,
                            'waktu_masuk' => null,
                            'waktu_pulang' => null,
                            'catatan' => 'Alpa otomatis (telat lewat batas).',
                            'status' => 'alpa',
                            'verification_status' => 'pending',
                            'absensi_source' => null,
                            'shift_no' => $shiftNo,
                            'selfie_path' => null,
                            'geo_lat' => null,
                            'geo_lng' => null,
                            'geo_accuracy_m' => null,
                        ]);
                    }
                    return back()->withErrors(['absensi_window' => $msg])->withInput();
                }
                $status = $state === 'alpa' ? 'alpa' : 'hadir';
            } else {
                $status = 'hadir';
            }
        }

        $selfiePath = $request->file('selfie')->storePublicly('absensi-selfie', 'public');

        Absensi::query()->create([
            'id_karyawan' => $karyawanId,
            'tanggal' => $today,
            'waktu_masuk' => $waktuMasuk,
            'waktu_pulang' => null,
            'catatan' => $catatan,
            'status' => $status,
            'verification_status' => 'pending',
            'absensi_source' => 'kasir',
            'shift_no' => $shiftNo,
            'selfie_path' => $selfiePath,
            'geo_lat' => null,
            'geo_lng' => null,
            'geo_accuracy_m' => null,
        ]);

        $timing = $this->absensiTimingInfo($today, $waktuMasuk, (int) ($shiftNo ?? 0), $setting, $karyawanRow?->employment_type);
        $success = 'Absen masuk berhasil.';
        if ($shiftNo) {
            $success .= ' ' . $setting->shiftCodeFor((int) $shiftNo, $karyawanRow?->employment_type) . '.';
        }
        if (!empty($timing['masuk'])) {
            $success .= ' Masuk ' . $timing['masuk'] . '.';
        }
        if (!empty($timing['deltaLabel'])) {
            $success .= ' ' . $timing['deltaLabel'] . '.';
        }
        if ($status === 'alpa') {
            $success .= ' Status: Alpa.';
        }

        return redirect()->route('kasir.absen.form')->with('success', $success);
    }

    private function resolveShiftNoForKaryawan(int $karyawanId, string $date): ?int
    {
        $jadwal = JadwalKaryawan::query()
            ->where('id_karyawan', $karyawanId)
            ->whereDate('tanggal', $date)
            ->first(['shift_ke']);

        $shiftNo = $jadwal ? (int) ($jadwal->shift_ke ?? 0) : null;
        if (! $shiftNo || $shiftNo <= 0) {
            return null;
        }

        return $shiftNo;
    }


    private function resolveCurrentShiftNo(StrukSetting $setting): ?int
    {
        $activeCount = (int) ($setting->active_shift_count ?? 2);
        $activeCount = max(1, min(3, $activeCount));

        if ($activeCount === 1) {
            return 1;
        }

        $now = now();
        $shift1 = Carbon::parse($now->toDateString() . ' ' . ($setting->shift1_start_time ?? '07:00') . ':00');
        $shift2 = Carbon::parse($now->toDateString() . ' ' . ($setting->shift2_start_time ?? '15:00') . ':00');

        if ($activeCount === 2) {
            return $now->greaterThanOrEqualTo($shift2) ? 2 : 1;
        }

        $shift3 = Carbon::parse($now->toDateString() . ' ' . ($setting->shift3_start_time ?? '23:00') . ':00');
        if ($now->greaterThanOrEqualTo($shift3) || $now->lt($shift1)) {
            return 3;
        }

        return $now->greaterThanOrEqualTo($shift2) ? 2 : 1;
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // Haversine formula
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $r * $c;
    }

    public function pulang(Request $request): RedirectResponse
    {
        $today = now()->toDateString();
        $staffId = (int) ($request->session()->get('staff_karyawan_id') ?? 0);

        if ($staffId <= 0) {
            session(['staff_redirect_after_login' => url('/absen')]);
            return redirect()->route('staff.login');
        }

        $absensi = Absensi::query()
            ->where('id_karyawan', $staffId)
            ->whereDate('tanggal', $today)
            ->first();

        if (! $absensi || ! $absensi->waktu_masuk) {
            return back()->withErrors(['absensi' => 'Absen masuk hari ini belum tercatat, jadi absen pulang belum bisa dilakukan.']);
        }

        if ($absensi->waktu_pulang) {
            return back()->withErrors(['absensi' => 'Absen pulang hari ini sudah tercatat.']);
        }

        $now = now();
        if ($now->lte($absensi->waktu_masuk)) {
            return back()->withErrors(['absensi' => 'Absen pulang belum bisa dicatat sebelum jam masuk terlewati.']);
        }

        $absensi->waktu_pulang = $now;
        $absensi->checkout_correction_status = null;
        $absensi->checkout_requested_pulang = null;
        $absensi->checkout_requested_at = null;
        $absensi->checkout_request_note = null;
        $absensi->checkout_review_note = null;
        $absensi->checkout_reviewed_by = null;
        $absensi->checkout_reviewed_at = null;
        $absensi->verification_status = 'pending';
        $absensi->verification_note = null;
        $absensi->verified_by = null;
        $absensi->verified_at = null;
        $absensi->save();

        $durationLabel = $this->workedDurationLabel($absensi->waktu_masuk, $absensi->waktu_pulang);
        $message = 'Absen pulang berhasil dicatat';
        if ($durationLabel) {
            $message .= '. Durasi kerja hari ini: ' . $durationLabel . '.';
        } else {
            $message .= '.';
        }

        app(StaffActivityLogger::class)->log(
            $request,
            $staffId,
            'staff.absen.pulang',
            'Absen Pulang',
            'Melakukan absen pulang untuk tanggal ' . $today . '.',
            [
                'tanggal' => $today,
                'durasi' => $durationLabel,
            ],
            'absensi',
            (int) $absensi->id_absensi,
            $today,
        );

        return redirect()->route('absen.form')->with('success', $message);
    }

    public function requestCheckoutCorrection(Request $request, Absensi $absensi): RedirectResponse
    {
        $staffId = (int) ($request->session()->get('staff_karyawan_id') ?? 0);
        if ($staffId <= 0) {
            session(['staff_redirect_after_login' => url('/absen')]);

            return redirect()->route('staff.login');
        }

        if ((int) $absensi->id_karyawan !== $staffId) {
            abort(404);
        }

        if (! $absensi->waktu_masuk || $absensi->waktu_pulang) {
            return back()->withErrors([
                'absensi' => 'Absensi ini sudah lengkap atau belum punya data masuk, jadi koreksi jam pulang tidak diperlukan.',
            ]);
        }

        $setting = StrukSetting::current();
        $staffKaryawan = $this->activeKaryawanQuery()
            ->where('id_karyawan', $staffId)
            ->first(['id_karyawan', 'nama_karyawan', 'employment_type']);
        $state = $this->checkoutCorrectionState($absensi, $setting, $staffKaryawan);

        if (! $state['can_request']) {
            return back()->withErrors([
                'absensi' => 'Batas koreksi jam pulang untuk absensi ini sudah lewat. Hubungi admin untuk bantuan manual.',
            ]);
        }

        $data = $request->validate([
            'requested_pulang' => ['required', 'date'],
            'request_note' => ['required', 'string', 'max:1000'],
        ]);

        $requestedPulang = Carbon::parse((string) $data['requested_pulang']);
        if ($requestedPulang->lte($absensi->waktu_masuk)) {
            return back()->withErrors([
                'requested_pulang' => 'Jam pulang usulan harus setelah jam masuk.',
            ])->withInput();
        }

        $deadlineAt = $this->checkoutCorrectionDeadlineAt($absensi);
        if ($requestedPulang->gt($deadlineAt)) {
            return back()->withErrors([
                'requested_pulang' => 'Jam pulang usulan terlalu jauh. Maksimal sampai ' . $deadlineAt->translatedFormat('d M Y, H:i') . '.',
            ])->withInput();
        }

        $absensi->checkout_correction_status = Absensi::CHECKOUT_CORRECTION_REQUESTED;
        $absensi->checkout_requested_pulang = $requestedPulang;
        $absensi->checkout_requested_at = now();
        $absensi->checkout_request_note = trim((string) $data['request_note']);
        $absensi->checkout_review_note = null;
        $absensi->checkout_reviewed_by = null;
        $absensi->checkout_reviewed_at = null;
        $absensi->verification_status = 'pending';
        $absensi->verification_note = null;
        $absensi->verified_by = null;
        $absensi->verified_at = null;
        $absensi->save();

        $message = 'Ajukan koreksi absen pulang: ' . $requestedPulang->translatedFormat('d M Y, H:i') . '.';
        $requestNote = trim((string) $data['request_note']);
        if ($requestNote !== '') {
            $message .= ' Catatan: ' . $requestNote;
        }

        $this->notifyAbsensiThread(
            $absensi,
            $message,
            'staff',
            $staffId,
            null,
        );

        app(StaffActivityLogger::class)->log(
            $request,
            $staffId,
            'staff.absen.koreksi_pulang',
            'Ajukan Koreksi Pulang',
            'Mengajukan koreksi jam pulang untuk absensi tanggal ' . ($absensi->tanggal?->toDateString() ?? '-'),
            [
                'tanggal' => $absensi->tanggal?->toDateString(),
                'requested_pulang' => $requestedPulang->toDateTimeString(),
            ],
            'absensi',
            (int) $absensi->id_absensi,
            'Koreksi jam pulang',
        );

        return redirect()->route('absen.form')->with('success', 'Usulan jam pulang berhasil dikirim. Admin akan meninjaunya terlebih dulu.');
    }

    public function approveCheckoutCorrection(Request $request, Absensi $absensi): RedirectResponse
    {
        if (! $absensi->waktu_masuk || $absensi->waktu_pulang) {
            return back()->withErrors([
                'absensi' => 'Koreksi jam pulang tidak diperlukan untuk data ini.',
            ]);
        }

        if ((string) ($absensi->checkout_correction_status ?? '') !== Absensi::CHECKOUT_CORRECTION_REQUESTED || ! $absensi->checkout_requested_pulang) {
            return back()->withErrors([
                'absensi' => 'Belum ada usulan jam pulang yang bisa disetujui.',
            ]);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $absensi->waktu_pulang = $absensi->checkout_requested_pulang;
        $absensi->checkout_correction_status = Absensi::CHECKOUT_CORRECTION_APPROVED;
        $absensi->checkout_review_note = trim((string) ($data['note'] ?? '')) ?: null;
        $absensi->checkout_reviewed_by = (int) auth()->id();
        $absensi->checkout_reviewed_at = now();
        $absensi->verification_status = 'pending';
        $absensi->verification_note = null;
        $absensi->verified_by = null;
        $absensi->verified_at = null;
        $absensi->save();

        $message = 'Koreksi jam pulang disetujui. Jam pulang dicatat ' . $absensi->waktu_pulang->translatedFormat('d M Y, H:i') . '.';
        $note = trim((string) ($data['note'] ?? ''));
        if ($note !== '') {
            $message .= ' Catatan admin: ' . $note;
        }

        $this->notifyAbsensiThread($absensi, $message, 'admin', null, (int) auth()->id());

        app(StaffNotificationService::class)->notify(
            (int) $absensi->id_karyawan,
            StaffNotification::CATEGORY_ATTENDANCE,
            'Koreksi absen pulang disetujui',
            $message,
            route('absen.form'),
            'Buka absen',
            'attendance-correction:' . (int) $absensi->id_absensi . ':approved',
            [
                'type' => 'attendance',
                'absensi_id' => (int) $absensi->id_absensi,
                'status' => 'approved',
            ]
        );

        return back()->with('success', 'Koreksi jam pulang disetujui. Data sekarang siap diverifikasi.');
    }

    public function rejectCheckoutCorrection(Request $request, Absensi $absensi): RedirectResponse
    {
        if (! $absensi->waktu_masuk || $absensi->waktu_pulang) {
            return back()->withErrors([
                'absensi' => 'Koreksi jam pulang tidak diperlukan untuk data ini.',
            ]);
        }

        if ((string) ($absensi->checkout_correction_status ?? '') !== Absensi::CHECKOUT_CORRECTION_REQUESTED) {
            return back()->withErrors([
                'absensi' => 'Belum ada usulan jam pulang yang bisa ditolak.',
            ]);
        }

        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $absensi->checkout_correction_status = Absensi::CHECKOUT_CORRECTION_REJECTED;
        $absensi->checkout_review_note = trim((string) $data['note']);
        $absensi->checkout_reviewed_by = (int) auth()->id();
        $absensi->checkout_reviewed_at = now();
        $absensi->save();

        $this->notifyAbsensiThread(
            $absensi,
            'Koreksi jam pulang ditolak: ' . (string) $data['note'],
            'admin',
            null,
            (int) auth()->id(),
        );

        app(StaffNotificationService::class)->notify(
            (int) $absensi->id_karyawan,
            StaffNotification::CATEGORY_ATTENDANCE,
            'Koreksi absen pulang ditolak',
            'Koreksi jam pulang ditolak admin. Catatan: ' . (string) $data['note'],
            route('absen.form'),
            'Buka absen',
            'attendance-correction:' . (int) $absensi->id_absensi . ':rejected',
            [
                'type' => 'attendance',
                'absensi_id' => (int) $absensi->id_absensi,
                'status' => 'rejected',
            ]
        );

        return back()->with('success', 'Usulan koreksi jam pulang ditolak.');
    }

    public function manualCheckoutCorrection(Request $request, Absensi $absensi): RedirectResponse
    {
        if (! $absensi->waktu_masuk || $absensi->waktu_pulang) {
            return back()->withErrors([
                'absensi' => 'Jam pulang manual hanya bisa diisi untuk absensi yang belum lengkap.',
            ]);
        }

        $data = $request->validate([
            'manual_pulang' => ['required', 'date'],
            'manual_note' => ['required', 'string', 'max:1000'],
        ]);

        $manualPulang = Carbon::parse((string) $data['manual_pulang']);
        if ($manualPulang->lte($absensi->waktu_masuk)) {
            return back()->withErrors([
                'manual_pulang' => 'Jam pulang manual harus setelah jam masuk.',
            ])->withInput();
        }

        $deadlineAt = $this->checkoutCorrectionDeadlineAt($absensi);
        if ($manualPulang->gt($deadlineAt)) {
            return back()->withErrors([
                'manual_pulang' => 'Jam pulang manual terlalu jauh dari jadwal kerja harian.',
            ])->withInput();
        }

        $absensi->waktu_pulang = $manualPulang;
        $absensi->checkout_correction_status = Absensi::CHECKOUT_CORRECTION_MANUAL;
        $absensi->checkout_review_note = trim((string) $data['manual_note']);
        $absensi->checkout_reviewed_by = (int) auth()->id();
        $absensi->checkout_reviewed_at = now();
        $absensi->verification_status = 'pending';
        $absensi->verification_note = null;
        $absensi->verified_by = null;
        $absensi->verified_at = null;
        $absensi->save();

        $this->notifyAbsensiThread(
            $absensi,
            'Admin mengisi jam pulang manual: ' . $manualPulang->translatedFormat('d M Y, H:i') . '. Catatan admin: ' . (string) $data['manual_note'],
            'admin',
            null,
            (int) auth()->id(),
        );

        app(StaffNotificationService::class)->notify(
            (int) $absensi->id_karyawan,
            StaffNotification::CATEGORY_ATTENDANCE,
            'Jam pulang manual ditambahkan admin',
            'Admin mengisi jam pulang manual: ' . $manualPulang->translatedFormat('d M Y, H:i') . '. Catatan: ' . (string) $data['manual_note'],
            route('absen.form'),
            'Buka absen',
            'attendance-correction:' . (int) $absensi->id_absensi . ':manual',
            [
                'type' => 'attendance',
                'absensi_id' => (int) $absensi->id_absensi,
                'status' => 'manual',
            ]
        );

        return back()->with('success', 'Jam pulang manual berhasil disimpan. Data sekarang siap diverifikasi.');
    }

    public function index(Request $request): View
    {
        $todayObj = now();
        $today = $todayObj->toDateString();
        $defaultStart = $todayObj->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $defaultEnd = $todayObj->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $tanggalAwal = (string) $request->get('tanggal_awal', $defaultStart);
        $tanggalAkhir = (string) $request->get('tanggal_akhir', $defaultEnd);
        $karyawanId = $request->get('id_karyawan');
        $correctionFilter = (string) $request->get('correction_state', 'all');
        $setting = StrukSetting::current();
        $allRows = $this->buildAdminAttendanceCollection(
            $tanggalAwal,
            $tanggalAkhir,
            $karyawanId,
            $setting,
            'all',
        );
        $filteredRows = $correctionFilter === 'all'
            ? $allRows
            : $allRows->filter(
                fn (Absensi $row) => $this->correctionService()->matchesFilter((array) ($row->correction_meta ?? []), $correctionFilter)
            )->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 20;
        $rows = new LengthAwarePaginator(
            $filteredRows->slice(($page - 1) * $perPage, $perPage)->values(),
            $filteredRows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $karyawan = Karyawan::query()
            ->orderBy('nama_karyawan')
            ->get(['id_karyawan', 'nama_karyawan', 'jabatan']);

        $totalMasuk = (int) $allRows->whereNotNull('waktu_masuk')->count();
        $correctionSummary = $this->attendanceCorrectionSummary($allRows);

        $hasActiveFilter = $karyawanId !== null
            || $correctionFilter !== 'all'
            || ($tanggalAwal !== $defaultStart || $tanggalAkhir !== $defaultEnd);

        return view('dashboard.absensi', [
            'tanggalAwal' => $tanggalAwal,
            'tanggalAkhir' => $tanggalAkhir,
            'selectedKaryawanId' => is_numeric($karyawanId) ? (int) $karyawanId : null,
            'selectedCorrectionFilter' => $correctionFilter,
            'correctionFilterOptions' => $this->correctionFilterOptions(),
            'correctionSummary' => $correctionSummary,
            'rows' => $rows,
            'karyawan' => $karyawan,
            'totalMasuk' => $totalMasuk,
            'setting' => $setting,
            'hasActiveFilter' => $hasActiveFilter,
        ]);
    }

    public function verify(Request $request, Absensi $absensi): RedirectResponse
    {
        if (! $this->readyForAdminFinalization($absensi)) {
            return back()->withErrors([
                'absensi' => 'Absensi belum bisa diverifikasi sebelum staf melakukan absen pulang.',
            ]);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $absensi->verification_status = 'verified';
        $absensi->verification_note = $data['note'] ?? null;
        $absensi->verified_by = (int) auth()->id();
        $absensi->verified_at = now();
        $absensi->save();

        $note = trim((string) ($data['note'] ?? ''));
        $message = $note !== '' ? ('Absensi diverifikasi: ' . $note) : 'Absensi diverifikasi.';
        $this->notifyAbsensiThread($absensi, $message, 'admin', null, (int) auth()->id());

        return back()->with('success', 'Absensi diverifikasi.');
    }

    public function reject(Request $request, Absensi $absensi): RedirectResponse
    {
        if (! $this->readyForAdminFinalization($absensi)) {
            return back()->withErrors([
                'absensi' => 'Absensi belum bisa ditolak sebelum data masuk dan pulang harian lengkap.',
            ]);
        }

        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $absensi->verification_status = 'rejected';
        $absensi->verification_note = (string) $data['note'];
        $absensi->verified_by = (int) auth()->id();
        $absensi->verified_at = now();
        $absensi->save();

        $this->notifyAbsensiThread(
            $absensi,
            'Absensi ditolak: ' . (string) $data['note'],
            'admin',
            null,
            (int) auth()->id(),
        );

        return back()->with('success', 'Absensi ditolak.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $normalizedGeoLat = str_replace(',', '.', trim((string) $request->input('absensi_geo_lat', '')));
        $normalizedGeoLng = str_replace(',', '.', trim((string) $request->input('absensi_geo_lng', '')));
        $request->merge([
            'absensi_geo_lat' => $normalizedGeoLat === '' ? null : $normalizedGeoLat,
            'absensi_geo_lng' => $normalizedGeoLng === '' ? null : $normalizedGeoLng,
        ]);

        $data = $request->validate([
            'absensi_require_selfie' => ['nullable', 'boolean'],
            'absensi_require_geofence' => ['nullable', 'boolean'],
            'absensi_geo_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'absensi_geo_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'absensi_geo_radius_m' => ['nullable', 'integer', 'between:10,5000'],
            'absensi_geo_max_accuracy_m' => ['nullable', 'integer', 'between:5,500'],
            'shift1_start_time' => ['nullable', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'shift2_start_time' => ['nullable', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'shift3_start_time' => ['nullable', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'absensi_late_tolerance_minutes' => ['nullable', 'integer', 'between:0,180'],
            'absensi_checkin_before_minutes' => ['nullable', 'integer', 'between:0,240'],
            'absensi_checkin_after_minutes' => ['nullable', 'integer', 'between:0,240'],
        ]);

        $setting = StrukSetting::current();
        $setting->absensi_require_selfie = $request->boolean('absensi_require_selfie');
        $setting->absensi_require_geofence = $request->boolean('absensi_require_geofence');
        $setting->absensi_geo_lat = isset($data['absensi_geo_lat']) ? (float) $data['absensi_geo_lat'] : null;
        $setting->absensi_geo_lng = isset($data['absensi_geo_lng']) ? (float) $data['absensi_geo_lng'] : null;
        $setting->absensi_geo_radius_m = (int) ($data['absensi_geo_radius_m'] ?? ($setting->absensi_geo_radius_m ?? 150));
        $setting->absensi_geo_max_accuracy_m = (int) ($data['absensi_geo_max_accuracy_m'] ?? ($setting->absensi_geo_max_accuracy_m ?? 80));
        $setting->shift1_start_time = (string) ($data['shift1_start_time'] ?? ($setting->shift1_start_time ?? '07:00'));
        $setting->shift2_start_time = (string) ($data['shift2_start_time'] ?? ($setting->shift2_start_time ?? '15:00'));
        $setting->shift3_start_time = (string) ($data['shift3_start_time'] ?? ($setting->shift3_start_time ?? '23:00'));
        $setting->absensi_late_tolerance_minutes = (int) ($data['absensi_late_tolerance_minutes'] ?? ($setting->absensi_late_tolerance_minutes ?? 10));
        $setting->absensi_checkin_before_minutes = (int) ($data['absensi_checkin_before_minutes'] ?? ($setting->absensi_checkin_before_minutes ?? 30));
        $setting->absensi_checkin_after_minutes = (int) ($data['absensi_checkin_after_minutes'] ?? ($setting->absensi_checkin_after_minutes ?? 60));
        $setting->save();

        return redirect()
            ->route('dashboard.absensi.settings')
            ->with('success', 'Pengaturan absensi berhasil diperbarui.');
    }

    public function settingsForm(): View
    {
        $setting = StrukSetting::current();

        return view('dashboard.absensi-settings', [
            'setting' => $setting,
        ]);
    }

    private function correctionFilterOptions(): array
    {
        return [
            'all' => 'Semua Status',
            'needs_attention' => 'Perlu Koreksi',
            'requested' => 'Menunggu Koreksi Admin',
            'forgot' => 'Lupa Absen Pulang',
            'rejected' => 'Koreksi Ditolak',
            'waiting_checkout' => 'Menunggu Pulang',
        ];
    }

    private function buildAdminAttendanceCollection(
        string $tanggalAwal,
        string $tanggalAkhir,
        mixed $karyawanId,
        StrukSetting $setting,
        string $correctionFilter = 'all',
    ) {
        $service = $this->correctionService();

        return Absensi::query()
            ->with('karyawan')
            ->whereDate('tanggal', '>=', $tanggalAwal)
            ->whereDate('tanggal', '<=', $tanggalAkhir)
            ->orderByDesc('tanggal')
            ->orderByDesc('waktu_masuk')
            ->when(is_numeric($karyawanId), function ($query) use ($karyawanId) {
                $query->where('id_karyawan', (int) $karyawanId);
            })
            ->get()
            ->map(function (Absensi $row) use ($setting, $service) {
                $row->setAttribute('correction_meta', $service->state($row, $setting, $row->karyawan));

                return $row;
            })
            ->filter(fn (Absensi $row) => $service->matchesFilter((array) ($row->correction_meta ?? []), $correctionFilter))
            ->values();
    }

    private function attendanceCorrectionSummary($rows): array
    {
        $service = $this->correctionService();

        return [
            'needs_attention' => $rows->filter(fn (Absensi $row) => $service->matchesFilter((array) ($row->correction_meta ?? []), 'needs_attention'))->count(),
            'requested' => $rows->filter(fn (Absensi $row) => $service->matchesFilter((array) ($row->correction_meta ?? []), 'requested'))->count(),
            'forgot' => $rows->filter(fn (Absensi $row) => $service->matchesFilter((array) ($row->correction_meta ?? []), 'forgot'))->count(),
            'waiting_checkout' => $rows->filter(fn (Absensi $row) => $service->matchesFilter((array) ($row->correction_meta ?? []), 'waiting_checkout'))->count(),
        ];
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $today = now()->toDateString();
        $tanggalAwal = (string) $request->get('tanggal_awal', $today);
        $tanggalAkhir = (string) $request->get('tanggal_akhir', $today);
        $karyawanId = $request->get('id_karyawan');
        $correctionFilter = (string) $request->get('correction_state', 'all');
        $setting = StrukSetting::current();

        $rows = $this->buildAdminAttendanceCollection(
            $tanggalAwal,
            $tanggalAkhir,
            $karyawanId,
            $setting,
            $correctionFilter,
        )->sortBy([
            ['tanggal', 'asc'],
            ['waktu_masuk', 'asc'],
        ])->values();
        $byTanggal = $rows->groupBy(fn ($r) => $r->tanggal?->format('Y-m-d') ?? '-');
        $correctionFilterLabel = (string) ($this->correctionFilterOptions()[$correctionFilter] ?? $correctionFilter);

        $filename = 'absensi-' . now()->format('Ymd-His') . '.xls';

        return response()->streamDownload(function () use ($tanggalAwal, $tanggalAkhir, $rows, $byTanggal, $correctionFilter, $correctionFilterLabel): void {
            echo '<html><head><meta charset="UTF-8">';
            echo '<style>';
            echo 'body{font-family:Arial,sans-serif;font-size:12px;}';
            echo 'h2{margin:0 0 8px 0;}';
            echo 'h3{margin:18px 0 8px 0;}';
            echo '.meta{margin:0 0 12px 0;line-height:1.6;}';
            echo 'table{border-collapse:collapse;width:100%;margin-bottom:14px;}';
            echo 'th,td{border:1px solid #444;padding:6px;}';
            echo 'th{background:#eaeaea;text-align:left;}';
            echo '.num{text-align:right;}';
            echo '</style></head><body>';

            echo '<h2>Laporan Absensi</h2>';
            echo '<div class="meta">';
            echo 'Tanggal awal: ' . htmlspecialchars($tanggalAwal, ENT_QUOTES, 'UTF-8') . '<br>';
            echo 'Tanggal akhir: ' . htmlspecialchars($tanggalAkhir, ENT_QUOTES, 'UTF-8') . '<br>';
            if ($correctionFilter !== 'all') {
                echo 'Filter koreksi: ' . htmlspecialchars($correctionFilterLabel, ENT_QUOTES, 'UTF-8') . '<br>';
            }
            echo 'Total masuk: ' . (int) $rows->whereNotNull('waktu_masuk')->count() . '<br>';
            echo 'Dicetak: ' . htmlspecialchars(now()->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');
            echo '</div>';

            echo '<h3>Rekap Per Hari</h3>';
            echo '<table><thead><tr><th>Tanggal</th><th class="num">Total Masuk</th></tr></thead><tbody>';
            foreach ($byTanggal as $tgl => $items) {
                $count = (int) $items->whereNotNull('waktu_masuk')->count();
                echo '<tr><td>' . htmlspecialchars((string) $tgl, ENT_QUOTES, 'UTF-8') . '</td><td class="num">' . $count . '</td></tr>';
            }
            echo '</tbody></table>';

            echo '<h3>Detail Absensi</h3>';
            echo '<table><thead><tr><th>Tanggal</th><th>Karyawan</th><th>Jabatan</th><th>Masuk</th><th>Catatan</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row->tanggal?->format('Y-m-d') ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars((string) ($row->karyawan?->nama_karyawan ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars((string) ($row->karyawan?->jabatan ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars($row->waktu_masuk ? $row->waktu_masuk->format('H:i') : '-', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars((string) ($row->catatan ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            echo '</body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }
}
