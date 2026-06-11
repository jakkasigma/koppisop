@extends('layouts.app')

@section('title', 'Jadwal Shift')

@section('content')
<div class="container">
    @php
        $fullTimeCount = $karyawan->filter(fn ($item) => method_exists($item, 'employmentTypeValue')
            ? $item->employmentTypeValue() === \App\Models\Karyawan::EMPLOYMENT_FULL_TIME
            : true)->count();
        $partTimeCount = max(0, $karyawan->count() - $fullTimeCount);
    @endphp
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Operasional</div>
            <h1>Jadwal Shift</h1>
            <p>Atur jadwal per shift dan pantau komposisi Full Time/Part Time pada bulan terpilih.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip">Shift aktif: {{ (int) $activeShiftCount }}</span>
            <span class="admin-chip">Bulan: {{ $bulanLabel ?? $bulan }}</span>
            <span class="admin-chip soft">FT: {{ number_format((int) $fullTimeCount, 0, ',', '.') }}</span>
            <span class="admin-chip soft">PT: {{ number_format((int) $partTimeCount, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="panel toolbar">
        <form class="monthbar" method="get" action="{{ route('dashboard.jadwal.index') }}">
            <label>Bulan
                <input type="month" name="bulan" value="{{ $bulan }}">
            </label>
            <input type="hidden" name="mode" value="{{ $mode ?? 'calendar' }}">
            <button class="btn-primary" type="submit">Lihat</button>
            <a class="btn-neutral" href="{{ route('dashboard.jadwal.index', ['bulan' => now()->format('Y-m'), 'mode' => ($mode ?? 'calendar')]) }}">Bulan Ini</a>
            <a class="btn-shareqr" href="{{ route('dashboard.share_qr') }}">Share QR</a>
        </form>
    </div>

    @php
        $enabled = (bool) ($setting->self_schedule_enabled ?? false);
        $open = $enabled && (bool) ($setting->self_schedule_is_open ?? false);
        $statusClass = $enabled ? ($open ? 'ok' : 'warn') : '';
        $statusText = $enabled ? ($open ? 'DIBUKA' : 'DITUTUP') : 'NONAKTIF';
    @endphp

    <div class="panel krs-shortcut">
        <div>
            <h2 class="krs-title">Ambil Jadwal Mandiri</h2>
            <div class="krs-desc">Pengaturan fitur ambil jadwal mandiri dipindah ke halaman khusus karena fiturnya cukup kompleks.</div>
        </div>
        <div class="krs-actions">
            <span class="krs-status {{ $statusClass }}">{{ $statusText }}</span>
            <a class="btn-neutral" href="{{ route('dashboard.jadwal.self_schedule', ['return_bulan' => $bulan, 'return_mode' => ($mode ?? 'calendar')]) }}">Buka Pengaturan</a>
            <a class="btn-neutral" href="{{ route('dashboard.jadwal.swap_requests') }}">Permintaan Tukar</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="split">
        <div class="panel">
            <div class="panel-head">
                <h2>Jadwal Bulanan</h2>
                <span class="panel-sub">Mode: {{ ($mode ?? 'calendar') === 'list' ? 'List' : 'Kalender' }}</span>
            </div>

            <div class="modebar">
                <div class="left">
                    <div class="seg">
                        <a class="{{ ($mode ?? 'calendar') === 'calendar' ? 'active' : '' }}" href="{{ route('dashboard.jadwal.index', ['bulan' => $bulan, 'mode' => 'calendar']) }}">Kalender</a>
                        <a class="{{ ($mode ?? 'calendar') === 'list' ? 'active' : '' }}" href="{{ route('dashboard.jadwal.index', ['bulan' => $bulan, 'mode' => 'list']) }}">List</a>
                    </div>
                </div>
                    <div class="right">
                        <div class="legend">
                        <span class="lg"><span class="dot s1"></span> S1</span>
                        <span class="lg"><span class="dot s2"></span> S2</span>
                        @if($activeShiftCount >= 3)
                            <span class="lg"><span class="dot s3"></span> S3</span>
                        @endif
                        <span class="lg employment ft"><span class="etype ft">FT</span> Full Time</span>
                        <span class="lg employment pt"><span class="etype pt">PT</span> Part Time</span>
                        <span class="hinttext">Klik tanggal untuk edit</span>
                    </div>
                </div>
            </div>

            @if(($mode ?? 'calendar') === 'list')
                <div class="table-wrap u-mt-12">
                    <table>
                        <thead>
                            <tr>
                                <th class="u-w-120">Tanggal</th>
                                @for($shift = 1; $shift <= $activeShiftCount; $shift++)
                                    <th>Shift {{ $shift }}</th>
                                @endfor
                                <th class="u-w-110"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($days as $tgl)
                                @php
                                    $grp = $byTanggal[$tgl] ?? collect();
                                @endphp
                                <tr>
                                    <td><strong>{{ $tgl }}</strong></td>
                                    @for($shift = 1; $shift <= $activeShiftCount; $shift++)
                                        @php
                                            $items = $grp[$shift] ?? collect();
                                        @endphp
                                        <td>
                                            <div class="names">
                                                @forelse($items as $row)
                                                    @php
                                                        $employmentType = ($row->karyawan && method_exists($row->karyawan, 'employmentTypeValue'))
                                                            ? $row->karyawan->employmentTypeValue()
                                                            : \App\Models\Karyawan::EMPLOYMENT_FULL_TIME;
                                                        $employmentClass = $employmentType === \App\Models\Karyawan::EMPLOYMENT_PART_TIME ? 'pt' : 'ft';
                                                        $shiftCode = $setting->shiftCodeFor($shift, $employmentType);
                                                        $shiftRange = $setting->shiftRangeLabel($shift, $employmentType, $tgl);
                                                    @endphp
                                                    <span class="name-pill {{ $employmentClass }}">
                                                        <span class="name-pill-head">
                                                            <span class="etype {{ $employmentClass }}">{{ $row->karyawan?->employmentTypeShortLabel() ?? 'FT' }}</span>
                                                            <span class="name-main">{{ $row->karyawan?->nama_karyawan ?? '-' }}</span>
                                                        </span>
                                                        <small>{{ $shiftCode }} &middot; {{ $shiftRange }}</small>
                                                    </span>
                                                @empty
                                                    <span class="shift-badge">Kosong</span>
                                                @endforelse
                                            </div>
                                        </td>
                                    @endfor
                                    <td class="num">
                                        <a class="btn-neutral" href="{{ route('dashboard.jadwal.edit', ['tanggal' => $tgl]) }}">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @php
                    $startOfMonth = \Carbon\Carbon::parse($start)->startOfMonth();
                    $prevMonth = $startOfMonth->copy()->subMonth();
                    $nextMonth = $startOfMonth->copy()->addMonth();
                    $daysInMonth = (int) $startOfMonth->daysInMonth;
                    $daysInPrev = (int) $prevMonth->daysInMonth;
                    $firstIso = (int) $startOfMonth->isoWeekday(); // 1=Mon..7=Sun
                    $leading = max(0, $firstIso - 1);
                    $totalCells = $leading + $daysInMonth;
                    $trailing = (7 - ($totalCells % 7)) % 7;
                    $cellCount = $totalCells + $trailing;
                    $weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
                @endphp

                <div class="cal-wrap">
                    <div class="cal-head">
                        @foreach($weekdays as $w)
                            <div>{{ $w }}</div>
                        @endforeach
                    </div>
                    <div class="cal-grid">
                        @for($i = 0; $i < $cellCount; $i++)
                            @php
                                $dayNo = $i - $leading + 1;
                            @endphp
                            @if($dayNo < 1 || $dayNo > $daysInMonth)
                                @php
                                    if ($dayNo < 1) {
                                        $showDay = $daysInPrev + $dayNo;
                                        $showDate = $prevMonth->copy()->day($showDay);
                                    } else {
                                        $showDay = $dayNo - $daysInMonth;
                                        $showDate = $nextMonth->copy()->day($showDay);
                                    }
                                    $showMeta = strtoupper($showDate->format('M'));
                                @endphp
                                <div class="day muted" title="{{ $showDate->toDateString() }}">
                                    <div class="top">
                                        <div>
                                            <div class="num">{{ (int) $showDay }}</div>
                                            <div class="meta">{{ $showMeta }}</div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                 @php
                                     $tgl = $startOfMonth->copy()->day($dayNo)->toDateString();
                                     $grp = $byTanggal[$tgl] ?? collect();
                                     $iso = (int) $startOfMonth->copy()->day($dayNo)->isoWeekday();
                                     $isWeekend = $iso >= 6;
                                     $isToday = $tgl === now()->toDateString();
                                     $rosterByShift = [];
                                     $staffCount = 0;
                                     for ($s = 1; $s <= $activeShiftCount; $s++) {
                                         $items = $grp[$s] ?? collect();
                                         $members = [];
                                         foreach ($items as $it) {
                                             $employmentType = ($it->karyawan && method_exists($it->karyawan, 'employmentTypeValue'))
                                                 ? $it->karyawan->employmentTypeValue()
                                                 : \App\Models\Karyawan::EMPLOYMENT_FULL_TIME;
                                             $members[] = [
                                                 'name' => (string) ($it->karyawan?->nama_karyawan ?? '-'),
                                                 'job' => (string) ($it->karyawan?->jabatan ?? 'Staff'),
                                                 'type_label' => method_exists($it->karyawan, 'employmentTypeLabel')
                                                     ? $it->karyawan->employmentTypeLabel()
                                                     : 'Full Time',
                                                 'type_short' => method_exists($it->karyawan, 'employmentTypeShortLabel')
                                                     ? $it->karyawan->employmentTypeShortLabel()
                                                     : 'FT',
                                                 'type_class' => $employmentType === \App\Models\Karyawan::EMPLOYMENT_PART_TIME ? 'pt' : 'ft',
                                                 'shift_code' => $setting->shiftCodeFor($s, $employmentType),
                                                 'shift_range' => $setting->shiftRangeLabel($s, $employmentType, $tgl),
                                             ];
                                         }
                                         $rosterByShift[$s] = $members;
                                         $staffCount += count($members);
                                     }

                                     // Hover tooltip (non-click) to reveal full roster without blocking click-to-edit.
                                     $tip = "Edit jadwal {$tgl}";
                                     for ($s = 1; $s <= $activeShiftCount; $s++) {
                                         $members = $rosterByShift[$s] ?? [];
                                         $list = count($members) > 0
                                             ? implode(', ', array_map(
                                                 fn ($member) => ($member['name'] ?? '-') . ' [' . ($member['type_short'] ?? 'FT') . ' | ' . ($member['shift_code'] ?? ('S' . $s)) . ' | ' . ($member['shift_range'] ?? '-') . ']',
                                                 $members
                                             ))
                                             : '-';
                                         $tip .= "\nS{$s} (" . count($members) . "): " . $list;
                                     }
                                 @endphp
                                 <a
                                     class="day day-link {{ $isWeekend ? 'weekend' : '' }} {{ $isToday ? 'today' : '' }}"
                                     href="{{ route('dashboard.jadwal.edit', ['tanggal' => $tgl]) }}"
                                     title="{{ $tip }}"
                                     data-date="{{ $tgl }}"
                                     data-edit-url="{{ route('dashboard.jadwal.edit', ['tanggal' => $tgl]) }}"
                                     data-roster='@json($rosterByShift)'
                                     data-active-shifts="{{ (int) $activeShiftCount }}"
                                 >
                                     <div class="top">
                                         <div>
                                             <div class="num">{{ $dayNo }}</div>
                                             <div class="meta">{{ $weekdays[$iso - 1] ?? '' }}</div>
                                         </div>
                                         <span class="count-pill">{{ $staffCount }} org</span>
                                     </div>
                                     <div class="shifts">
                                         @for($shift = 1; $shift <= $activeShiftCount; $shift++)
                                             @php
                                                 $members = $rosterByShift[$shift] ?? [];
                                                 $max = 2;
                                             @endphp
                                             <div class="shift-row s{{ $shift }}">
                                                 <div class="slabel s{{ $shift }}">S{{ $shift }}</div>
                                                <div class="scontent">
                                                    @if(count($members) === 0)
                                                        <span class="chip empty"><span>-</span></span>
                                                    @else
                                                        @for($n = 0; $n < min($max, count($members)); $n++)
                                                            <span class="chip staff {{ $members[$n]['type_class'] ?? 'ft' }}">
                                                                <span class="chip-head">
                                                                    <span class="etype {{ $members[$n]['type_class'] ?? 'ft' }}">{{ $members[$n]['type_short'] ?? 'FT' }}</span>
                                                                    <span class="chip-name">{{ $members[$n]['name'] ?? '-' }}</span>
                                                                </span>
                                                                <span class="chip-meta">{{ $members[$n]['shift_code'] ?? ('S' . $shift) }} &middot; {{ $members[$n]['shift_range'] ?? '-' }}</span>
                                                            </span>
                                                        @endfor
                                                        @if(count($members) > $max)
                                                            <span class="chip more"><span>+{{ count($members) - $max }}</span></span>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                        @endfor
                                    </div>
                                </a>
                            @endif
                        @endfor
                    </div>
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Rekap Karyawan</h2>
                <div class="panel-tools">
                    <input id="rekap-search" class="search" type="search" placeholder="Cari karyawan..." autocomplete="off">
                    <span class="panel-sub">Bulan {{ $bulanLabel ?? $bulan }}</span>
                </div>
            </div>

            <div class="table-wrap rekap-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Karyawan</th>
                            <th class="num">Jadwal</th>
                            <th class="num">Absen</th>
                            <th class="num">Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($karyawan as $k)
                            @php
                                $r = $rekap[(int) $k->id_karyawan] ?? null;
                                $jadwal = (int) ($r->total_jadwal ?? 0);
                                $absen = (int) ($r->total_absen ?? 0);
                                $sisa = max(0, $jadwal - $absen);
                                $pct = $jadwal > 0 ? round(($absen / $jadwal) * 100, 1) : 0;
                                $namaKey = strtolower(trim((string) $k->nama_karyawan));
                                $jabKey = strtolower(trim((string) ($k->jabatan ?? '')));
                                $employmentType = method_exists($k, 'employmentTypeValue')
                                    ? $k->employmentTypeValue()
                                    : \App\Models\Karyawan::EMPLOYMENT_FULL_TIME;
                                $employmentClass = $employmentType === \App\Models\Karyawan::EMPLOYMENT_PART_TIME ? 'pt' : 'ft';
                            @endphp
                            <tr class="emp-row" data-key="{{ $namaKey }} {{ $jabKey }} {{ strtolower((string) ($k->employmentTypeLabel() ?? '')) }}">
                                <td>
                                    <strong>{{ $k->nama_karyawan }}</strong>
                                    <div class="employee-meta">
                                        <span class="etype {{ $employmentClass }}">{{ method_exists($k, 'employmentTypeShortLabel') ? $k->employmentTypeShortLabel() : 'FT' }}</span>
                                        <span>{{ $k->jabatan ?: 'Staff' }}</span>
                                        <span>&middot;</span>
                                        <span>{{ method_exists($k, 'employmentSummaryLabel') ? $k->employmentSummaryLabel() : 'Full Time - 8 jam' }}</span>
                                    </div>
                                </td>
                                <td class="num">{{ $jadwal }}</td>
                                <td class="num">{{ $absen }}</td>
                                <td class="num"><span class="{{ $sisa > 0 ? 'sync-pill warn' : 'sync-pill ok' }}">{{ $sisa }}</span></td>
                            </tr>
                            <tr class="emp-detail">
                                <td colspan="4" class="u-pt-0">
                                    <div class="rekap-bar"><span style="width:{{ $pct }}%"></span></div>
                                    <div class="rekap-meta">
                                        <div class="muted u-text-sm">Progress absensi bulan ini</div>
                                        <div class="pct">{{ number_format((float) $pct, 1, ',', '.') }}%</div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="muted u-mt-10">
                Catatan: Absen dihitung hanya pada tanggal yang ada jadwalnya.
            </div>
        </div>
    </div>

    <div id="rosterModal" class="roster-modal" aria-hidden="true">
        <div class="roster-sheet" role="dialog" aria-modal="true" aria-label="Detail jadwal">
            <div class="roster-head">
                <div>
                    <div class="roster-title" id="rosterTitle">Detail Jadwal</div>
                    <div class="roster-sub" id="rosterSub">Tekan Edit untuk mengubah jadwal.</div>
                </div>
                <button class="roster-x" type="button" id="rosterClose" aria-label="Tutup">X</button>
            </div>
            <div class="roster-body" id="rosterBody"></div>
            <div class="roster-actions">
                <button class="btn-neutral" type="button" id="rosterClose2">Tutup</button>
                <a class="btn-primary" href="#" id="rosterEditLink">Edit</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const input = document.getElementById('rekap-search');
    if (!input) return;

    const rows = Array.from(document.querySelectorAll('tr.emp-row'));
    const details = Array.from(document.querySelectorAll('tr.emp-detail'));

    // Each employee uses 2 rows: data row then detail row.
    const pairs = rows.map((r, idx) => ({ row: r, detail: details[idx] || null }));

    const apply = () => {
        const q = String(input.value || '').trim().toLowerCase();
        for (const p of pairs) {
            const key = String(p.row.getAttribute('data-key') || '');
            const show = q === '' ? true : key.indexOf(q) !== -1;
            p.row.style.display = show ? '' : 'none';
            if (p.detail) p.detail.style.display = show ? '' : 'none';
        }
    };

    input.addEventListener('input', apply);
})();

(function () {
    const modal = document.getElementById('rosterModal');
    const body = document.getElementById('rosterBody');
    const title = document.getElementById('rosterTitle');
    const editLink = document.getElementById('rosterEditLink');
    const closeBtn = document.getElementById('rosterClose');
    const closeBtn2 = document.getElementById('rosterClose2');
    if (!modal || !body || !title || !editLink || !closeBtn || !closeBtn2) return;

    const open = (payload) => {
        title.textContent = `Detail Jadwal ${payload.date}`;
        editLink.setAttribute('href', payload.editUrl || '#');
        body.innerHTML = '';

        const active = Math.max(1, Number(payload.activeShifts || 1));
        for (let s = 1; s <= active; s++) {
            const members = (payload.roster && payload.roster[String(s)]) ? payload.roster[String(s)] : [];
            const block = document.createElement('div');
            block.className = 'roster-block';

            const h = document.createElement('div');
            h.className = 'h';

            const left = document.createElement('div');
            left.className = 'left';

            const badge = document.createElement('span');
            badge.className = `roster-badge s${s}`;
            badge.textContent = `S${s}`;
            left.appendChild(badge);

            const cnt = document.createElement('span');
            cnt.className = 'roster-count';
            cnt.textContent = `${members.length} org`;
            left.appendChild(cnt);

            h.appendChild(left);
            block.appendChild(h);

            const namesWrap = document.createElement('div');
            namesWrap.className = 'roster-names';
            if (!members.length) {
                const em = document.createElement('span');
                em.className = 'roster-name';
                em.innerHTML = '<span>-</span>';
                namesWrap.appendChild(em);
            } else {
                for (const member of members) {
                    const typeClass = member && member.type_class ? String(member.type_class) : 'ft';
                    const chip = document.createElement('div');
                    chip.className = `roster-name-card ${typeClass}`;

                    const top = document.createElement('div');
                    top.className = 'roster-name-top';

                    const type = document.createElement('span');
                    type.className = `etype ${typeClass}`;
                    type.textContent = member && member.type_short ? String(member.type_short) : 'FT';
                    top.appendChild(type);

                    const name = document.createElement('span');
                    name.className = 'roster-name-title';
                    name.textContent = member && member.name ? String(member.name) : '-';
                    top.appendChild(name);
                    chip.appendChild(top);

                    const meta = document.createElement('div');
                    meta.className = 'roster-name-meta';
                    const metaParts = [];
                    if (member && member.job) metaParts.push(String(member.job));
                    if (member && member.shift_code) metaParts.push(String(member.shift_code));
                    if (member && member.shift_range) metaParts.push(String(member.shift_range));
                    meta.textContent = metaParts.join(' • ');
                    chip.appendChild(meta);
                    namesWrap.appendChild(chip);
                }
            }
            block.appendChild(namesWrap);
            body.appendChild(block);
        }

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const close = () => {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    closeBtn.addEventListener('click', close);
    closeBtn2.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

    const LONG_MS = 480;
    const MOVE_PX = 10;

    const links = Array.from(document.querySelectorAll('.day-link[data-roster][data-date]'));
    for (const el of links) {
        let t = null;
        let startX = 0;
        let startY = 0;
        let fired = false;

        const clear = () => {
            if (t) window.clearTimeout(t);
            t = null;
        };

        el.addEventListener('click', (e) => {
            if (el.dataset.suppressClick === '1') {
                e.preventDefault();
                e.stopPropagation();
                el.dataset.suppressClick = '0';
            }
        }, true);

        el.addEventListener('touchstart', (e) => {
            fired = false;
            clear();
            const touch = e.touches && e.touches[0] ? e.touches[0] : null;
            startX = touch ? touch.clientX : 0;
            startY = touch ? touch.clientY : 0;

            t = window.setTimeout(() => {
                fired = true;
                el.dataset.suppressClick = '1';

                let roster = null;
                try { roster = JSON.parse(el.getAttribute('data-roster') || '{}'); } catch (_e) { roster = {}; }
                open({
                    date: el.getAttribute('data-date') || '',
                    editUrl: el.getAttribute('data-edit-url') || '',
                    activeShifts: el.getAttribute('data-active-shifts') || '1',
                    roster,
                });
            }, LONG_MS);
        }, { passive: true });

        el.addEventListener('touchmove', (e) => {
            const touch = e.touches && e.touches[0] ? e.touches[0] : null;
            if (!touch) return;
            const dx = Math.abs(touch.clientX - startX);
            const dy = Math.abs(touch.clientY - startY);
            if (dx > MOVE_PX || dy > MOVE_PX) clear();
        }, { passive: true });

        el.addEventListener('touchend', () => {
            clear();
            if (fired) {
                // On iOS/Android, a click may still happen after longpress; suppress via dataset flag.
                window.setTimeout(() => { el.dataset.suppressClick = '0'; }, 1200);
            }
        }, { passive: true });

        el.addEventListener('touchcancel', clear, { passive: true });
        el.addEventListener('contextmenu', (e) => {
            if (modal.classList.contains('open')) e.preventDefault();
        });
    }
 })();
</script>
@endsection




