@extends('layouts.staff')

@section('title', 'Absen Karyawan')

@section('content')
<div class="container app-shell">
    @php
        $nama = (string) ($staffKaryawan->nama_karyawan ?? session('staff_karyawan_name', 'Karyawan'));
        $jabatan = trim((string) ($staffKaryawan->jabatan ?? 'Staff')) ?: 'Staff';
        $tipeKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
        $durasiKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentDurationLabel')) ? $staffKaryawan->employmentDurationLabel() : '8 jam';
        $windowClass = (string) ($windowInfo['class'] ?? 'warn');
        $alreadyMasuk = !empty($alreadyMasuk);
        $alreadyPulang = !empty($alreadyPulang);
        $blockTodayForm = !empty($blockTodayForm);
        $pendingCorrection = $pendingCorrection ?? null;
        $statusClass = (string) ($absenInfo['statusClass'] ?? ($alreadyMasuk ? 'ok' : 'warn'));
        $statusLabel = $alreadyMasuk
            ? ((string) ($absenInfo['statusLabel'] ?? 'Sudah Absen'))
            : ((string) ($windowInfo['label'] ?? 'Menunggu Waktu Absen'));
        $shiftCode = (string) ($windowInfo['shiftCode'] ?? '');
        $shiftLabel = isset($windowInfo['shiftNo']) && (int) ($windowInfo['shiftNo'] ?? 0) > 0
            ? (($shiftCode !== '' ? $shiftCode : ('Shift ' . (int) $windowInfo['shiftNo'])))
            : 'Belum ada shift hari ini';
        $windowRange = isset($windowInfo['openAt'], $windowInfo['closeAt'])
            ? ($windowInfo['openAt'] . ' - ' . $windowInfo['closeAt'])
            : 'Menyesuaikan jadwal hari ini';
        $requirementLabel = collect([
            !empty($requireSelfie) ? 'Selfie kamera' : null,
            !empty($requireGeofence) ? 'Lokasi aktif' : null,
        ])->filter()->implode('  -  ');
        $initials = collect(array_values(array_filter(explode(' ', $nama))))
            ->take(2)
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->implode('');
        $currentMoment = \Illuminate\Support\Carbon::now()->locale('id');
        $greetingLabel = match (true) {
            $currentMoment->hour < 11 => 'Selamat Pagi',
            $currentMoment->hour < 15 => 'Selamat Siang',
            $currentMoment->hour < 18 => 'Selamat Sore',
            default => 'Selamat Malam',
        };
        $calendarDayLabel = mb_strtoupper($currentMoment->translatedFormat('l'));
        $calendarDateLabel = $currentMoment->translatedFormat('d M');
        $heroStatusTitle = $blockTodayForm
            ? 'Perlu Koreksi'
            : ($alreadyMasuk
                ? ($alreadyPulang ? 'Absensi Lengkap' : 'Sedang Bekerja')
                : 'Belum Absen');
        $heroStatusText = $blockTodayForm
            ? 'Selesaikan koreksi jam pulang sebelumnya supaya absensi hari ini tetap aman.'
            : ($alreadyMasuk
                ? ($alreadyPulang
                    ? 'Absen masuk dan pulang sudah tercatat. Tinggal menunggu verifikasi admin.'
                    : 'Absen masuk sudah tercatat. Lanjutkan absen pulang saat shift selesai.')
                : 'Silakan clock-in untuk memulai shift hari ini.');
        $verificationLabel = (string) ($absenInfo['verificationLabel'] ?? 'Pending');
        $masukLabel = (string) ($absenInfo['masuk'] ?? '-');
        $pulangLabel = (string) ($absenInfo['pulang'] ?? '-');
        $durasiLabel = (string) ($absenInfo['durasiLabel'] ?? '0j 0m');
        $canCheckIn = !$blockTodayForm && !$alreadyMasuk;
        $canCheckOut = !$blockTodayForm && $alreadyMasuk && !$alreadyPulang;
        $checkInHref = route('absen.masuk.page');
        $checkOutHref = route('absen.pulang.page');
    @endphp
    <div class="staff-attendance-mobile-app">
        <section class="staff-attendance-app-topbar">
            <div class="staff-attendance-app-title">
                <span class="staff-attendance-app-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                <strong>Absen</strong>
            </div>
            <div class="staff-attendance-avatar" aria-hidden="true">{{ $initials !== '' ? $initials : 'ST' }}</div>
        </section>

        <section class="staff-attendance-greeting">
            <div class="staff-attendance-greeting-copy">
                <span>{{ mb_strtoupper($greetingLabel) }}</span>
                <h1>Halo, {{ $nama }}</h1>
                <p>{{ $jabatan }} ({{ $tipeKerja }})</p>
            </div>
            <div class="staff-attendance-date-card">
                <span>{{ $calendarDayLabel }}</span>
                <strong>{{ $calendarDateLabel }}</strong>
            </div>
        </section>

        <article class="staff-attendance-status-hero {{ $blockTodayForm ? 'is-danger' : ($alreadyMasuk ? ($alreadyPulang ? 'is-ok' : 'is-live') : 'is-idle') }}">
            <span class="staff-attendance-status-chip">Status Kehadiran</span>
            <strong>{{ $heroStatusTitle }}</strong>
            <p>{{ $heroStatusText }}</p>
        </article>

        <article class="staff-attendance-shift-card">
            <div class="staff-attendance-section-label">
                <span>Jadwal Shift</span>
                <span class="staff-attendance-section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M8 3.8v3M16 3.8v3M4 9.5h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
            </div>
            <div class="staff-attendance-shift-grid">
                <article>
                    <span>Shift</span>
                    <strong>{{ $shiftCode !== '' ? $shiftCode : 'Hari ini' }}</strong>
                    <small>{{ $windowRange }}</small>
                </article>
                <article>
                    <span>Target</span>
                    <strong>{{ $durasiKerja }}</strong>
                    <small>{{ $tipeKerja }}</small>
                </article>
            </div>
        </article>

        <div class="staff-attendance-action-note">
            @if($blockTodayForm)
                Selesaikan koreksi absensi sebelumnya dulu, baru lanjut absensi hari ini.
            @elseif($canCheckIn)
                Tombol aktif sekarang: <strong>Absen Masuk</strong>
            @elseif($canCheckOut)
                Tombol aktif sekarang: <strong>Absen Pulang</strong>
            @else
                Absensi hari ini sudah lengkap.
            @endif
        </div>

        <div class="staff-attendance-action-grid">
            <a class="staff-attendance-action-card {{ $canCheckIn || $blockTodayForm ? 'active' : 'inactive' }}" href="{{ $checkInHref }}">
                <span class="staff-attendance-action-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                <strong>{{ $blockTodayForm ? 'Ajukan Koreksi' : 'Absen Masuk' }}</strong>
                <small>
                    {{ $blockTodayForm ? 'Perbaiki absen sebelumnya' : ($canCheckIn ? 'Tap untuk clock-in sekarang' : 'Sudah tercatat / belum aktif') }}
                </small>
            </a>
            <a class="staff-attendance-action-card {{ $canCheckOut ? 'active' : 'inactive' }}" href="{{ $checkOutHref }}">
                <span class="staff-attendance-action-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M8 12h8M13 7l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <strong>Absen Pulang</strong>
                <small>{{ $canCheckOut ? 'Tap untuk clock-out sekarang' : 'Aktif setelah absen masuk' }}</small>
            </a>
        </div>

        <section class="staff-attendance-daily-section">
            <div class="staff-attendance-section-label no-icon">
                <span>Ringkasan Hari Ini</span>
            </div>
            <div class="staff-attendance-daily-grid">
                <article>
                    <span>Masuk</span>
                    <strong>{{ $masukLabel }}</strong>
                </article>
                <article>
                    <span>Keluar</span>
                    <strong>{{ $pulangLabel }}</strong>
                </article>
                <article>
                    <span>Durasi</span>
                    <strong>{{ $durasiLabel }}</strong>
                </article>
                <article>
                    <span>Status</span>
                    <strong>{{ $verificationLabel }}</strong>
                </article>
            </div>
        </section>
    </div>
    <div class="staff-mobile-page-screen">
        <section class="staff-mobile-page-stage">
            @include('staff.partials.mobile-page-header', [
                'pageTitle' => 'Absen',
                'pageMark' => 'AB',
                'staffName' => $nama,
                'greetingTitle' => 'Halo, ' . $nama,
                'greetingSubtitle' => 'Catat jam masuk dan pulang kerja langsung dari portal staf.',
                'employmentLabel' => $tipeKerja,
                'employmentMeta' => $jabatan . ' • ' . $durasiKerja,
            ])

            <article class="staff-mobile-page-summary-card">
                <div class="staff-mobile-page-summary-topline">
                    <div class="staff-mobile-page-summary-period">
                        <span class="staff-mobile-page-summary-label">Status Hari Ini</span>
                        <strong>{{ $statusLabel }}</strong>
                    </div>
                    <span class="staff-mobile-page-pill">{{ $shiftCode !== '' ? $shiftCode : 'SHIFT' }}</span>
                </div>

                <div class="staff-mobile-page-summary-stats">
                    <article>
                        <span>Shift</span>
                        <strong>{{ $shiftLabel }}</strong>
                    </article>
                    <article>
                        <span>Window</span>
                        <strong>{{ $windowRange }}</strong>
                    </article>
                    <article>
                        <span>Verifikasi</span>
                        <strong>{{ (string) ($absenInfo['verificationLabel'] ?? 'Menunggu absensi hari ini') }}</strong>
                    </article>
                    <article>
                        <span>Tanggal</span>
                        <strong>{{ $today }}</strong>
                    </article>
                </div>

                <div class="staff-mobile-page-summary-actions">
                    <a class="btn-neutral" href="{{ route('staff.home') }}">Kembali</a>
                </div>
            </article>
        </section>

        <div class="panel attendance-panel" id="attendance-detail-panel">
        @if(session('success'))
            <div class="alert ok">{{ session('success') }}</div>
        @endif

        @if($errors->has('absensi'))
            <div class="alert ok">{{ $errors->first('absensi') }}</div>
        @endif

        @if($alreadyMasuk && !$alreadyPulang)
            <div class="alert ok">Absen masuk hari ini sudah tercatat. Kalau shift atau jam kerjamu sudah selesai, lanjutkan dengan absen pulang.</div>
        @elseif($alreadyMasuk && $alreadyPulang)
            <div class="alert ok">Absen masuk dan pulang hari ini sudah lengkap. Data ini tinggal menunggu verifikasi admin.</div>
        @endif
        @if($blockTodayForm && $pendingCorrection)
            <div class="alert err">Masih ada absensi {{ $pendingCorrection['tanggalLabel'] }} yang belum selesai. Selesaikan koreksi jam pulang dulu sebelum absen hari ini.</div>
        @endif

        @php
            $otherErrors = [];
            foreach ($errors->all() as $message) {
                if ($message === $errors->first('absensi')) {
                    continue;
                }
                $otherErrors[] = $message;
            }
        @endphp
        @if(!empty($otherErrors))
            <div class="alert err">
                @foreach($otherErrors as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="attendance-section-head">
            <div>
                <h2 class="attendance-section-title">
                    @if($blockTodayForm)
                        Koreksi Jam Pulang
                    @elseif(!$alreadyMasuk)
                        Mulai Kerja
                    @elseif(!$alreadyPulang)
                        Selesai Kerja
                    @else
                        Detail Hari Ini
                    @endif
                </h2>
                <div class="attendance-section-sub">
                    @if($blockTodayForm)
                        Isi jam pulang yang benar supaya admin bisa meninjau absensimu.
                    @elseif(!$alreadyMasuk)
                        Lengkapi data yang dibutuhkan sebelum clock-in.
                    @elseif(!$alreadyPulang)
                        Clock-in sudah tercatat. Lanjutkan clock-out saat shift selesai.
                    @else
                        Absensi hari ini sudah lengkap dan tinggal menunggu verifikasi admin.
                    @endif
                </div>
            </div>
            <div class="attendance-badges">
                <span class="pill {{ $blockTodayForm ? ($pendingCorrection['stateClass'] ?? 'warn') : ($alreadyAbsen ? 'ok' : $statusClass) }}">{{ $blockTodayForm ? ($pendingCorrection['stateLabel'] ?? 'Koreksi') : $statusLabel }}</span>
                @if(isset($windowInfo['shiftNo']) && (int) ($windowInfo['shiftNo'] ?? 0) > 0)
                    <span class="pill gray">{{ $shiftCode !== '' ? $shiftCode : ('Shift ' . (int) $windowInfo['shiftNo']) }}</span>
                @endif
            </div>
        </div>

        <div class="attendance-form-grid">
            @if($blockTodayForm && $pendingCorrection)
                <div class="attendance-card">
                    <div class="attendance-card-title">Tanggal</div>
                    <div class="attendance-karyawan-name">{{ $pendingCorrection['tanggalLabel'] }}</div>
                </div>
                <div class="attendance-card">
                    <div class="attendance-card-title">Shift</div>
                    <div class="attendance-karyawan-name">{{ $pendingCorrection['shiftLabel'] }}</div>
                </div>
                <div class="attendance-card">
                    <div class="attendance-card-title">Jam Masuk</div>
                    <div class="attendance-karyawan-name">{{ $pendingCorrection['masuk'] }}</div>
                </div>
                <div class="attendance-card full">
                    <div class="attendance-card-head">
                        <div>
                            <div class="attendance-card-title">Lanjutkan dari Halaman Fokus</div>
                            <div class="attendance-card-sub">Supaya lebih rapi, pengajuan koreksi sekarang dibuka di halaman khusus absen.</div>
                        </div>
                        <span class="pill gray">{{ $pendingCorrection['stateLabel'] }}</span>
                    </div>
                    <div class="attendance-card-sub">{{ $pendingCorrection['note'] }}</div>
                    @if(!empty($pendingCorrection['requestedPulangLabel']))
                        <div class="attendance-card-sub">Usulan terakhir: {{ $pendingCorrection['requestedPulangLabel'] }}</div>
                    @endif
                    @if(!empty($pendingCorrection['reviewNote']))
                        <div class="attendance-card-sub">Catatan admin: {{ $pendingCorrection['reviewNote'] }}</div>
                    @endif
                    <div class="attendance-card-sub">Batas ajukan koreksi mandiri: {{ $pendingCorrection['deadlineLabel'] }}</div>
                </div>
            @elseif(!$alreadyMasuk)
                <div class="attendance-card full">
                    <div class="attendance-card-head">
                        <div>
                            <div class="attendance-card-title">Syarat Absen</div>
                            <div class="attendance-card-sub">
                                @if($requirementLabel !== '')
                                    Semua kebutuhan seperti {{ $requirementLabel }} akan dibuka di halaman khusus supaya lebih clean.
                                @else
                                    Form absen masuk dipindahkan ke halaman khusus supaya lebih fokus dan rapi.
                                @endif
                            </div>
                        </div>
                        <span class="pill gray">Fokus</span>
                    </div>
                </div>
            @elseif(!$alreadyPulang)
                <div class="attendance-card full">
                    <div class="attendance-card-head">
                        <div>
                            <div class="attendance-card-title">Absen Pulang di Halaman Fokus</div>
                            <div class="attendance-card-sub">Clock-out sekarang dibuka di halaman khusus supaya prosesnya lebih jelas dan nggak bercampur dengan ringkasan.</div>
                        </div>
                        <span class="pill gray">Praktis</span>
                    </div>
                    <div class="attendance-card-sub">Jam masukmu sudah tercatat pukul {{ (string) ($absenInfo['masuk'] ?? '-') }}.</div>
                </div>
            @else
                <div class="attendance-card">
                    <div class="attendance-card-title">Jam Masuk</div>
                    <div class="attendance-karyawan-name">{{ (string) ($absenInfo['masuk'] ?? '-') }}</div>
                </div>
                <div class="attendance-card">
                    <div class="attendance-card-title">Jam Pulang</div>
                    <div class="attendance-karyawan-name">{{ (string) ($absenInfo['pulang'] ?? '-') }}</div>
                </div>
                <div class="attendance-card">
                    <div class="attendance-card-title">Durasi Kerja</div>
                    <div class="attendance-karyawan-name">{{ (string) ($absenInfo['durasiLabel'] ?? '-') }}</div>
                </div>
            @endif
        </div>

        <div class="actions">
            @if($blockTodayForm)
                <a class="btn-primary" href="{{ route('absen.masuk.page') }}">Buka Halaman Koreksi</a>
            @elseif(!$alreadyMasuk)
                <a class="btn-primary" href="{{ route('absen.masuk.page') }}">Lanjut ke Absen Masuk</a>
            @elseif(!$alreadyPulang)
                <a class="btn-primary" href="{{ route('absen.pulang.page') }}">Lanjut ke Absen Pulang</a>
            @endif
            <a class="btn-neutral" href="{{ route('staff.home') }}">Kembali ke Portal</a>
        </div>
        </div>
    </div>
</div>
@endsection


