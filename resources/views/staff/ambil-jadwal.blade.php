@extends('layouts.staff')

@section('title', 'Ambil Jadwal')

@section('content')
<div class="container app-shell">
    @php
        $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
        $employmentType = (string) ($employmentType ?? ($staffKaryawan->employment_type ?? null));
        $employmentLabel = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : (\App\Models\Karyawan::employmentTypeLabelFor($employmentType));
        $durasiKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentDurationLabel')) ? $staffKaryawan->employmentDurationLabel() : '8 jam';
        $isPartTimeSchedule = (bool) ($isPartTimeSchedule ?? false);
        $shiftCode = fn (int $shift) => $setting->shiftCodeFor($shift, $employmentType);
        $shiftRange = fn (int $shift, ?string $date = null) => $setting->shiftRangeLabel($shift, $employmentType, $date);
        $shiftBadge = fn (int $shift, ?string $date = null) => $shiftCode($shift) . ' • ' . $shiftRange($shift, $date);
        $todayStr = $today ?? now()->toDateString();
        $outOfOpenWindow = ($open ?? false) && ($openStart ?? null) && ($openEnd ?? null)
            && ($todayStr < $openStart || $todayStr > $openEnd);
        $daysCollection = collect($days ?? []);
        $mineCollection = collect($mine ?? []);
        $pickedShiftTotal = $mineCollection->reduce(function ($carry, $shifts) {
            return $carry + count(array_unique(array_map('intval', (array) $shifts)));
        }, 0);
        $scheduledDays = $mineCollection->filter(fn ($shifts) => count((array) $shifts) > 0)->count();
        $limitLabel = trim(
            (($maxPerWeek ?? null) ? ((int) $maxPerWeek . '/minggu') : '')
            . ((($maxPerWeek ?? null) && ($maxPerMonth ?? null)) ? '  -  ' : '')
            . (($maxPerMonth ?? null) ? ((int) $maxPerMonth . '/bulan') : '')
        );
        $canPickNow = $enabled
            && $open
            && (bool) $rangeStart
            && (bool) $rangeEnd
            && ! ($outOfOpenWindow ?? false);
        $statusLabel = ! $enabled
            ? 'Nonaktif'
            : (! $open
                ? 'Ditutup'
                : (($outOfOpenWindow ?? false) ? 'Di luar jadwal buka' : 'Sedang dibuka'));
        $statusClass = ! $enabled ? 'bad' : ((! $open || ($outOfOpenWindow ?? false)) ? 'warn' : 'ok');
        $periodLabel = ($rangeStart && $rangeEnd) ? ($rangeStart . ' s/d ' . $rangeEnd) : 'Belum diatur';
        $openLabel = ($openStart && $openEnd) ? ($openStart . ' s/d ' . $openEnd) : 'Mengikuti tombol buka/tutup admin';
    @endphp
    <div class="staff-mobile-page-screen">
        <section class="staff-mobile-page-stage">
            @include('staff.partials.mobile-page-header', [
                'pageTitle' => 'Ambil Jadwal Mandiri',
                'pageMark' => 'JD',
                'staffName' => (string) ($karyawan->nama_karyawan ?? 'Karyawan'),
                'greetingTitle' => 'Halo, ' . (string) ($karyawan->nama_karyawan ?? 'Karyawan'),
                'greetingSubtitle' => $isPartTimeSchedule ? 'Pilih slot part time yang tersedia selama fase penjadwalan dibuka.' : 'Pilih shift yang tersedia selama fase penjadwalan dibuka.',
                'employmentLabel' => $employmentLabel,
                'employmentMeta' => trim((string) ($karyawan->jabatan ?? 'Staff')) . ' • ' . $durasiKerja,
            ])

            <article class="staff-mobile-page-summary-card">
                <div class="staff-mobile-page-summary-topline">
                    <div class="staff-mobile-page-summary-period">
                        <span class="staff-mobile-page-summary-label">Status Pengisian</span>
                        <strong>{{ $statusLabel }}</strong>
                    </div>
                    <span class="staff-mobile-page-pill">{{ $periodLabel }}</span>
                </div>

                <div class="staff-mobile-page-summary-stats">
                    <article>
                        <span>Hari Ditampilkan</span>
                        <strong>{{ $daysCollection->count() }}</strong>
                    </article>
                    <article>
                        <span>{{ $isPartTimeSchedule ? 'Slot Sudah Diambil' : 'Shift Sudah Diambil' }}</span>
                        <strong>{{ $pickedShiftTotal }}</strong>
                    </article>
                    <article>
                        <span>Hari Terisi</span>
                        <strong>{{ $scheduledDays }}</strong>
                    </article>
                    <article>
                        <span>Batas</span>
                        <strong>{{ $limitLabel !== '' ? $limitLabel : 'Tidak dibatasi' }}</strong>
                    </article>
                </div>

                <div class="staff-mobile-page-summary-note">Jadwal buka: {{ $openLabel }}</div>

                <div class="staff-mobile-page-summary-actions">
                    <a class="btn-neutral" href="{{ route('staff.home') }}">Kembali</a>
                    <a class="btn-primary" href="{{ route('staff.swap.index') }}">Tukar Jadwal</a>
                </div>
            </article>
        </section>

        <div class="panel schedule-note-panel">
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

        <div class="schedule-note-card">
            @if(! $enabled)
                <div class="schedule-note-title">Fitur sedang dinonaktifkan admin</div>
                <div class="schedule-note-sub">Menu ini tetap tampil supaya kamu tahu fitur ada, tapi pengambilan jadwal belum bisa dilakukan.</div>
            @elseif(! $rangeStart || ! $rangeEnd)
                <div class="schedule-note-title">Periode jadwal belum ditentukan</div>
                <div class="schedule-note-sub">Tunggu admin mengisi rentang tanggal jadwal yang boleh diambil.</div>
            @elseif(! $open)
                <div class="schedule-note-title">Pendaftaran belum dibuka</div>
                <div class="schedule-note-sub">Admin sudah mengaktifkan fitur, tapi fase pengisian sedang ditutup sementara.</div>
            @elseif($outOfOpenWindow)
                <div class="schedule-note-title">Fase pengisian belum berjalan saat ini</div>
                <div class="schedule-note-sub">Pendaftaran hanya aktif pada {{ $openStart ?? '-' }} s/d {{ $openEnd ?? '-' }}.</div>
            @else
                <div class="schedule-note-title">Kamu bisa mulai mengambil jadwal</div>
                <div class="schedule-note-sub">{{ $isPartTimeSchedule ? 'Pilih slot PT yang tersedia.' : 'Pilih shift yang tersedia.' }} Kalau nanti perlu menukar jadwal, lanjutkan dari menu Tukar Jadwal.</div>
            @endif
        </div>
        </div>

        <div class="panel schedule-days-panel">
        <div class="schedule-section-head">
            <div>
                <h2 class="schedule-section-title">{{ $isPartTimeSchedule ? 'Daftar Hari & Slot PT' : 'Daftar Hari & Shift' }}</h2>
                <div class="schedule-section-sub">Setiap kartu menampilkan slot yang tersisa, jam kerjanya, jadwalmu saat ini, serta tombol cepat untuk ambil atau batalkan.</div>
            </div>
            <span class="pill gray">{{ $statusLabel }}</span>
        </div>

        <div class="mobile-list">
            @forelse(($days ?? []) as $d)
                @php
                    $isPast = is_string($today ?? null) ? ($d < $today) : false;
                    $myShifts = $mine[$d] ?? [];
                    $myShifts = array_values(array_unique(array_map('intval', (array) $myShifts)));
                    sort($myShifts);
                    $hasAnyShift = count($myShifts) > 0;
                    $myShiftLabel = $hasAnyShift ? ('S' . implode(', S', $myShifts)) : null;
                    $isWeekend = (bool) ($isWeekendByDay[$d] ?? false);
                    $weekCount = (int) ($weekCountByDay[$d] ?? 0);
                    $monthCount = (int) ($monthCountByDay[$d] ?? 0);
                    $maxWeek = (int) ($maxPerWeek ?? 0);
                    $maxMonth = (int) ($maxPerMonth ?? 0);
                @endphp
                <div class="day-card">
                    <div class="day-head">
                        <div>
                            <div class="day-date">{{ $d }}</div>
                            <div class="day-meta">
                                @if($d === ($today ?? ''))
                                    <span class="pill dark">Hari ini</span>
                                @endif
                                @if($isWeekend)
                                    <span class="pill warn">Weekend</span>
                                @endif
                                @if($hasAnyShift)
                                    <span class="pill gray">Jadwalmu: {{ collect($myShifts)->map(fn ($shift) => $shiftCode((int) $shift))->implode(', ') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="slot-group">
                        @for($s = 1; $s <= (int) ($activeShiftCount ?? 2); $s++)
                            @php
                                $cap = (int) (($capacityByDayShift[$d][$s] ?? null) ?? ($capacityByShift[$s] ?? 1));
                                $taken = (int) ($counts[$d][$s] ?? 0);
                                $left = max(0, $cap - $taken);
                                $hasShift = in_array($s, $myShifts, true);
                                $disabled = !($canPickNow ?? false) || $isPast;
                                if ($maxWeek > 0 && $weekCount >= $maxWeek) { $disabled = true; }
                                if ($maxMonth > 0 && $monthCount >= $maxMonth) { $disabled = true; }
                                if ($left <= 0) { $disabled = true; }
                                if ($hasShift) { $disabled = true; }
                            @endphp
                            @if($hasShift)
                                <button class="btn-pick primary" type="button" disabled>Sudah Ambil {{ $shiftCode($s) }}</button>
                            @elseif(!($canPickNow ?? false))
                                <button class="btn-pick" type="button" disabled>{{ $shiftCode($s) }} • Belum Masuk Fase</button>
                            @elseif($left <= 0)
                                <button class="btn-pick" type="button" disabled>{{ $shiftCode($s) }} Penuh</button>
                            @else
                                <form method="post" action="{{ route('staff.self_schedule.pick') }}">
                                    @csrf
                                    <input type="hidden" name="tanggal" value="{{ $d }}">
                                    <input type="hidden" name="shift_ke" value="{{ $s }}">
                                    <button class="btn-pick" type="submit" @disabled($disabled)>
                                        Ambil {{ $shiftCode($s) }} • {{ $shiftRange($s, $d) }} • {{ $left }}/{{ $cap }}
                                    </button>
                                </form>
                            @endif
                        @endfor
                    </div>

                    @php
                        $actionDisabled = (! $enabled) || (! $open) || ($outOfOpenWindow ?? false);
                    @endphp
                    <div class="day-actions">
                        <button class="{{ $actionDisabled ? 'btn-pick' : 'btn-pick primary' }}" type="button" onclick="openSheet('{{ $d }}')">
                            {{ $actionDisabled ? 'Lihat Info Hari Ini' : 'Lihat Opsi Hari Ini' }}
                        </button>
                    </div>

                    @php
                        $canCancel = (bool) ($allowCancel ?? false)
                            && $hasAnyShift
                            && ! $isPast
                            && is_string($today ?? null)
                            && $d > $today;
                        if ($canCancel && (int) ($cancelMinDaysBefore ?? 0) > 0 && is_string($today ?? null)) {
                            try {
                                $diff = \Illuminate\Support\Carbon::parse($today)->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($d)->startOfDay(), false);
                            } catch (\Throwable $e) {
                                $diff = 0;
                            }
                            if ($diff < (int) $cancelMinDaysBefore) { $canCancel = false; }
                        }
                    @endphp
                    @if($canCancel)
                        <div class="day-actions">
                            @foreach($myShifts as $ms)
                                <form method="post" action="{{ route('staff.self_schedule.cancel') }}">
                                    @csrf
                                    <input type="hidden" name="tanggal" value="{{ $d }}">
                                    <input type="hidden" name="shift_ke" value="{{ $ms }}">
                                    <button class="btn-pick danger" type="submit" onclick="return confirm('Batalkan jadwal tanggal {{ $d }} {{ $shiftCode((int) $ms) }}?')">Batal {{ $shiftCode((int) $ms) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif

                    @if($hasAnyShift)
                        <div class="sub">Kamu masih bisa ambil slot lain di hari ini kalau memang masih tersedia.</div>
                    @else
                        @if(($maxWeek ?? null) || ($maxMonth ?? null))
                            <div class="sub">
                                @if(($maxPerWeek ?? null)) Minggu ini: {{ $weekCount }}/{{ (int) $maxPerWeek }} @endif
                                @if(($maxPerWeek ?? null) && ($maxPerMonth ?? null))  -  @endif
                                @if(($maxPerMonth ?? null)) Bulan ini: {{ $monthCount }}/{{ (int) $maxPerMonth }} @endif
                            </div>
                        @endif
                    @endif
                </div>
            @empty
                <div class="history-empty">
                    <div class="history-empty-title">Belum ada hari yang bisa dipilih</div>
                    <div class="history-empty-sub">Saat admin membuka periode jadwal, daftar harinya akan muncul di sini.</div>
                </div>
            @endforelse
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="u-w-180">Tanggal</th>
                        <th>Shift Tersedia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($days ?? []) as $d)
                        @php
                            $isPast = is_string($today ?? null) ? ($d < $today) : false;
                            $myShifts = $mine[$d] ?? [];
                            $myShifts = array_values(array_unique(array_map('intval', (array) $myShifts)));
                            sort($myShifts);
                            $hasAnyShift = count($myShifts) > 0;
                            $myShiftLabel = $hasAnyShift ? ('S' . implode(', S', $myShifts)) : null;
                            $isWeekend = (bool) ($isWeekendByDay[$d] ?? false);
                            $weekCount = (int) ($weekCountByDay[$d] ?? 0);
                            $monthCount = (int) ($monthCountByDay[$d] ?? 0);
                            $maxWeek = (int) ($maxPerWeek ?? 0);
                            $maxMonth = (int) ($maxPerMonth ?? 0);
                        @endphp
                        <tr>
                            <td>
                                <div class="dayline">
                                    <div>
                                        <div class="d">{{ $d }}</div>
                                        <div class="meta">
                                            @if($d === ($today ?? ''))
                                                Hari ini
                                            @elseif($isWeekend)
                                                Weekend
                                            @else
                                                Hari kerja
                                            @endif
                                        </div>
                                    </div>
                                    @if($hasAnyShift)
                                        <span class="pill gray">{{ collect($myShifts)->map(fn ($shift) => $shiftCode((int) $shift))->implode(', ') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="slots">
                                    @for($s = 1; $s <= (int) ($activeShiftCount ?? 2); $s++)
                                        @php
                                            $cap = (int) (($capacityByDayShift[$d][$s] ?? null) ?? ($capacityByShift[$s] ?? 1));
                                            $taken = (int) ($counts[$d][$s] ?? 0);
                                            $left = max(0, $cap - $taken);
                                            $hasShift = in_array($s, $myShifts, true);
                                            $disabled = !($canPickNow ?? false) || $isPast;
                                            if ($maxWeek > 0 && $weekCount >= $maxWeek) { $disabled = true; }
                                            if ($maxMonth > 0 && $monthCount >= $maxMonth) { $disabled = true; }
                                            if ($left <= 0) { $disabled = true; }
                                            if ($hasShift) { $disabled = true; }
                                        @endphp
                                        @if($hasShift)
                                            <button class="btn-pick primary" type="button" disabled>Sudah Ambil {{ $shiftCode($s) }}</button>
                                        @elseif(!($canPickNow ?? false))
                                            <button class="btn-pick" type="button" disabled>{{ $shiftCode($s) }} • Belum Masuk Fase</button>
                                        @elseif($left <= 0)
                                            <button class="btn-pick" type="button" disabled>{{ $shiftCode($s) }} Penuh</button>
                                        @else
                                            <form method="post" action="{{ route('staff.self_schedule.pick') }}">
                                                @csrf
                                                <input type="hidden" name="tanggal" value="{{ $d }}">
                                                <input type="hidden" name="shift_ke" value="{{ $s }}">
                                                <button class="btn-pick" type="submit" @disabled($disabled)>
                                                    Ambil {{ $shiftCode($s) }} • {{ $shiftRange($s, $d) }} • {{ $left }}/{{ $cap }}
                                                </button>
                                            </form>
                                        @endif
                                    @endfor

                                    @php
                                        $canCancel = (bool) ($allowCancel ?? false)
                                            && $hasAnyShift
                                            && ! $isPast
                                            && is_string($today ?? null)
                                            && $d > $today;
                                        if ($canCancel && (int) ($cancelMinDaysBefore ?? 0) > 0 && is_string($today ?? null)) {
                                            try {
                                                $diff = \Illuminate\Support\Carbon::parse($today)->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($d)->startOfDay(), false);
                                            } catch (\Throwable $e) {
                                                $diff = 0;
                                            }
                                            if ($diff < (int) $cancelMinDaysBefore) { $canCancel = false; }
                                        }
                                    @endphp
                                    @if($canCancel)
                                        @foreach($myShifts as $ms)
                                            <form method="post" action="{{ route('staff.self_schedule.cancel') }}">
                                                @csrf
                                                <input type="hidden" name="tanggal" value="{{ $d }}">
                                                <input type="hidden" name="shift_ke" value="{{ $ms }}">
                                                <button class="btn-pick danger" type="submit" onclick="return confirm('Batalkan jadwal tanggal {{ $d }} {{ $shiftCode((int) $ms) }}?')">Batal {{ $shiftCode((int) $ms) }}</button>
                                            </form>
                                        @endforeach
                                    @endif
                                </div>
                                @if($hasAnyShift)
                                    <div class="sub u-mt-6">Kamu sudah terjadwal di {{ collect($myShifts)->map(fn ($shift) => $shiftBadge((int) $shift, $d))->implode(', ') }}. Kalau mau tukar, lanjutkan ke menu Tukar Jadwal.</div>
                                @elseif(($maxWeek ?? null) || ($maxMonth ?? null))
                                    <div class="sub u-mt-6">
                                        @if(($maxPerWeek ?? null)) Minggu ini: {{ $weekCount }}/{{ (int) $maxPerWeek }} @endif
                                        @if(($maxPerWeek ?? null) && ($maxPerMonth ?? null))  -  @endif
                                        @if(($maxPerMonth ?? null)) Bulan ini: {{ $monthCount }}/{{ (int) $maxPerMonth }} @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="sub">Belum ada periode yang bisa diambil.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
    </div>
</div>

<div class="action-sheet" id="actionSheet" aria-hidden="true">
    <div class="sheet-card">
        <div class="sheet-head">
            <div class="sheet-title" id="sheetTitle">Detail Hari</div>
            <button class="btn-neutral" type="button" onclick="closeSheet()">Tutup</button>
        </div>
        <div class="sheet-body" id="sheetBody"></div>
    </div>
</div>

@php
    $sheetShiftCodes = [];
    for ($sheetShift = 1; $sheetShift <= 3; $sheetShift++) {
        $sheetShiftCodes[$sheetShift] = $setting->shiftCodeFor($sheetShift, $employmentType);
    }

    $sheetShiftRanges = [];
    foreach (($days ?? []) as $sheetDate) {
        $sheetItems = [];
        for ($sheetShift = 1; $sheetShift <= (int) ($activeShiftCount ?? 2); $sheetShift++) {
            $sheetItems[$sheetShift] = $setting->shiftRangeLabel($sheetShift, $employmentType, $sheetDate);
        }
        $sheetShiftRanges[$sheetDate] = $sheetItems;
    }
@endphp
<script>
    const sheetData = @json($days ?? []);
    const sheetMap = @json($capacityByDayShift ?? []);
    const sheetMine = @json($mine ?? []);
    const sheetCounts = @json($counts ?? []);
    const sheetOpen = @json($open ?? false);
    const sheetEnabled = @json($enabled ?? false);
    const sheetCanPickNow = @json($canPickNow ?? false);
    const sheetActiveShift = @json($activeShiftCount ?? 2);
    const sheetAllowCancel = @json($allowCancel ?? false);
    const sheetToday = @json($today ?? null);
    const sheetOpenStart = @json($openStart ?? null);
    const sheetOpenEnd = @json($openEnd ?? null);
    const sheetWeekCounts = @json($weekCountByDay ?? []);
    const sheetMonthCounts = @json($monthCountByDay ?? []);
    const sheetMaxWeek = @json($maxPerWeek ?? 0);
    const sheetMaxMonth = @json($maxPerMonth ?? 0);
    const sheetShiftCodes = {{ Illuminate\Support\Js::from($sheetShiftCodes) }};
    const sheetShiftRanges = {{ Illuminate\Support\Js::from($sheetShiftRanges) }};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function openSheet(dateStr) {
        const sheet = document.getElementById('actionSheet');
        const title = document.getElementById('sheetTitle');
        const body = document.getElementById('sheetBody');

        title.textContent = 'Detail ' + dateStr;
        let html = '';
        let note = '';

        if (!sheetEnabled) note = 'Fitur ambil jadwal sedang nonaktif.';
        else if (!sheetOpen) note = 'Pendaftaran sedang ditutup oleh admin.';
        else if (sheetOpenStart && sheetOpenEnd && sheetToday && (sheetToday < sheetOpenStart || sheetToday > sheetOpenEnd)) {
            note = `Pendaftaran aktif ${sheetOpenStart} s/d ${sheetOpenEnd}.`;
        }

        if (note) {
            html += `<div class="sub">${note}</div>`;
        }

        for (let s = 1; s <= sheetActiveShift; s++) {
            const cap = (sheetMap[dateStr] && sheetMap[dateStr][s]) ? sheetMap[dateStr][s] : 1;
            const taken = (sheetCounts[dateStr] && sheetCounts[dateStr][s]) ? sheetCounts[dateStr][s] : 0;
            const left = Math.max(0, cap - taken);
            const myShifts = sheetMine[dateStr] || [];
            const isPast = sheetToday && dateStr < sheetToday;
            const weekCount = sheetWeekCounts[dateStr] || 0;
            const monthCount = sheetMonthCounts[dateStr] || 0;
            const shiftCode = sheetShiftCodes[s] || `S${s}`;
            const shiftRange = (sheetShiftRanges[dateStr] && sheetShiftRanges[dateStr][s]) ? sheetShiftRanges[dateStr][s] : '';

            html += `<div class="sheet-row"><div><strong>${shiftCode}</strong><div class="sub">${shiftRange}</div><div class="sub">Sisa ${left}/${cap}</div></div>`;

            if (Array.isArray(myShifts) && myShifts.includes(s)) {
                html += `<div><button class="btn-pick primary" type="button" disabled>Sudah diambil</button></div>`;
            } else {
                let disabledReason = '';

                if (!sheetEnabled) disabledReason = 'Nonaktif';
                else if (!sheetOpen) disabledReason = 'Ditutup';
                else if (!sheetCanPickNow) disabledReason = 'Belum masuk fase';
                else if (isPast) disabledReason = 'Lewat';
                else if (left <= 0) disabledReason = 'Penuh';
                else if (sheetMaxWeek > 0 && weekCount >= sheetMaxWeek) disabledReason = 'Limit mingguan';
                else if (sheetMaxMonth > 0 && monthCount >= sheetMaxMonth) disabledReason = 'Limit bulanan';

                if (disabledReason) {
                    html += `<div><button class="btn-pick" type="button" disabled>${disabledReason}</button></div>`;
                } else {
                    html += `<div><form method="post" action="{{ route('staff.self_schedule.pick') }}"><input type="hidden" name="_token" value="${csrfToken}"><input type="hidden" name="tanggal" value="${dateStr}"><input type="hidden" name="shift_ke" value="${s}"><button class="btn-pick" type="submit">Ambil ${shiftCode}</button></form></div>`;
                }
            }

            html += `</div>`;
        }

        body.innerHTML = html;
        sheet.classList.add('show');
        sheet.setAttribute('aria-hidden', 'false');
    }

    function closeSheet() {
        const sheet = document.getElementById('actionSheet');
        sheet.classList.remove('show');
        sheet.setAttribute('aria-hidden', 'true');
    }
</script>
@endsection


