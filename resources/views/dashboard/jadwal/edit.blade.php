@extends('layouts.app')

@section('title', 'Edit Jadwal')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <div class="u-flex u-justify-between u-align-start u-gap-12 u-flex-wrap">
            <div>
                <h1>Edit Jadwal</h1>
                <p>Tanggal: <strong>{{ $tanggal }}</strong></p>
                <div class="hero-note">Full Time dan Part Time tetap diatur dalam satu layar. Badge dan kode shift membantu admin bedakan jam kerja tiap staf.</div>
            </div>
            
        </div>
    </div>

    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <form method="post" action="{{ route('dashboard.jadwal.update', ['tanggal' => $tanggal]) }}">
        @csrf

        <div class="admin-grid-secondary u-mt-14">
            @for($shift = 1; $shift <= $activeShiftCount; $shift++)
                @php
                    $selected = (array) old("shift_$shift", $existing[$shift] ?? []);
                    $selectedInt = array_map('intval', $selected);
                @endphp
                <div class="admin-soft-card">
                    <div class="u-flex u-justify-between u-gap-10 u-align-start u-flex-wrap">
                        <div>
                            <h3>Shift {{ $shift }}</h3>
                            <div class="sub">Centang karyawan untuk shift ini. Full Time tetap memakai shift biasa, Part Time otomatis ikut slot PT yang setara.</div>
                        </div>
                        <span class="tag shift{{ $shift }}">S{{ $shift }}</span>
                    </div>

                    <div class="tools">
                        <div class="u-flex u-gap-8 u-flex-wrap u-align-center">
                            <button class="btn-mini" type="button" data-action="select-all" data-shift="{{ $shift }}">Pilih Semua</button>
                            <button class="btn-mini" type="button" data-action="clear" data-shift="{{ $shift }}">Kosongkan</button>
                        </div>
                        <div class="tag" id="countShift{{ $shift }}">Terpilih: {{ count($selectedInt) }}</div>
                    </div>

                    <div class="field">
                        <label for="searchShift{{ $shift }}">Cari Karyawan</label>
                        <input id="searchShift{{ $shift }}" class="search" type="text" placeholder="Ketik nama atau jabatan..." data-shift="{{ $shift }}">
                        <div class="hint">Bisa cari cepat tanpa scroll panjang.</div>
                    </div>

                    <div class="list" data-list="{{ $shift }}">
                        @foreach($karyawan as $k)
                            @php
                                $kid = (int) $k->id_karyawan;
                                $isSel = in_array($kid, $selectedInt, true);
                                $isAbsen = in_array($kid, array_map('intval', $alreadyAbsen), true);
                                $label = trim((string) ($k->nama_karyawan ?? ''));
                                $jab = trim((string) ($k->jabatan ?? ''));
                                $typeLabel = method_exists($k, 'employmentTypeLabel') ? $k->employmentTypeLabel() : 'Full Time';
                                $typeShort = method_exists($k, 'employmentTypeShortLabel') ? $k->employmentTypeShortLabel() : 'FT';
                                $typeClass = ($k->employment_type ?? null) === \App\Models\Karyawan::EMPLOYMENT_PART_TIME ? 'pt' : 'ft';
                                $durationLabel = method_exists($k, 'employmentDurationLabel') ? $k->employmentDurationLabel() : '8 jam';
                                $shiftCodeLabel = isset($setting) ? $setting->shiftCodeFor($shift, $k->employment_type ?? null) : ('S' . $shift);
                                $shiftRangeLabel = isset($setting) ? $setting->shiftRangeLabel($shift, $k->employment_type ?? null, $tanggal) : '-';
                                $searchText = strtolower($label . ' ' . $jab . ' ' . $typeLabel . ' ' . $durationLabel);
                            @endphp
                            <label class="row" data-item="{{ $kid }}" data-search="{{ $searchText }}" data-shift="{{ $shift }}">
                                <input type="checkbox" name="shift_{{ $shift }}[]" value="{{ $kid }}" @checked($isSel)>
                                <div class="u-minw-0">
                                    <div class="name-line">
                                        <div class="name">{{ $label ?: '-' }}</div>
                                        <span class="employment-pill {{ $typeClass }}">{{ $typeShort }}</span>
                                    </div>
                                    <div class="meta">
                                        {{ $jab ?: 'Staff' }} &middot; {{ $typeLabel }} &middot; {{ $durationLabel }}
                                    </div>
                                    <div class="schedule-line">
                                        <span class="schedule-chip {{ $typeClass }}">{{ $shiftCodeLabel }}</span>
                                        <span class="meta">Jam kerja: {{ $shiftRangeLabel }}</span>
                                    </div>
                                </div>
                                <div class="row-aside">
                                    @if($isAbsen)
                                        <span class="tag warn">Sudah Absen</span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endfor
        </div>

        <div class="actions">
            <a class="btn-neutral" href="{{ route('dashboard.jadwal.index', ['bulan' => substr($tanggal, 0, 7)]) }}">Kembali</a>
            <button class="btn-primary" type="submit">Simpan</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const countForShift = (shift) => {
        const list = document.querySelector(`.list[data-list="${shift}"]`);
        if (!list) return 0;
        return list.querySelectorAll('input[type="checkbox"]:checked').length;
    };

    const updateCount = (shift) => {
        const el = document.getElementById(`countShift${shift}`);
        if (!el) return;
        el.textContent = `Terpilih: ${countForShift(shift)}`;
    };

    const applyFilter = (shift, q) => {
        const list = document.querySelector(`.list[data-list="${shift}"]`);
        if (!list) return;
        const query = String(q || '').trim().toLowerCase();
        list.querySelectorAll(`.row[data-shift="${shift}"]`).forEach((row) => {
            const hay = String(row.getAttribute('data-search') || '');
            row.style.display = !query || hay.includes(query) ? '' : 'none';
        });
    };

    document.querySelectorAll('.search[data-shift]').forEach((inp) => {
        inp.addEventListener('input', (e) => applyFilter(inp.getAttribute('data-shift'), inp.value));
    });

    document.addEventListener('change', (e) => {
        const cb = e.target;
        if (!(cb instanceof HTMLInputElement)) return;
        if (cb.type !== 'checkbox') return;
        const row = cb.closest('.row[data-shift]');
        if (!row) return;
        const shift = row.getAttribute('data-shift');
        updateCount(shift);
    });

    document.querySelectorAll('button[data-action][data-shift]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const action = btn.getAttribute('data-action');
            const shift = btn.getAttribute('data-shift');
            const list = document.querySelector(`.list[data-list="${shift}"]`);
            if (!list) return;

            const boxes = Array.from(list.querySelectorAll('input[type="checkbox"]'));
            if (action === 'select-all') {
                boxes.forEach((b) => b.checked = true);
            } else if (action === 'clear') {
                boxes.forEach((b) => b.checked = false);
            }
            updateCount(shift);
        });
    });

    // initial counts
    document.querySelectorAll('.list[data-list]').forEach((list) => updateCount(list.getAttribute('data-list')));
})();
</script>
@endsection


