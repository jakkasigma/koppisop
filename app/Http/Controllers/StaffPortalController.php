<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\JadwalKaryawan;
use App\Models\JadwalTukarRequest;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\StaffMessage;
use App\Models\StaffMessageRead;
use App\Models\StaffNotification;
use App\Models\StrukSetting;
use App\Services\StaffActivityLogger;
use App\Services\StaffNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StaffPortalController extends Controller
{
    private function parseAnnouncementLines(string $body): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $body) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();
    }

    private function extractPromoSegments(string $body): array
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $body));

        if ($normalized === '') {
            return [
                'headline' => null,
                'nilai' => null,
                'harga' => null,
                'periode' => null,
                'berakhir' => null,
                'status' => null,
                'minimal' => null,
            ];
        }

        $extract = function (string $pattern) use ($normalized): ?string {
            if (preg_match($pattern, $normalized, $matches)) {
                $value = trim((string) ($matches[1] ?? ''));
                return $value !== '' ? $value : null;
            }

            return null;
        };

        $firstMetaPos = null;
        foreach ([
            'nilai:',
            'harga bundle:',
            'periode:',
            'berakhir pada:',
            'status:',
            'min belanja:',
            'minimal belanja:',
        ] as $needle) {
            $pos = mb_stripos($normalized, $needle);
            if ($pos !== false) {
                $firstMetaPos = $firstMetaPos === null ? $pos : min($firstMetaPos, $pos);
            }
        }

        $headline = $firstMetaPos !== null
            ? trim((string) mb_substr($normalized, 0, $firstMetaPos))
            : $normalized;

        return [
            'headline' => $headline !== '' ? $headline : null,
            'nilai' => $extract('/Nilai:\s*(.+?)(?=\s+(?:Periode:|Status:|Min(?:imal)? belanja:|Berakhir pada:|Harga bundle:)|$)/iu'),
            'harga' => $extract('/Harga bundle:\s*(.+?)(?=\s+(?:Periode:|Status:|Min(?:imal)? belanja:|Berakhir pada:|Nilai:)|$)/iu'),
            'periode' => $extract('/Periode:\s*(.+?)(?=\s+(?:Status:|Min(?:imal)? belanja:|Berakhir pada:|Nilai:|Harga bundle:)|$)/iu'),
            'berakhir' => $extract('/Berakhir pada:\s*(.+?)(?=\s+(?:Status:|Min(?:imal)? belanja:|Periode:|Nilai:|Harga bundle:)|$)/iu'),
            'status' => $extract('/Status:\s*(.+?)(?=\s+(?:Periode:|Min(?:imal)? belanja:|Berakhir pada:|Nilai:|Harga bundle:)|$)/iu'),
            'minimal' => $extract('/Min(?:imal)? belanja:\s*(.+?)(?=\s+(?:Status:|Periode:|Berakhir pada:|Nilai:|Harga bundle:)|$)/iu'),
        ];
    }

    private function mapPromoStatusVisual(?string $value): array
    {
        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            'aktif', 'berjalan', 'akan berakhir' => ['label' => 'Aktif', 'class' => 'on'],
            'terjadwal', 'akan mulai' => ['label' => 'Terjadwal', 'class' => 'wait'],
            'berakhir', 'nonaktif', 'tidak berlaku' => ['label' => 'Berakhir', 'class' => 'off'],
            default => ['label' => trim((string) $value) !== '' ? trim((string) $value) : 'Info', 'class' => 'plain'],
        };
    }

    private function decorateAnnouncementCard(Announcement $announcement, string $staffRole): Announcement
    {
        $presentation = $this->inferAnnouncementPresentation($announcement, $staffRole);
        $body = (string) ($announcement->body ?? '');
        $lines = $this->parseAnnouncementLines($body);
        $publishedLabel = $announcement->published_at ? $announcement->published_at->format('d M Y - H:i') : '-';
        $summary = \Illuminate\Support\Str::limit(trim((string) preg_replace('/\s+/', ' ', $body)), 92);
        $details = [];
        $statusBadge = null;
        $cardTitle = (string) ($announcement->title ?? 'Pengumuman');
        $cardSubtitle = (string) ($presentation['subtitle'] ?? 'Informasi terbaru untuk staf');

        if (($presentation['type'] ?? '') === 'promo') {
            $promoSegments = $this->extractPromoSegments($body);
            $headline = trim((string) ($promoSegments['headline'] ?? ($lines[0] ?? $cardTitle)));
            $cardTitle = $headline !== '' ? $headline : $cardTitle;

            $rawSubtitle = trim((string) ($announcement->title ?? ''));
            $computedPromoStatus = trim((string) ($announcement->promo_status ?? ''));
            $cardSubtitle = $rawSubtitle !== '' && strcasecmp($rawSubtitle, $cardTitle) !== 0
                ? $rawSubtitle
                : ($computedPromoStatus !== '' ? $computedPromoStatus : 'Promo cafe');

            $plainSummary = [];
            $pushDetail = function (string $key, string $label, ?string $value, string $class = 'plain') use (&$details): void {
                $value = trim((string) $value);
                if ($value === '') {
                    return;
                }

                foreach ($details as $detail) {
                    if (($detail['key'] ?? null) === $key) {
                        return;
                    }
                }

                $details[] = [
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                    'class' => $class,
                ];
            };

            foreach (array_slice($lines, 1) as $line) {
                if (preg_match('/^Nilai:\s*(.+)$/i', $line, $matches)) {
                    $pushDetail('nilai', 'Nilai', $matches[1]);
                    continue;
                }
                if (preg_match('/^Harga bundle:\s*(.+)$/i', $line, $matches)) {
                    $pushDetail('harga', 'Harga', $matches[1]);
                    continue;
                }
                if (preg_match('/^Periode:\s*(.+)$/i', $line, $matches)) {
                    $pushDetail('periode', 'Periode', $matches[1]);
                    continue;
                }
                if (preg_match('/^Berakhir pada:\s*(.+)$/i', $line, $matches)) {
                    $pushDetail('berakhir', 'Berakhir', $matches[1]);
                    continue;
                }
                if (preg_match('/^(?:Min(?:imal)? belanja):\s*(.+)$/i', $line, $matches)) {
                    $pushDetail('minimal', 'Min. belanja', $matches[1]);
                    continue;
                }
                if (preg_match('/^Status:\s*(.+)$/i', $line, $matches)) {
                    $statusBadge = $this->mapPromoStatusVisual($matches[1]);
                    continue;
                }

                $plainSummary[] = $line;
            }

            $pushDetail('nilai', 'Nilai', $promoSegments['nilai'] ?? null);
            $pushDetail('harga', 'Harga', $promoSegments['harga'] ?? null);
            $pushDetail('periode', 'Periode', $promoSegments['periode'] ?? null);
            $pushDetail('berakhir', 'Berakhir', $promoSegments['berakhir'] ?? null);
            $pushDetail('minimal', 'Min. belanja', $promoSegments['minimal'] ?? null);

            if ($computedPromoStatus !== '') {
                $statusBadge = $this->mapPromoStatusVisual($computedPromoStatus);
            } elseif (! $statusBadge && ! empty($promoSegments['status'])) {
                $statusBadge = $this->mapPromoStatusVisual((string) $promoSegments['status']);
            }

            $summaryBits = [];
            if (! empty($promoSegments['nilai'])) {
                $summaryBits[] = 'Nilai promo ' . $promoSegments['nilai'];
            }
            if (! empty($promoSegments['harga'])) {
                $summaryBits[] = 'Harga bundle ' . $promoSegments['harga'];
            }
            if (! empty($promoSegments['minimal'])) {
                $summaryBits[] = 'Min. belanja ' . $promoSegments['minimal'];
            }
            if (empty($summaryBits) && ! empty($promoSegments['periode'])) {
                $summaryBits[] = 'Berlaku ' . $promoSegments['periode'];
            }

            $summary = ! empty($plainSummary)
                ? \Illuminate\Support\Str::limit(implode(' • ', $plainSummary), 88)
                : (! empty($summaryBits)
                    ? \Illuminate\Support\Str::limit(implode(' • ', $summaryBits), 88)
                    : 'Promo ini sedang berjalan. Cek nilai, periode, dan status di bawah.');

            $details = array_map(function (array $detail): array {
                unset($detail['key']);
                return $detail;
            }, $details);
        } else {
            $details[] = [
                'label' => (string) ($presentation['detailLabel'] ?? 'Sumber'),
                'value' => (string) ($presentation['detailValue'] ?? 'Admin Cafe'),
                'class' => 'plain',
            ];
        }

        $details[] = ['label' => 'Terbit', 'value' => $publishedLabel, 'class' => 'plain'];

        $announcement->staff_card_type = $presentation['type'];
        $announcement->staff_card_label = $presentation['label'];
        $announcement->staff_card_subtitle = $cardSubtitle;
        $announcement->staff_card_title = $cardTitle;
        $announcement->staff_card_summary = $summary;
        $announcement->staff_card_details = $details;
        $announcement->staff_card_status = $statusBadge;

        return $announcement;
    }

    private function inferAnnouncementPresentation(Announcement $announcement, string $staffRole): array
    {
        $title = mb_strtolower(trim((string) ($announcement->title ?? '')));
        $body = mb_strtolower(trim((string) ($announcement->body ?? '')));
        $targetRole = trim((string) ($announcement->target_role ?? ''));
        $promoInfo = $announcement->resolvePromoStatus(now());

        $isPromo = str_contains($title, 'promo')
            || str_contains($title, 'diskon')
            || str_contains($title, 'bundling')
            || str_contains($body, 'promo')
            || str_contains($body, 'diskon')
            || str_contains($body, 'bundling');

        if ($isPromo) {
            $status = (string) ($promoInfo['status'] === 'Akan Mulai' ? 'Terjadwal' : ($promoInfo['status'] ?? 'Promo Aktif'));

            return [
                'type' => 'promo',
                'label' => 'Info Promo',
                'subtitle' => $status !== '' ? $status : 'Promo cafe',
                'detailLabel' => 'Status',
                'detailValue' => $status !== '' ? $status : 'Aktif',
            ];
        }

        if ($targetRole !== '') {
            $roleLabel = $targetRole;
            if ($staffRole !== '' && strcasecmp($targetRole, $staffRole) === 0) {
                $roleLabel = 'Khusus ' . $staffRole;
            }

            return [
                'type' => 'role',
                'label' => 'Info Posisi',
                'subtitle' => $roleLabel,
                'detailLabel' => 'Untuk',
                'detailValue' => $roleLabel,
            ];
        }

        return [
            'type' => 'admin',
            'label' => 'Info Admin',
            'subtitle' => 'Informasi umum operasional',
            'detailLabel' => 'Sumber',
            'detailValue' => 'Admin Cafe',
        ];
    }

    private function normalizePhone(string $value): string
    {
        // Keep digits only. No strict E.164 here; cafe input is usually 08xx / 62xx.
        $digits = preg_replace('/\\D+/', '', $value) ?? '';
        return trim($digits);
    }

    public function showLogin(): View
    {
        return view('staff.login', [
            'setting' => StrukSetting::current(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'no_telepon' => ['required', 'string', 'max:30'],
            'pin' => ['required', 'string', 'regex:/^[0-9]{4,8}$/'],
        ]);

        $phoneRaw = trim((string) $data['no_telepon']);
        $phone = $this->normalizePhone($phoneRaw);
        $pin = trim((string) ($data['pin'] ?? ''));
        $digest = Karyawan::pinDigest($pin);

        $q = Karyawan::query();
        if (Schema::hasColumn('karyawan', 'is_active')) {
            $q->where('is_active', true);
        }

        $karyawan = $q
            ->where(function ($w) use ($phoneRaw, $phone): void {
                $w->where('no_telepon', $phoneRaw);
                if ($phone !== '') {
                    $w->orWhere('no_telepon', $phone);
                }
            })
            ->first(['id_karyawan', 'nama_karyawan', 'jabatan', 'no_telepon', 'employment_type', 'pin_digest']);

        if (! $karyawan) {
            return back()->withErrors([
                'login' => 'No telepon tidak valid.',
            ])->withInput();
        }

        // PIN is mandatory for staff portal access.
        if (!is_string($karyawan->pin_digest ?? null) || trim((string) $karyawan->pin_digest) === '') {
            return back()->withErrors([
                'login' => 'Akun ini belum memiliki PIN. Hubungi admin untuk set PIN.',
            ])->withInput();
        }

        if (! hash_equals((string) $karyawan->pin_digest, (string) $digest)) {
            return back()->withErrors([
                'login' => 'PIN tidak valid.',
            ])->withInput();
        }

        $request->session()->regenerate();
        $request->session()->put('staff_karyawan_id', (int) $karyawan->id_karyawan);
        $request->session()->put('staff_karyawan_name', (string) ($karyawan->nama_karyawan ?? ''));
        $request->session()->put('staff_last_activity', time());

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.login',
            'Login Portal Staff',
            'Masuk ke portal staf melalui nomor telepon dan PIN.',
        );

        $redirect = (string) ($request->session()->pull('staff_redirect_after_login') ?? '');
        if ($redirect !== '' && str_starts_with($redirect, url('/'))) {
            return redirect()->to($redirect);
        }

        return redirect()->route('staff.home');
    }

    public function logout(Request $request): RedirectResponse
    {
        $staffId = (int) ($request->session()->get('staff_karyawan_id') ?? 0);
        if ($staffId > 0) {
            app(StaffActivityLogger::class)->log(
                $request,
                $staffId,
                'staff.logout',
                'Logout Portal Staff',
                'Keluar dari portal staf.',
            );
        }

        $request->session()->forget(['staff_karyawan_id', 'staff_karyawan_name', 'staff_last_activity']);
        $request->session()->regenerate();
        return redirect()->route('staff.login');
    }

    public function home(Request $request): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $today = now()->toDateString();
        $setting = StrukSetting::current();
        $employmentType = method_exists($karyawan, 'employmentTypeValue')
            ? $karyawan->employmentTypeValue()
            : Karyawan::normalizeEmploymentType($karyawan?->employment_type ?? null);

        $absenHariIni = Absensi::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', $today)
            ->first();

        $jadwalHariIni = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', $today)
            ->first();

        $jadwal7Hari = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', '>=', $today)
            ->whereDate('tanggal', '<=', now()->addDays(6)->toDateString())
            ->orderBy('tanggal')
            ->get();

        $riwayat = Absensi::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->orderByDesc('tanggal')
            ->orderByDesc('waktu_masuk')
            ->limit(10)
            ->get();

        $shiftStarts = [
            1 => $setting->shiftStartTimeFor(1, $employmentType),
            2 => $setting->shiftStartTimeFor(2, $employmentType),
            3 => $setting->shiftStartTimeFor(3, $employmentType),
        ];
        $tol = (int) ($setting->absensi_late_tolerance_minutes ?? 10);

        $jadwal7ByTanggal = $jadwal7Hari->keyBy(fn ($r) => $r->tanggal?->format('Y-m-d') ?? '-');
        $absen7 = Absensi::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', '>=', $today)
            ->whereDate('tanggal', '<=', now()->addDays(6)->toDateString())
            ->orderBy('tanggal')
            ->get(['tanggal', 'waktu_masuk', 'status', 'shift_no']);
        $absen7ByTanggal = $absen7->keyBy(fn ($r) => $r->tanggal?->format('Y-m-d') ?? '-');

        $jadwal7View = [];
        $cursor = Carbon::parse($today)->startOfDay();
        for ($i = 0; $i < 7; $i++) {
            $tgl = $cursor->toDateString();
            $jadwalRow = $jadwal7ByTanggal[$tgl] ?? null;
            $shiftNo = $jadwalRow?->shift_ke ? (int) $jadwalRow->shift_ke : null;
            $shiftStart = $shiftNo ? (string) ($shiftStarts[$shiftNo] ?? '') : '';

            $absRow = $absen7ByTanggal[$tgl] ?? null;
            $masuk = ($absRow && $absRow->waktu_masuk) ? $absRow->waktu_masuk->format('H:i') : null;

            $deltaLabel = null;
            $deltaClass = 'gray';
            $lateMinutes = null;
            if ($masuk && $shiftNo && $shiftStart !== '') {
                $startAt = Carbon::parse($tgl . ' ' . $shiftStart . ':00');
                $deltaMin = (int) $absRow->waktu_masuk->diffInMinutes($startAt, false);
                if ($deltaMin > $tol) { $lateMinutes = $deltaMin; $deltaClass = 'bad'; }
                elseif ($deltaMin > 0) { $deltaLabel = 'Lewat ' . $deltaMin . 'm'; $deltaClass = 'gray'; }
                elseif ($deltaMin < 0) { $deltaLabel = 'Lebih awal ' . abs($deltaMin) . 'm'; $deltaClass = 'gray'; }
                else { $deltaLabel = 'Tepat waktu'; $deltaClass = 'gray'; }
            }

            $status = (string) ($absRow?->status ?? '');
            $statusLabel = '-';
            $statusClass = 'gray';
            if ($shiftNo) {
                if ($masuk || $status === 'alpa') {
                    if ($status === 'alpa') {
                        $statusLabel = 'Alpa';
                        $statusClass = 'bad';
                    } elseif ($lateMinutes !== null) {
                        $statusLabel = 'Telat (' . $lateMinutes . 'm)';
                        $statusClass = 'bad';
                    } else {
                        $statusLabel = $status === 'telat' ? 'Telat' : 'Hadir';
                        $statusClass = $status === 'telat' ? 'bad' : '';
                    }
                } else {
                    // Today: expected to absen if scheduled.
                    if ($tgl === $today) {
                        $statusLabel = 'Belum absen';
                        $statusClass = 'warn';
                    } else {
                        $statusLabel = 'Terjadwal';
                        $statusClass = 'gray';
                    }
                }
            } elseif ($status === 'tidak_dijadwalkan') {
                $statusLabel = 'Tidak dijadwalkan';
                $statusClass = 'warn';
            }

            $jadwal7View[] = [
                'tanggal' => $tgl,
                'shiftNo' => $shiftNo,
                'shiftCode' => $shiftNo ? $setting->shiftCodeFor($shiftNo, $employmentType) : null,
                'shiftStart' => $shiftStart !== '' ? $shiftStart : null,
                'masuk' => $masuk,
                'deltaLabel' => $deltaLabel,
                'deltaClass' => $deltaClass,
                'statusLabel' => $statusLabel,
                'statusClass' => $statusClass,
                'isToday' => $tgl === $today,
            ];

            $cursor->addDay();
        }

        $riwayatView = [];
        if ($riwayat->count() > 0) {
            $dates = $riwayat
                ->pluck('tanggal')
                ->filter()
                ->map(fn ($d) => Carbon::parse((string) $d)->toDateString())
                ->unique()
                ->values()
                ->all();

            $jadwalByDate = [];
            if (count($dates) > 0) {
                $minDate = min($dates);
                $maxDate = max($dates);

                $jadwalRows = JadwalKaryawan::query()
                    ->where('id_karyawan', (int) $karyawan->id_karyawan)
                    ->whereDate('tanggal', '>=', $minDate)
                    ->whereDate('tanggal', '<=', $maxDate)
                    ->get();

                foreach ($jadwalRows as $j) {
                    $key = $j->tanggal?->format('Y-m-d') ?? null;
                    if (is_string($key) && $key !== '') {
                        $jadwalByDate[$key] = $j;
                    }
                }
            }

            foreach ($riwayat as $r) {
                $tgl = $r->tanggal?->format('Y-m-d') ?? null;
                if (! is_string($tgl) || $tgl === '') {
                    continue;
                }

                $jadwalRow = $jadwalByDate[$tgl] ?? null;
                $shiftNo = (int) ($r->shift_no ?? ($jadwalRow?->shift_ke ?? 0));
                $shiftStart = $shiftNo > 0 ? (string) ($shiftStarts[$shiftNo] ?? '') : '';

                $masuk = $r->waktu_masuk ? $r->waktu_masuk->format('H:i') : null;

                $deltaLabel = null;
                $deltaClass = 'gray';
                $lateMinutes = null;
                if ($masuk && $shiftNo > 0 && $shiftStart !== '') {
                    $startAt = Carbon::parse($tgl . ' ' . $shiftStart . ':00');
                    $deltaMin = (int) $r->waktu_masuk->diffInMinutes($startAt, false);
                    if ($deltaMin > $tol) { $lateMinutes = $deltaMin; $deltaClass = 'bad'; }
                    elseif ($deltaMin > 0) { $deltaLabel = 'Lewat ' . $deltaMin . 'm'; $deltaClass = 'gray'; }
                    elseif ($deltaMin < 0) { $deltaLabel = 'Lebih awal ' . abs($deltaMin) . 'm'; $deltaClass = 'gray'; }
                    else { $deltaLabel = 'Tepat waktu'; $deltaClass = 'gray'; }
                }

                $status = (string) ($r->status ?? '');
                $statusLabel = $status === 'alpa'
                    ? 'Alpa'
                    : ($lateMinutes !== null
                        ? 'Telat (' . $lateMinutes . 'm)'
                        : ($status === 'telat'
                            ? 'Telat'
                            : ($status === 'hadir'
                                ? 'Hadir'
                                : ($status === 'tidak_dijadwalkan'
                                    ? 'Tidak dijadwalkan'
                                    : ($masuk ? 'Hadir' : 'Belum')))));
                $statusClass = $status === 'alpa' ? 'bad' : (($lateMinutes !== null || $status === 'telat') ? 'bad' : 'gray');
                if ($status === 'tidak_dijadwalkan') {
                    $statusClass = 'warn';
                }

                $riwayatView[] = [
                    'tanggal' => $tgl,
                    'shiftNo' => $shiftNo > 0 ? $shiftNo : null,
                    'shiftCode' => $shiftNo > 0 ? $setting->shiftCodeFor($shiftNo, $employmentType) : null,
                    'shiftStart' => $shiftStart !== '' ? $shiftStart : null,
                    'masuk' => $masuk,
                    'statusLabel' => $statusLabel,
                    'statusClass' => $statusClass,
                    'deltaLabel' => $deltaLabel,
                    'deltaClass' => $deltaClass,
                ];
            }
        }

        $absenSummary = null;
        if ($absenHariIni && $absenHariIni->waktu_masuk) {
            $shiftNo = (int) ($absenHariIni->shift_no ?? ($jadwalHariIni?->shift_ke ?? 0));
            $startTime = $shiftNo > 0 ? $setting->shiftStartTimeFor($shiftNo, $employmentType) : '';
            $startAt = $shiftNo > 0 ? Carbon::parse($today . ' ' . $startTime . ':00') : null;
            $deltaMin = ($startAt && $shiftNo > 0) ? (int) $absenHariIni->waktu_masuk->diffInMinutes($startAt, false) : null;
            $tol = (int) ($setting->absensi_late_tolerance_minutes ?? 10);

            $deltaLabel = null;
            $deltaClass = 'ok';
            $lateMinutes = null;
            if ($deltaMin !== null) {
                if ($deltaMin > $tol) { $lateMinutes = $deltaMin; $deltaClass = 'bad'; }
                elseif ($deltaMin > 0) { $deltaLabel = 'Lewat ' . $deltaMin . 'm'; $deltaClass = 'ok'; }
                elseif ($deltaMin < 0) { $deltaLabel = 'Lebih awal ' . abs($deltaMin) . 'm'; $deltaClass = 'ok'; }
                else { $deltaLabel = 'Tepat waktu'; $deltaClass = 'ok'; }
            }

            $status = (string) ($absenHariIni->status ?? '');
            $statusLabel = $status === 'alpa'
                ? 'Alpa'
                : ($lateMinutes !== null
                    ? 'Telat (' . $lateMinutes . 'm)'
                    : ($status === 'telat'
                        ? 'Telat'
                        : ($status === 'hadir' ? 'Hadir' : ($status === 'tidak_dijadwalkan' ? 'Tidak dijadwalkan' : 'OK'))));
            $statusClass = $status === 'alpa' ? 'bad' : (($lateMinutes !== null || $status === 'telat') ? 'bad' : 'ok');

            $absenSummary = [
                'masuk' => $absenHariIni->waktu_masuk->format('H:i'),
                'shiftNo' => $shiftNo > 0 ? $shiftNo : null,
                'shiftCode' => $shiftNo > 0 ? $setting->shiftCodeFor($shiftNo, $employmentType) : null,
                'statusLabel' => $statusLabel,
                'statusClass' => $statusClass,
                'deltaLabel' => $deltaLabel,
                'deltaClass' => $deltaClass,
            ];
        }

        $jabatan = trim((string) ($karyawan->jabatan ?? ''));
        $announcements = collect();
        $readIds = [];
        $latestUnread = null;
        $unreadAnnouncements = collect();
        if (Schema::hasTable('announcements')) {
            $announcementQuery = Announcement::query()
                ->where('is_active', true)
                ->where(function ($q) use ($jabatan): void {
                    $q->whereNull('target_role');
                    if ($jabatan !== '') {
                        $q->orWhere('target_role', $jabatan);
                    }
                })
                ->where(function ($q): void {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', now());
                })
                ->orderByDesc('updated_at')
                ->orderByDesc('published_at')
                ->orderByDesc('id');

            $announcementNow = now();
            $announcements = $announcementQuery->get()
                ->map(function (Announcement $announcement) use ($announcementNow, $jabatan) {
                    $promoInfo = $announcement->resolvePromoStatus($announcementNow);
                    $announcement->promo_end_at = $promoInfo['end_at'];
                    $announcement->promo_start_at = $promoInfo['start_at'];
                    $announcement->promo_status = $promoInfo['status'] === 'Akan Mulai' ? 'Terjadwal' : $promoInfo['status'];

                    $sortTimestamps = collect([
                        $announcement->updated_at,
                        $announcement->published_at,
                        $announcement->created_at,
                        $announcement->promo_start_at,
                    ])
                        ->filter(fn ($value) => $value instanceof Carbon)
                        ->map(fn (Carbon $value) => $value->getTimestamp());

                    $announcement->staff_sort_timestamp = $sortTimestamps->max() ?? 0;

                    return $this->decorateAnnouncementCard($announcement, $jabatan);
                })
                ->filter(function (Announcement $announcement) use ($announcementNow): bool {
                    return ! ($announcement->promo_end_at instanceof Carbon)
                        || $announcementNow->lessThanOrEqualTo($announcement->promo_end_at);
                })
                ->sortByDesc(function (Announcement $announcement): int {
                    return (int) ($announcement->staff_sort_timestamp ?? 0);
                })
                ->values()
                ->take(5);
            $announcementIds = $announcements->pluck('id')->all();
            if (! empty($announcementIds) && Schema::hasTable('announcement_reads')) {
                $readIds = AnnouncementRead::query()
                    ->where('karyawan_id', (int) $karyawan->id_karyawan)
                    ->whereIn('announcement_id', $announcementIds)
                    ->pluck('announcement_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }
            $latestAnnouncement = $announcements->first();
            $latestUnread = $latestAnnouncement && ! in_array((int) $latestAnnouncement->id, $readIds, true)
                ? $latestAnnouncement
                : null;
            $unreadAnnouncements = $announcements->filter(function ($a) use ($readIds): bool {
                return ! in_array((int) $a->id, (array) $readIds, true);
            })->values();
        }

        $pendingSwapForStaff = 0;
        $pendingSwapWaitingAdmin = 0;
        if (Schema::hasTable('jadwal_tukar_requests')) {
            $pendingSwapForStaff = (int) JadwalTukarRequest::query()
                ->where('to_karyawan_id', (int) $karyawan->id_karyawan)
                ->where('status', 'pending')
                ->where('staff_status', 'pending')
                ->count();
            $pendingSwapWaitingAdmin = (int) JadwalTukarRequest::query()
                ->where('from_karyawan_id', (int) $karyawan->id_karyawan)
                ->where('status', 'pending')
                ->where('staff_status', 'approved')
                ->count();
        }

        $pendingLeaveCount = 0;
        if (Schema::hasTable('leave_requests')) {
            $pendingLeaveCount = (int) LeaveRequest::query()
                ->where('id_karyawan', (int) $karyawan->id_karyawan)
                ->where('status', 'pending')
                ->count();
        }

        $staffNotifications = collect();
        $unreadNotificationCount = 0;
        if (Schema::hasTable('staff_notifications')) {
            $staffNotifications = StaffNotification::query()
                ->where('id_karyawan', (int) $karyawan->id_karyawan)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(40)
                ->get();

            $unreadNotificationCount = (int) $staffNotifications->whereNull('read_at')->count();
        }

        $messageAlertItems = $this->buildDashboardMessageAlertItems((int) $karyawan->id_karyawan);

        return view('staff.home', [
            'setting' => $setting,
            'karyawan' => $karyawan,
            'today' => $today,
            'absenHariIni' => $absenHariIni,
            'absenSummary' => $absenSummary,
            'jadwalHariIni' => $jadwalHariIni,
            'jadwal7Hari' => $jadwal7Hari,
            'jadwal7View' => $jadwal7View,
            'riwayat' => $riwayat,
            'riwayatView' => $riwayatView,
            'shiftStarts' => $shiftStarts,
            'announcements' => $announcements,
            'announcementReadIds' => $readIds,
            'latestUnreadAnnouncement' => $latestUnread,
            'unreadAnnouncements' => $unreadAnnouncements,
            'pendingSwapForStaff' => $pendingSwapForStaff,
            'pendingSwapWaitingAdmin' => $pendingSwapWaitingAdmin,
            'pendingLeaveCount' => $pendingLeaveCount,
            'staffNotifications' => $staffNotifications,
            'unreadNotificationCount' => $unreadNotificationCount,
            'messageAlertItems' => $messageAlertItems,
        ]);
    }

    public function profile(Request $request): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        return view('staff.profile', $this->profilePagePayload($karyawan));
    }

    public function notificationsIndex(Request $request): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $setting = StrukSetting::current();

        $activeFilter = in_array((string) $request->query('filter'), ['all', 'unread'], true)
            ? (string) $request->query('filter')
            : 'all';

        $baseQuery = StaffNotification::query()
            ->where('id_karyawan', (int) ($karyawan->id_karyawan ?? 0));

        $totalNotificationCount = (clone $baseQuery)->count();
        $unreadNotificationCount = (clone $baseQuery)
            ->whereNull('read_at')
            ->count();

        $latestNotificationAt = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('created_at');

        $notifications = (clone $baseQuery)
            ->when($activeFilter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->simplePaginate(20)
            ->withQueryString();

        $categoryCounts = StaffNotification::query()
            ->selectRaw('category, COUNT(*) as total')
            ->where('id_karyawan', (int) ($karyawan->id_karyawan ?? 0))
            ->groupBy('category')
            ->pluck('total', 'category');

        return view('staff.notifications.index', [
            'setting' => $setting,
            'karyawan' => $karyawan,
            'notifications' => $notifications,
            'totalNotificationCount' => (int) $totalNotificationCount,
            'unreadNotificationCount' => (int) $unreadNotificationCount,
            'latestNotificationAt' => $latestNotificationAt ? Carbon::parse($latestNotificationAt) : null,
            'activeNotificationFilter' => $activeFilter,
            'categoryCounts' => $categoryCounts,
        ]);
    }

    public function editProfile(Request $request): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');

        return view('staff.profile-edit', $this->profilePagePayload($karyawan));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');

        $data = $request->validate([
            'no_telepon' => ['required', 'string', 'max:30'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'foto_profil' => ['nullable', 'image', 'max:3072'],
        ]);

        $normalizedPhone = $this->normalizePhone((string) $data['no_telepon']);
        if ($normalizedPhone === '') {
            return back()
                ->withErrors(['no_telepon' => 'Nomor telepon wajib diisi dengan angka yang valid.'])
                ->withInput();
        }

        $phoneUsed = Karyawan::query()
            ->where('id_karyawan', '<>', (int) $karyawan->id_karyawan)
            ->where('no_telepon', $normalizedPhone)
            ->exists();

        if ($phoneUsed) {
            return back()
                ->withErrors(['no_telepon' => 'Nomor telepon ini sudah dipakai staf lain.'])
                ->withInput();
        }

        $oldPhone = trim((string) ($karyawan->no_telepon ?? ''));
        $oldAlamat = trim((string) ($karyawan->alamat ?? ''));
        $oldPhoto = trim((string) ($karyawan->foto_profil_path ?? ''));
        $alamat = trim((string) ($data['alamat'] ?? ''));
        $newPhotoPath = $oldPhoto;

        if ($request->hasFile('foto_profil')) {
            $newPhotoPath = $request->file('foto_profil')->storePublicly('staff-profile', 'public');
        }

        $karyawan->no_telepon = $normalizedPhone;
        $karyawan->alamat = $alamat !== '' ? $alamat : null;
        $karyawan->foto_profil_path = $newPhotoPath !== '' ? $newPhotoPath : null;
        $karyawan->save();

        if ($request->hasFile('foto_profil') && $oldPhoto !== '' && $oldPhoto !== $newPhotoPath) {
            Storage::disk('public')->delete($oldPhoto);
        }

        $changedFields = [];
        if ($oldPhone !== $normalizedPhone) {
            $changedFields[] = 'nomor telepon';
        }
        if ($oldAlamat !== $alamat) {
            $changedFields[] = 'alamat';
        }
        if ($request->hasFile('foto_profil')) {
            $changedFields[] = 'foto profil';
        }

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.profile.update',
            'Perbarui Profil',
            $changedFields !== []
                ? 'Memperbarui ' . implode(', ', $changedFields) . '.'
                : 'Menyimpan ulang data profil staf.',
            [
                'changed_fields' => $changedFields,
            ],
            'karyawan',
            (int) $karyawan->id_karyawan,
            trim((string) ($karyawan->nama_karyawan ?? '')) ?: 'Profil staf',
        );

        return redirect()
            ->route('staff.profile')
            ->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePagePayload(?Karyawan $karyawan): array
    {
        $today = now()->toDateString();
        $setting = StrukSetting::current();

        $jadwalHariIni = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan?->id_karyawan)
            ->whereDate('tanggal', $today)
            ->orderBy('shift_ke')
            ->first();

        $absenHariIni = Absensi::query()
            ->where('id_karyawan', (int) $karyawan?->id_karyawan)
            ->whereDate('tanggal', $today)
            ->first();

        return [
            'setting' => $setting,
            'karyawan' => $karyawan,
            'today' => $today,
            'jadwalHariIni' => $jadwalHariIni,
            'absenHariIni' => $absenHariIni,
        ];
    }

    public function history(Request $request): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');

        $swapRequests = collect();
        $staffMap = [];
        if (Schema::hasTable('jadwal_tukar_requests')) {
            $swapRequests = JadwalTukarRequest::query()
                ->where(function ($q) use ($karyawan): void {
                    $q->where('from_karyawan_id', (int) $karyawan->id_karyawan)
                        ->orWhere('to_karyawan_id', (int) $karyawan->id_karyawan);
                })
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            $staffIds = $swapRequests
                ->pluck('from_karyawan_id')
                ->merge($swapRequests->pluck('to_karyawan_id'))
                ->filter()
                ->unique()
                ->values();

            if ($staffIds->isNotEmpty() && Schema::hasTable('karyawan')) {
                $rows = Karyawan::query()
                    ->whereIn('id_karyawan', $staffIds->all())
                    ->get(['id_karyawan', 'nama_karyawan', 'jabatan']);
                foreach ($rows as $row) {
                    $name = trim((string) ($row->nama_karyawan ?? ''));
                    if ($name === '') {
                        $name = 'Karyawan #' . (int) $row->id_karyawan;
                    }
                    $role = trim((string) ($row->jabatan ?? ''));
                    $label = $role !== '' ? ($name . ' (' . $role . ')') : $name;
                    $staffMap[(int) $row->id_karyawan] = $label;
                }
            }
        }

        $leaveRows = collect();
        if (Schema::hasTable('leave_requests')) {
            $leaveRows = LeaveRequest::query()
                ->where('id_karyawan', (int) $karyawan->id_karyawan)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        return view('staff.history', [
            'karyawan' => $karyawan,
            'swapRequests' => $swapRequests,
            'leaveRows' => $leaveRows,
            'staffMap' => $staffMap,
        ]);
    }

    public function readAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');

        if (! Schema::hasTable('announcement_reads')) {
            return back();
        }

        AnnouncementRead::query()->firstOrCreate(
            [
                'announcement_id' => (int) $announcement->id,
                'karyawan_id' => (int) $karyawan->id_karyawan,
            ],
            [
                'read_at' => now(),
            ]
        );

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.announcement.read',
            'Baca Pengumuman',
            'Menandai pengumuman "' . trim((string) ($announcement->title ?? 'Pengumuman')) . '" sebagai sudah dibaca.',
            [],
            'announcement',
            (int) $announcement->id,
            trim((string) ($announcement->title ?? 'Pengumuman')),
        );

        return back()->with('success', 'Pengumuman ditandai sudah dibaca.');
    }

    public function openNotification(Request $request, StaffNotification $notification): RedirectResponse
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');

        if ((int) $notification->id_karyawan !== (int) ($karyawan->id_karyawan ?? 0)) {
            abort(404);
        }

        app(StaffNotificationService::class)->markAsRead($notification);

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.notification.open',
            'Buka Notifikasi',
            'Membuka notifikasi "' . trim((string) ($notification->title ?? 'Notifikasi')) . '".',
            [
                'notification_id' => (int) $notification->id,
                'category' => (string) $notification->category,
            ],
            'staff_notification',
            (int) $notification->id,
            trim((string) ($notification->title ?? 'Notifikasi')),
        );

        $target = trim((string) ($notification->action_url ?? ''));

        return redirect()->to($target !== '' ? $target : route('staff.home'));
    }

    private function buildDashboardMessageAlertItems(int $karyawanId): Collection
    {
        if ($karyawanId <= 0) {
            return collect();
        }

        if (! Schema::hasTable('staff_messages') || ! Schema::hasTable('staff_message_reads')) {
            return collect();
        }

        $threads = collect();
        $threads->push([
            'type' => 'admin_chat',
            'id' => $karyawanId,
            'title' => 'Chat Admin',
        ]);

        if (Schema::hasTable('leave_requests')) {
            LeaveRequest::query()
                ->where('id_karyawan', $karyawanId)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(['id', 'jenis', 'tanggal_awal', 'tanggal_akhir'])
                ->each(function (LeaveRequest $leave) use ($threads): void {
                    $jenis = strtolower((string) ($leave->jenis ?? ''));
                    $jenisLabel = $jenis === 'sakit' ? 'Sakit' : 'Izin';

                    $threads->push([
                        'type' => 'leave',
                        'id' => (int) $leave->id,
                        'title' => 'Pengajuan ' . $jenisLabel,
                    ]);
                });
        }

        if (class_exists(JadwalTukarRequest::class) && Schema::hasTable('jadwal_tukar_requests')) {
            JadwalTukarRequest::query()
                ->where(function ($query) use ($karyawanId): void {
                    $query->where('from_karyawan_id', $karyawanId)
                        ->orWhere('to_karyawan_id', $karyawanId);
                })
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(['id'])
                ->each(function ($swap) use ($threads): void {
                    $threads->push([
                        'type' => 'swap',
                        'id' => (int) $swap->id,
                        'title' => 'Tukar Shift',
                    ]);
                });
        }

        $absensiThreadIds = StaffMessage::query()
            ->where('thread_type', 'absensi')
            ->orderByDesc('created_at')
            ->pluck('thread_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($absensiThreadIds->isNotEmpty()) {
            Absensi::query()
                ->where('id_karyawan', $karyawanId)
                ->whereIn('id_absensi', $absensiThreadIds)
                ->orderByDesc('tanggal')
                ->get(['id_absensi', 'tanggal'])
                ->each(function (Absensi $absensi) use ($threads): void {
                    $threads->push([
                        'type' => 'absensi',
                        'id' => (int) $absensi->id_absensi,
                        'title' => 'Absensi ' . ($absensi->tanggal?->translatedFormat('d M Y') ?? '-'),
                    ]);
                });
        }

        $threads = $threads->unique(fn (array $thread) => $thread['type'] . ':' . (int) $thread['id'])->values();
        if ($threads->isEmpty()) {
            return collect();
        }

        $lastMessages = $this->resolveDashboardLastMessages($threads);
        $unreadCounts = $this->resolveDashboardUnreadCounts($threads, $karyawanId);

        return $threads
            ->filter(function (array $thread) use ($unreadCounts, $lastMessages): bool {
                $key = $thread['type'] . ':' . (int) $thread['id'];
                return (int) ($unreadCounts[$key] ?? 0) > 0 && isset($lastMessages[$key]);
            })
            ->map(function (array $thread) use ($lastMessages, $unreadCounts): array {
                $key = $thread['type'] . ':' . (int) $thread['id'];
                /** @var \App\Models\StaffMessage|null $lastMessage */
                $lastMessage = $lastMessages[$key] ?? null;
                $unreadCount = (int) ($unreadCounts[$key] ?? 0);
                $preview = $lastMessage
                    ? \Illuminate\Support\Str::limit(trim((string) $lastMessage->message), 76)
                    : 'Ada balasan baru di percakapan ini.';
                $detail = $preview;
                if ($lastMessage?->created_at) {
                    $detail .= ' • ' . $lastMessage->created_at->translatedFormat('d M Y • H:i');
                }

                return [
                    'title' => (string) ($thread['title'] ?? 'Pesan Baru'),
                    'detail' => $detail,
                    'href' => route('staff.messages.show', ['type' => $thread['type'], 'id' => $thread['id']]),
                    'badge' => $unreadCount > 1 ? $unreadCount . ' baru' : 'Pesan',
                    'tone' => 'messages',
                    'isUnread' => true,
                    'created_at' => $lastMessage?->created_at,
                ];
            })
            ->sortByDesc(fn (array $item) => $item['created_at']?->timestamp ?? 0)
            ->values();
    }

    private function resolveDashboardLastMessages(Collection $threads): array
    {
        $groupedIds = $threads->groupBy('type')->map(fn (Collection $items) => $items->pluck('id')->all())->all();
        $lastMessages = [];

        foreach ($groupedIds as $type => $ids) {
            if (empty($ids)) {
                continue;
            }

            $messages = StaffMessage::query()
                ->where('thread_type', $type)
                ->whereIn('thread_id', $ids)
                ->orderByDesc('created_at')
                ->get();

            foreach ($messages as $message) {
                $key = $type . ':' . (int) $message->thread_id;
                if (! isset($lastMessages[$key])) {
                    $lastMessages[$key] = $message;
                }
            }
        }

        return $lastMessages;
    }

    private function resolveDashboardUnreadCounts(Collection $threads, int $karyawanId): array
    {
        if ($threads->isEmpty()) {
            return [];
        }

        $reads = StaffMessageRead::query()
            ->where('reader_role', 'staff')
            ->where('reader_karyawan_id', $karyawanId)
            ->get()
            ->keyBy(fn ($row) => $row->thread_type . ':' . (int) $row->thread_id);

        $groupedIds = $threads->groupBy('type')->map(fn (Collection $items) => $items->pluck('id')->all())->all();
        $unreadCounts = [];

        foreach ($groupedIds as $type => $ids) {
            if (empty($ids)) {
                continue;
            }

            $messages = StaffMessage::query()
                ->where('thread_type', $type)
                ->whereIn('thread_id', $ids)
                ->orderBy('created_at')
                ->get(['thread_id', 'created_at', 'sender_role', 'sender_karyawan_id']);

            foreach ($messages as $message) {
                if ((string) $message->sender_role === 'staff' && (int) $message->sender_karyawan_id === $karyawanId) {
                    continue;
                }

                $key = $type . ':' . (int) $message->thread_id;
                $lastRead = $reads[$key]->last_read_at ?? null;

                if ($lastRead instanceof Carbon && $message->created_at <= $lastRead) {
                    continue;
                }

                $unreadCounts[$key] = ($unreadCounts[$key] ?? 0) + 1;
            }
        }

        return $unreadCounts;
    }

    public function jadwal(Request $request): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $employmentType = method_exists($karyawan, 'employmentTypeValue')
            ? $karyawan->employmentTypeValue()
            : Karyawan::normalizeEmploymentType($karyawan?->employment_type ?? null);

        $bulan = (string) $request->get('bulan', now()->format('Y-m'));
        $mode = (string) $request->get('mode', 'calendar');
        if (! in_array($mode, ['calendar', 'list'], true)) {
            $mode = 'calendar';
        }

        $setting = StrukSetting::current();
        try {
            $start = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();

        $rows = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->orderBy('tanggal')
            ->get();

        $byTanggal = $rows
            ->groupBy(fn ($r) => $r->tanggal?->format('Y-m-d') ?? '-')
            ->map(function ($items) {
                return $items->pluck('shift_ke')
                    ->map(fn ($s) => (int) $s)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            })
            ->all();

        $calendarDetails = [];
        $monthRows = JadwalKaryawan::query()
            ->leftJoin('karyawan', 'jadwal_karyawan.id_karyawan', '=', 'karyawan.id_karyawan')
            ->whereDate('jadwal_karyawan.tanggal', '>=', $start->toDateString())
            ->whereDate('jadwal_karyawan.tanggal', '<=', $end->toDateString())
            ->orderBy('jadwal_karyawan.tanggal')
            ->get([
                'jadwal_karyawan.tanggal',
                'jadwal_karyawan.shift_ke',
                'karyawan.nama_karyawan',
            ]);

        foreach ($monthRows as $row) {
            $tgl = $row->tanggal?->format('Y-m-d') ?? null;
            if (! $tgl) {
                continue;
            }
            $shift = (int) ($row->shift_ke ?? 0);
            $name = (string) ($row->nama_karyawan ?? '');
            if ($shift < 1 || $shift > 3 || $name === '') {
                continue;
            }
            $key = 'shift' . $shift;
            $calendarDetails[$tgl][$key] ??= [];
            $calendarDetails[$tgl][$key][] = $name;
        }

        $absenDays = Absensi::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->where(function ($q): void {
                $q->whereNotNull('waktu_masuk')->orWhere('status', 'alpa');
            })
            ->pluck('tanggal')
            ->map(fn ($d) => Carbon::parse((string) $d)->format('Y-m-d'))
            ->all();
        $absenSet = array_fill_keys($absenDays, true);

        $absenRows = Absensi::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->where(function ($q): void {
                $q->whereNotNull('waktu_masuk')->orWhere('status', 'alpa');
            })
            ->orderBy('tanggal')
            ->get(['tanggal', 'waktu_masuk', 'status', 'shift_no']);
        $absenByTanggal = $absenRows->keyBy(fn ($r) => $r->tanggal?->format('Y-m-d') ?? '-');
        $absenByTanggalShift = [];
        foreach ($absenRows as $row) {
            $tgl = $row->tanggal?->format('Y-m-d') ?? null;
            if (! $tgl) {
                continue;
            }
            $shift = (int) ($row->shift_no ?? 0);
            if ($shift > 0) {
                $absenByTanggalShift[$tgl][$shift] = $row;
            }
        }

        $shiftStarts = [
            1 => $setting->shiftStartTimeFor(1, $employmentType),
            2 => $setting->shiftStartTimeFor(2, $employmentType),
            3 => $setting->shiftStartTimeFor(3, $employmentType),
        ];
        $tolerance = (int) ($setting->absensi_late_tolerance_minutes ?? 10);

        $days = [];
        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $bulanLabel = $start->copy()->locale('id')->translatedFormat('F Y');

        return view('staff.jadwal', [
            'setting' => $setting,
            'karyawan' => $karyawan,
            'bulan' => $start->format('Y-m'),
            'bulanLabel' => $bulanLabel,
            'mode' => $mode,
            'days' => $days,
            'byTanggal' => $byTanggal,
            'calendarDetails' => $calendarDetails,
            'absenSet' => $absenSet,
            'absenByTanggal' => $absenByTanggal,
            'absenByTanggalShift' => $absenByTanggalShift,
            'shiftStarts' => $shiftStarts,
            'lateToleranceMinutes' => $tolerance,
        ]);
    }
}



