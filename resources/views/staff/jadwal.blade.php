@extends('layouts.staff')

@section('title', 'Jadwal Saya')

@section('content')
<div class="container app-shell">
    @php
        $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
        $mode = request('mode', $mode ?? 'list');
        $today = now()->format('Y-m-d');
        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        $leading = max(0, (int) $start->dayOfWeekIso - 1);
        $dows = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        $employmentTypeLabel = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
        $employmentDurationLabel = ($staffKaryawan && method_exists($staffKaryawan, 'employmentDurationLabel')) ? $staffKaryawan->employmentDurationLabel() : '8 jam';
        $shiftCode = function (int $shift) use ($setting, $staffKaryawan) {
            return $setting->shiftCodeFor($shift, $staffKaryawan->employment_type ?? null);
        };
        $shiftRangeLabel = function (int $shift, ?string $tgl = null) use ($setting, $staffKaryawan) {
            return $setting->shiftRangeLabel($shift, $staffKaryawan->employment_type ?? null, $tgl);
        };

        $scheduledEntries = collect($days ?? [])
            ->filter(fn ($tgl) => count((array) ($byTanggal[$tgl] ?? [])) > 0)
            ->values();

        $scheduledDays = $scheduledEntries->count();
        $totalShiftCount = $scheduledEntries
            ->map(fn ($tgl) => count((array) ($byTanggal[$tgl] ?? [])))
            ->sum();

        $nextScheduleDate = $scheduledEntries->first(fn ($tgl) => $tgl >= $today);
        $focusDate = $scheduledEntries->first(fn ($tgl) => $tgl === $today) ?: $nextScheduleDate;
        $focusShifts = $focusDate ? array_values(array_unique(array_map('intval', (array) ($byTanggal[$focusDate] ?? [])))) : [];
        sort($focusShifts);
        $focusTitle = $focusDate === $today ? 'Jadwal Hari Ini' : 'Jadwal Berikutnya';
        $focusDateLabel = $focusDate
            ? \Illuminate\Support\Carbon::parse($focusDate)->translatedFormat('l, d M Y')
            : 'Belum ada jadwal aktif';
        $focusShiftLabel = $focusDate && count($focusShifts) > 0
            ? implode(' • ', array_map(fn ($shift) => $shiftCode((int) $shift) . ' ' . $shiftRangeLabel((int) $shift, $focusDate), $focusShifts))
            : 'Nanti jadwal kerja berikutnya akan muncul di sini.';

        $todayShifts = array_values(array_unique(array_map('intval', (array) ($byTanggal[$today] ?? []))));
        sort($todayShifts);
        $todayHasSchedule = count($todayShifts) > 0;
        $todayLabel = \Illuminate\Support\Carbon::parse($today)->translatedFormat('l, d M Y');
        $initialView = in_array($mode, ['list', 'calendar'], true) ? $mode : 'list';

        $statusFor = function (string $tgl, int $shift) use ($absenByTanggalShift, $absenByTanggal, $shiftStarts, $lateToleranceMinutes, $today, $byTanggal) {
            $absRow = $absenByTanggalShift[$tgl][$shift] ?? null;
            if (! $absRow && isset($absenByTanggal[$tgl])) {
                $onlyOneShift = isset($byTanggal[$tgl]) && is_array($byTanggal[$tgl]) && count($byTanggal[$tgl]) === 1;
                if ($onlyOneShift) {
                    $absRow = $absenByTanggal[$tgl];
                }
            }

            $shiftStart = (string) ($shiftStarts[$shift] ?? '');
            $masukLabel = $absRow && $absRow->waktu_masuk ? $absRow->waktu_masuk->format('H:i') : null;
            $statusRaw = (string) ($absRow->status ?? '');
            $statusLabel = null;
            $statusClass = 'neu';

            if ($statusRaw === 'alpa') {
                $statusLabel = 'Alpa';
                $statusClass = 'bad';
            } elseif ($masukLabel && $shiftStart !== '') {
                $startAt = \Illuminate\Support\Carbon::parse($tgl . ' ' . $shiftStart . ':00');
                $deltaMin = (int) $absRow->waktu_masuk->diffInMinutes($startAt, false);
                $tol = (int) ($lateToleranceMinutes ?? 10);

                if ($deltaMin > $tol) {
                    $statusLabel = 'Telat ' . $deltaMin . 'm';
                    $statusClass = 'bad';
                } else {
                    $statusLabel = 'Hadir';
                    $statusClass = 'ok';
                }
            } elseif ($absRow) {
                $statusLabel = 'Hadir';
                $statusClass = 'ok';
            } else {
                $statusLabel = $tgl === $today ? 'Belum absen' : 'Sesuai Jadwal';
                $statusClass = $tgl === $today ? 'warn' : 'neu';
            }

            return [
                'label' => $statusLabel,
                'class' => $statusClass,
                'masuk' => $masukLabel,
            ];
        };

        $nama = (string) ($karyawan->nama_karyawan ?? 'Karyawan');
        $parts = array_values(array_filter(explode(' ', $nama)));
        $initials = collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
        $focusStatuses = collect($focusShifts)->map(fn ($shift) => $focusDate ? $statusFor($focusDate, (int) $shift) : null)->filter();
        $focusStatusText = $focusStatuses->pluck('label')->filter()->unique()->take(2)->implode(' • ');
        $focusStatusText = $focusStatusText !== '' ? $focusStatusText : ($focusDate ? 'Belum ada aktivitas' : 'Belum ada jadwal');
        $focusStatusVariant = $focusStatuses->contains(fn ($item) => ($item['class'] ?? '') === 'bad')
            ? 'bad'
            : ($focusStatuses->contains(fn ($item) => ($item['class'] ?? '') === 'warn')
                ? 'warn'
                : ($focusStatuses->contains(fn ($item) => ($item['class'] ?? '') === 'ok') ? 'ok' : 'neu'));
        $modeLabel = $mode === 'calendar' ? 'Mode Kalender' : 'Mode List';
        $hourNow = (int) now()->format('H');
        $greeting = match (true) {
            $hourNow < 11 => 'Pagi',
            $hourNow < 15 => 'Siang',
            $hourNow < 18 => 'Sore',
            default => 'Malam',
        };
    @endphp

    <div class="staff-schedule-mobile-screen is-reference-style">
        <section class="staff-schedule-stage-app minimal reference-stage">
            <div class="staff-schedule-topbar reference-topbar">
                <div class="staff-schedule-topbar-title">
                    <span class="staff-schedule-top-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M8 3.8v3M16 3.8v3M4 9.5h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <strong>Jadwal Saya</strong>
                </div>
            </div>

            <div class="staff-schedule-greeting-card">
                <div class="staff-schedule-greeting-copy">
                    <h1>Halo, {{ $nama }}</h1>
                </div>
                <div class="staff-schedule-employment-card">
                    <span class="staff-schedule-inline-pill {{ $focusStatusVariant !== 'bad' ? 'ok' : 'bad' }}">{{ strtoupper($employmentTypeLabel) }}</span>
                    <small>{{ $karyawan->jabatan ?? 'Staf' }} &bull; {{ $employmentDurationLabel }}</small>
                </div>
            </div>

                        <article class="staff-schedule-summary-card reference-summary" id="schedule-active-summary">
                <div class="staff-schedule-summary-topline">
                    <div class="staff-schedule-summary-period">
                        <span class="staff-schedule-summary-label">Periode</span>
                        <strong>{{ $bulanLabel ?? $bulan }}</strong>
                    </div>
                    <button class="staff-schedule-period-toggle" type="button" data-schedule-period-toggle aria-controls="schedulePeriodPanel" aria-expanded="false" aria-label="Ganti periode aktif">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M8 3.8v3M16 3.8v3M4 9.5h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M9 13h6M12 10v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="staff-schedule-summary-stats summary-stats-inline">
                    <article>
                        <span>Hari Kerja</span>
                        <strong>{{ $scheduledDays }} Hari</strong>
                    </article>
                    <article>
                        <span>Total Shift</span>
                        <strong>{{ $totalShiftCount }} shift</strong>
                    </article>
                </div>

                <div class="staff-schedule-next-card">
                    <div class="staff-schedule-next-head">
                        <span>Shift Berikutnya</span>
                        <span class="staff-schedule-next-kicker">{{ $focusTitle }}</span>
                    </div>
                    <div class="staff-schedule-next-row">
                        <div class="staff-schedule-next-time">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M12 8v4l2.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <strong>{{ count($focusShifts) > 0 ? $shiftRangeLabel((int) $focusShifts[0], $focusDate) : '--' }}</strong>
                        </div>
                        <span class="staff-schedule-inline-pill {{ $focusStatusVariant }}">{{ $focusDate === $today ? 'Hari Ini' : 'Besok' }}</span>
                    </div>
                    <p>{{ $focusShiftLabel }}</p>
                </div>

                <form method="get" action="{{ route('staff.jadwal') }}" class="staff-schedule-period-form native-period-form">
                    <input id="bulan" class="staff-schedule-period-native" type="month" name="bulan" value="{{ $bulan }}" aria-label="Pilih periode aktif">
                    <input id="scheduleModeInput" type="hidden" name="mode" value="{{ $initialView }}">
                    <button class="staff-schedule-period-submit" type="submit" tabindex="-1" aria-hidden="true">Terapkan</button>
                </form>
            </article>

            <div class="staff-schedule-segmented reference-segmented" data-schedule-tabs>
                <button type="button" data-schedule-view="list" class="{{ $initialView === 'list' ? 'active' : '' }}">List</button>
                <button type="button" data-schedule-view="calendar" class="{{ $initialView === 'calendar' ? 'active' : '' }}">Calendar</button>
                <button type="button" data-schedule-view="today">Today</button>
            </div>
        </section>

        <section class="staff-schedule-mobile-section reference-section">
            @if(session('success'))
                <div class="alert ok">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert err">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

                        <div class="staff-schedule-content-head compact-head">
                <div>
                    <h2>Jadwal Hari Ini</h2>
                    <p>Pilih tampilan list, kalender, atau fokus jadwal hari ini tanpa pindah halaman.</p>
                </div>
                <span class="staff-schedule-content-pill" data-schedule-view-label>{{ $modeLabel }}</span>
            </div>

            <div class="staff-schedule-view-stack">
                <div class="staff-schedule-view-panel" data-schedule-panel="today" hidden>
                    <div class="staff-schedule-today-card">
                        <div class="staff-schedule-today-head">
                            <div>
                                <h2>Jadwal Hari Ini</h2>
                                <p>{{ $todayLabel }}</p>
                            </div>
                            <span class="staff-schedule-content-pill">{{ $todayHasSchedule ? count($todayShifts) . ' shift' : 'Kosong' }}</span>
                        </div>

                        @if($todayHasSchedule)
                            @php
                                $todayPrimaryShift = $todayShifts[0] ?? null;
                                $todayPrimaryStatus = $todayPrimaryShift ? $statusFor($today, (int) $todayPrimaryShift) : ['label' => 'Kosong', 'class' => 'neu', 'masuk' => null];
                                $todayDayLabel = \Illuminate\Support\Carbon::parse($today)->translatedFormat('D');
                                $todayDayNumLabel = \Illuminate\Support\Carbon::parse($today)->translatedFormat('d');
                            @endphp
                            <article class="schedule-mobile-card schedule-mobile-card-reference compact-month-card clickable is-today" data-date="{{ $today }}" role="button" aria-label="Detail jadwal hari ini">
                                <div class="schedule-mobile-card-datebox">
                                    <span>{{ strtoupper($todayDayLabel) }}</span>
                                    <strong>{{ $todayDayNumLabel }}</strong>
                                </div>
                                <div class="schedule-mobile-card-body">
                                    <div class="schedule-mobile-card-head reference-head">
                                        <div>
                                            <div class="schedule-mobile-card-date">{{ count($todayShifts) > 1 ? 'Multiple Shift' : (($todayPrimaryShift ? $shiftCode((int) $todayPrimaryShift) : 'Jadwal') . ' Shift') }}</div>
                                            <div class="schedule-mobile-card-sub">Fokus jadwal untuk hari ini</div>
                                        </div>
                                        <span class="schedule-mobile-card-count {{ $todayPrimaryStatus['class'] }}">{{ $todayPrimaryStatus['label'] }}</span>
                                    </div>

                                    <div class="schedule-mobile-slot-list compact">
                                        @foreach($todayShifts as $s)
                                            @php $st = $statusFor($today, $s); @endphp
                                            <div class="schedule-mobile-slot compact-slot">
                                                <div class="schedule-mobile-slot-main compact-main">
                                                    <span class="schedule-mobile-slot-code">{{ $shiftCode($s) }}</span>
                                                    <div class="schedule-mobile-slot-copy">
                                                        <span class="schedule-mobile-slot-time">{{ $shiftRangeLabel($s, $today) }}</span>
                                                        <small>{{ $st['masuk'] ? 'Masuk ' . $st['masuk'] : 'Belum absen' }}</small>
                                                    </div>
                                                </div>
                                                <span class="schedule-mobile-slot-status {{ $st['class'] }}">{{ $st['label'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </article>
                        @else
                            <div class="schedule-mobile-empty compact-empty">
                                <strong>Belum ada jadwal untuk hari ini.</strong>
                                <span>Kalau ada perubahan dari admin, jadwal hari ini akan muncul di sini tanpa perlu ganti halaman.</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="staff-schedule-view-panel" data-schedule-panel="list" {{ $initialView === 'list' ? '' : 'hidden' }}>
                    <div class="schedule-mobile-list reference-list compact-month-list">
                        @forelse($days as $tgl)
                            @php
                                $shifts = $byTanggal[$tgl] ?? [];
                                $shifts = array_values(array_unique(array_map('intval', (array) $shifts)));
                                sort($shifts);
                                $isToday = $tgl === $today;
                                $dayLabel = \Illuminate\Support\Carbon::parse($tgl)->translatedFormat('D');
                                $dayNumLabel = \Illuminate\Support\Carbon::parse($tgl)->translatedFormat('d');
                                $dateLabel = \Illuminate\Support\Carbon::parse($tgl)->translatedFormat('d M');
                                $shiftCount = count($shifts);
                                $isEmptyDay = $shiftCount === 0;
                                $primaryShift = $shifts[0] ?? null;
                                $primaryStatus = $primaryShift ? $statusFor($tgl, (int) $primaryShift) : ['label' => 'Kosong', 'class' => 'neu', 'masuk' => null];
                                $compactCode = $primaryShift ? $shiftCode((int) $primaryShift) : 'OFF';
                                $compactTime = $primaryShift ? $shiftRangeLabel((int) $primaryShift, $tgl) : 'Kosong';
                                $compactNote = $isEmptyDay
                                    ? 'Tidak ada jadwal'
                                    : ($primaryStatus['masuk'] ? 'Masuk ' . $primaryStatus['masuk'] : ($isToday ? 'Belum absen' : 'Sesuai jadwal'));
                            @endphp
                            <article class="schedule-mobile-card schedule-mobile-card-reference compact-month-card clickable {{ $isToday ? 'is-today' : '' }} {{ $isEmptyDay ? 'is-empty' : '' }}" data-date="{{ $tgl }}" role="button" aria-label="Detail jadwal {{ $tgl }}">
                                <div class="schedule-mobile-card-datebox">
                                    <span>{{ strtoupper($dayLabel) }}</span>
                                    <strong>{{ $dayNumLabel }}</strong>
                                </div>
                                <div class="schedule-mobile-card-body">
                                    <div class="schedule-mobile-card-head reference-head">
                                        <div>
                                            <div class="schedule-mobile-card-date">{{ $isEmptyDay ? 'Hari Kosong' : (count($shifts) > 1 ? 'Multiple Shift' : (($primaryShift ? $shiftCode((int) $primaryShift) : 'Jadwal') . ' Shift')) }}</div>
                                            <div class="schedule-mobile-card-sub">{{ $isToday ? 'Jadwal hari ini' : $dateLabel }}</div>
                                        </div>
                                        <span class="schedule-mobile-card-count {{ $primaryStatus['class'] }}">{{ $primaryStatus['label'] }}</span>
                                    </div>

                                    <div class="schedule-mobile-compact-grid">
                                        <div class="schedule-mobile-compact-cell">
                                            <span>Kode</span>
                                            <strong>{{ $compactCode }}</strong>
                                        </div>
                                        <div class="schedule-mobile-compact-cell">
                                            <span>Jam</span>
                                            <strong>{{ $compactTime }}</strong>
                                        </div>
                                    </div>

                                    <div class="schedule-mobile-compact-footer">
                                        <small>{{ $compactNote }}</small>
                                        @if($shiftCount > 1)
                                            <div class="schedule-mobile-mini-shifts">
                                                @foreach($shifts as $s)
                                                    <span class="schedule-mobile-mini-chip">{{ $shiftCode($s) }} · {{ $shiftRangeLabel($s, $tgl) }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="schedule-mobile-empty">
                                <strong>Belum ada jadwal di periode ini.</strong>
                                <span>Coba pilih bulan lain atau tunggu admin mengatur jadwal kerja berikutnya.</span>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="staff-schedule-view-panel" data-schedule-panel="calendar" {{ $initialView === 'calendar' ? '' : 'hidden' }}>
                    <div class="staff-schedule-note">
                        <strong>Kalender {{ $bulanLabel ?? $bulan }}</strong>
                        <span>Shift yang aktif akan muncul sebagai chip kecil agar tetap hemat tempat di HP.</span>
                    </div>

                    <div class="cal" aria-label="Kalender jadwal">
                        @foreach($dows as $dow)
                            <div class="dow">{{ $dow }}</div>
                        @endforeach

                        @for($i = 0; $i < $leading; $i++)
                            <div class="day empty"></div>
                        @endfor

                        @foreach($days as $tgl)
                            @php
                                $shifts = $byTanggal[$tgl] ?? [];
                                $shifts = array_values(array_unique(array_map('intval', (array) $shifts)));
                                sort($shifts);
                                $isToday = $tgl === $today;
                                $dayNum = (int) substr($tgl, 8, 2);
                                $hasShift = count($shifts) > 0;
                            @endphp
                            <div class="day clickable {{ $isToday ? 'today' : '' }} {{ $hasShift ? 'has-shift' : '' }}" data-date="{{ $tgl }}">
                                <div class="meta2">
                                    <div class="n">{{ $dayNum }}</div>
                                    <div class="r">
                                        @if($isToday)
                                            <span class="chip shift">Hari ini</span>
                                        @endif
                                        @foreach($shifts as $s)
                                            <span class="chip shift shift{{ $s }}">{{ $shiftCode($s) }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="status-line">
                                    @if(! $hasShift)
                                        <span class="chip neu">Kosong</span>
                                    @else
                                        @foreach($shifts as $s)
                                            @php $st = $statusFor($tgl, $s); @endphp
                                            <span class="chip {{ $st['class'] }}">{{ $shiftCode($s) }} {{ $st['label'] }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal-backdrop" id="jadwalModal" aria-hidden="true">
    <div class="modal-card bottom" role="dialog" aria-modal="true" aria-labelledby="jadwalModalTitle">
        <div class="modal-head">
            <div>
                <div class="modal-title" id="jadwalModalTitle">Detail Jadwal</div>
                <div class="modal-subtitle" id="jadwalModalSubtitle">Siapa saja yang bertugas pada tanggal ini.</div>
            </div>
            <button class="btn-neutral" type="button" id="jadwalModalClose">Tutup</button>
        </div>
        <div class="modal-body" id="jadwalModalBody"></div>
        <div class="modal-actions">
            <button class="btn-primary" type="button" id="jadwalModalOk">Oke</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const scheduleMap = @json($calendarDetails ?? []);
        const shiftLabels = {{ Illuminate\Support\Js::from([
            1 => $shiftCode(1),
            2 => $shiftCode(2),
            3 => $shiftCode(3),
        ]) }};
        const modal = document.getElementById('jadwalModal');
        const modalBody = document.getElementById('jadwalModalBody');
        const modalTitle = document.getElementById('jadwalModalTitle');
        const modalSubtitle = document.getElementById('jadwalModalSubtitle');
        const closeBtn = document.getElementById('jadwalModalClose');
        const okBtn = document.getElementById('jadwalModalOk');
        const staffName = @json((string) ($karyawan->nama_karyawan ?? ''));

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderNames(names) {
            if (!names || !names.length) {
                return '<div class="modal-empty">Belum ada jadwal pada slot ini.</div>';
            }

            const items = names.map(function (name) {
                const safe = escapeHtml(name);
                const isMe = staffName && String(name).toLowerCase() === String(staffName).toLowerCase();
                const cls = isMe ? 'chip me' : 'chip neu';
                return '<span class="' + cls + '">' + safe + '</span>';
            }).join('');

            return '<div class="chip-group">' + items + '</div>';
        }

        function renderShiftCard(label, names, variantClass) {
            const count = Array.isArray(names) ? names.length : 0;
            const countLabel = count > 0 ? count + ' orang' : 'Kosong';

            return [
                '<section class="modal-shift-card ' + variantClass + '">',
                    '<div class="modal-shift-top">',
                        '<div class="modal-shift-title">' + label + '</div>',
                        '<div class="modal-shift-count">' + countLabel + '</div>',
                    '</div>',
                    '<div class="shift-names">' + renderNames(names) + '</div>',
                '</section>',
            ].join('');
        }

        function openModal(dateStr) {
            const data = scheduleMap[dateStr] || {};

            modalTitle.textContent = 'Jadwal ' + dateStr;
            modalSubtitle.textContent = 'Lihat siapa saja yang bertugas pada tiap slot di tanggal ini.';

            modalBody.innerHTML = [
                renderShiftCard(shiftLabels[1] || 'Shift 1', data.shift1 || [], 'shift1'),
                renderShiftCard(shiftLabels[2] || 'Shift 2', data.shift2 || [], 'shift2'),
                renderShiftCard(shiftLabels[3] || 'Shift 3', data.shift3 || [], 'shift3'),
            ].join('');

            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }

        document.querySelectorAll('.day.clickable[data-date], .schedule-mobile-card.clickable[data-date]').forEach(function (element) {
            element.addEventListener('click', function () {
                const dateStr = element.getAttribute('data-date');
                if (dateStr) {
                    openModal(dateStr);
                }
            });
        });

        closeBtn.addEventListener('click', closeModal);
        okBtn.addEventListener('click', closeModal);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('show')) {
                closeModal();
            }
        });

        const tabs = document.querySelectorAll('[data-schedule-view]');
        const panels = document.querySelectorAll('[data-schedule-panel]');
        const viewLabel = document.querySelector('[data-schedule-view-label]');
        const modeInput = document.getElementById('scheduleModeInput');
        const periodToggle = document.querySelector('[data-schedule-period-toggle]');
        const periodInput = document.getElementById('bulan');
        const labelMap = {
            list: 'Mode List',
            calendar: 'Mode Kalender',
            today: 'Hari Ini',
        };

        function setView(view) {
            const nextView = ['list', 'calendar', 'today'].includes(view) ? view : 'list';

            panels.forEach(function (panel) {
                const isActive = panel.getAttribute('data-schedule-panel') === nextView;
                panel.hidden = !isActive;
            });

            tabs.forEach(function (tab) {
                const isActive = tab.getAttribute('data-schedule-view') === nextView;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            if (viewLabel) {
                viewLabel.textContent = labelMap[nextView] || labelMap.list;
            }

            if (modeInput) {
                modeInput.value = nextView === 'today' ? 'list' : nextView;
            }
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setView(tab.getAttribute('data-schedule-view'));
            });
        });

        setView(@json($initialView));

        if (periodToggle && periodInput) {
            periodToggle.addEventListener('click', function () {
                periodToggle.setAttribute('aria-expanded', 'true');
                if (typeof periodInput.showPicker === 'function') {
                    periodInput.showPicker();
                } else {
                    periodInput.focus();
                    periodInput.click();
                }
            });

            periodInput.addEventListener('change', function () {
                const form = periodInput.form;
                if (form) {
                    form.submit();
                }
            });
        }
    })();
</script>
@endsection


