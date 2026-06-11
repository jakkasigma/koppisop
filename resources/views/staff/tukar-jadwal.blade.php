@extends('layouts.staff')

@section('title', 'Tukar Jadwal')

@section('content')
<div class="container app-shell">
    @php
        $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
        $employmentType = (string) ($employmentType ?? ($staffKaryawan->employment_type ?? null));
        $employmentLabel = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
        $durasiKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentDurationLabel')) ? $staffKaryawan->employmentDurationLabel() : '8 jam';
        $shiftCode = fn (int $shift) => $setting->shiftCodeFor($shift, $employmentType);
        $shiftRange = fn (int $shift, ?string $date = null) => $setting->shiftRangeLabel($shift, $employmentType, $date);
        $staffMap = collect($staffList ?? [])->keyBy('id_karyawan');
        $scheduleReady = collect($mySchedules ?? [])->filter(function ($row) use ($today, $minDays, $minTarget) {
            $tgl = $row->tanggal?->format('Y-m-d') ?? '';
            $isPast = $tgl !== '' && $tgl <= $today;
            $isTooSoon = ($minDays ?? 0) > 0 && $tgl !== '' && $tgl < $minTarget;
            return ! $isPast && ! $isTooSoon;
        })->count();
        $swapTotal = (int) collect($swapRequests ?? [])->count();
        $swapWaitingYou = collect($swapRequests ?? [])->filter(function ($req) use ($staffKaryawan) {
            return (int) ($req->to_karyawan_id ?? 0) === (int) ($staffKaryawan->id_karyawan ?? 0)
                && (string) ($req->status ?? '') === 'pending'
                && (string) ($req->staff_status ?? 'pending') === 'pending';
        })->count();
        $swapWaitingAdmin = collect($swapRequests ?? [])->filter(fn ($req) => (string) ($req->status ?? '') === 'pending' && (string) ($req->staff_status ?? '') === 'approved')->count();
        $swapFinished = collect($swapRequests ?? [])->filter(fn ($req) => in_array((string) ($req->status ?? ''), ['approved', 'rejected'], true))->count();
    @endphp
    <div class="staff-mobile-page-screen">
        <section class="staff-mobile-page-stage">
            @include('staff.partials.mobile-page-header', [
                'pageTitle' => 'Tukar Shift',
                'pageMark' => 'SW',
                'staffName' => (string) ($karyawan->nama_karyawan ?? 'Karyawan'),
                'greetingTitle' => 'Halo, ' . (string) ($karyawan->nama_karyawan ?? 'Karyawan'),
                'greetingSubtitle' => 'Ajukan pertukaran jadwal lalu pantau jawabannya dari satu halaman.',
                'employmentLabel' => $employmentLabel,
                'employmentMeta' => trim((string) ($karyawan->jabatan ?? 'Staff')) . ' • ' . $durasiKerja,
            ])

            <article class="staff-mobile-page-summary-card">
                <div class="staff-mobile-page-summary-topline">
                    <div class="staff-mobile-page-summary-period">
                        <span class="staff-mobile-page-summary-label">Pertukaran</span>
                        <strong>{{ $swapTotal }} Permintaan</strong>
                    </div>
                    <span class="staff-mobile-page-pill">{{ $enabled ? 'Aktif' : 'Nonaktif' }}</span>
                </div>

                <div class="staff-mobile-page-summary-stats">
                    <article>
                        <span>Bisa Diajukan</span>
                        <strong>{{ $scheduleReady }}</strong>
                    </article>
                    <article>
                        <span>Menunggu Kamu</span>
                        <strong>{{ $swapWaitingYou }}</strong>
                    </article>
                    <article>
                        <span>Menunggu Admin</span>
                        <strong>{{ $swapWaitingAdmin }}</strong>
                    </article>
                    <article>
                        <span>Selesai</span>
                        <strong>{{ $swapFinished }}</strong>
                    </article>
                </div>

                <div class="staff-mobile-page-summary-note">
                    @if($rangeStart && $rangeEnd)
                        Periode aktif: {{ $rangeStart }} s/d {{ $rangeEnd }}.
                    @endif
                    @if(($minDays ?? 0) > 0)
                        Minimal pengajuan H-{{ (int) $minDays }} sebelum tanggal shift.
                    @endif
                </div>

                <div class="staff-mobile-page-summary-actions">
                    <a class="btn-neutral" href="{{ route('staff.home') }}">Kembali</a>
                    <a class="btn-primary" href="{{ route('staff.history') }}">Riwayat Lengkap</a>
                </div>
            </article>
        </section>

        <div class="panel swap-info-panel">
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

        @if(! $enabled)
            <div class="sub">Fitur tukar jadwal belum diaktifkan admin.</div>
        @else
            <div class="sub">Pilih salah satu jadwalmu di bawah, lalu tentukan tanggal, shift, dan staff tujuan yang ingin diajak bertukar.</div>
        @endif
        </div>

        <div class="panel swap-schedule-panel">
        <div class="swap-section-head">
            <div>
                <h2 class="swap-section-title">Jadwal yang Bisa Ditukar</h2>
                <div class="swap-section-sub">Hanya jadwal yang belum lewat dan tidak terkunci aturan H-minimum yang bisa diajukan.</div>
            </div>
            <span class="pill gray">{{ $scheduleReady }} siap diajukan</span>
        </div>

        <div class="swap-list">
            @forelse($mySchedules as $row)
                @php
                    $tgl = $row->tanggal?->format('Y-m-d') ?? '';
                    $shift = (int) ($row->shift_ke ?? 0);
                    $isPast = $tgl !== '' && $tgl <= $today;
                    $isTooSoon = ($minDays ?? 0) > 0 && $tgl !== '' && $tgl < $minTarget;
                    $canSwap = $enabled && ! $isPast && ! $isTooSoon;
                @endphp
                <div class="swap-card">
                    <div class="swap-head">
                        <div>
                            <div class="swap-date">{{ $tgl }}</div>
                            <div class="swap-meta">{{ $shiftCode($shift) }} • {{ $shiftRange($shift, $tgl) }}  -  {{ $isPast ? 'Tanggal lewat' : ($isTooSoon ? 'Terkunci aturan H-minimum' : 'Bisa diajukan') }}</div>
                        </div>
                        <div class="u-flex u-gap-6 u-wrap">
                            <span class="pill {{ $canSwap ? 'ok' : 'warn' }}">{{ $shiftCode($shift) }}</span>
                            @if($isTooSoon)
                                <span class="pill warn">Terkunci H-{{ (int) $minDays }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="swap-trigger">
                        <button
                            class="btn-pick primary"
                            type="button"
                            data-swap-open
                            data-date="{{ $tgl }}"
                            data-shift="{{ $shift }}"
                            @disabled(! $canSwap)
                        >
                            Ajukan Tukar
                        </button>
                        <span class="swap-hint">Setelah staff tujuan setuju, permintaan otomatis lanjut ke admin.</span>
                    </div>

                    @if($isPast)
                        <div class="sub">Tanggal ini sudah lewat atau sedang berjalan, jadi tidak bisa ditukar lagi.</div>
                    @elseif($isTooSoon)
                        <div class="sub">Permintaan baru bisa dibuat mulai {{ $minTarget }} agar tetap memenuhi aturan minimal H-{{ (int) $minDays }}.</div>
                    @endif
                </div>
            @empty
                <div class="history-empty">
                    <div class="history-empty-title">Belum ada jadwal untuk ditukar</div>
                    <div class="history-empty-sub">Kalau jadwalmu nanti sudah masuk sistem, daftar tanggalnya akan tampil di sini.</div>
                </div>
            @endforelse
        </div>
        </div>

        <div class="panel swap-history-panel" id="swap-history">
        <div class="swap-section-head">
            <div>
                <h2 class="swap-section-title">Riwayat Permintaan Tukar</h2>
                <div class="swap-section-sub">Pantau status permintaan dan jawab langsung kalau ada permintaan yang menunggu keputusanmu.</div>
            </div>
            <span class="pill gray">{{ $swapTotal }} permintaan</span>
        </div>

        @if(isset($swapRequests) && $swapRequests->count() > 0)
            <div class="swap-history-cards">
                @foreach($swapRequests as $req)
                    @php
                        $adminStatus = strtolower((string) ($req->status ?? 'pending'));
                        $staffStatus = strtolower((string) ($req->staff_status ?? 'pending'));
                        $isTarget = (int) ($req->to_karyawan_id ?? 0) === (int) ($karyawan->id_karyawan ?? 0);
                        $statusLabel = 'MENUNGGU STAFF';
                        $statusClass = 'warn';
                        $statusDesc = 'Menunggu jawaban staff tujuan.';
                        if ($adminStatus === 'approved') {
                            $statusLabel = 'DISETUJUI ADMIN';
                            $statusClass = 'ok';
                            $statusDesc = 'Jadwal resmi ditukar oleh admin.';
                        } elseif ($adminStatus === 'rejected') {
                            $statusLabel = 'DITOLAK ADMIN';
                            $statusClass = 'bad';
                            $statusDesc = 'Permintaan berhenti di tahap admin.';
                        } else {
                            if ($staffStatus === 'approved') {
                                $statusLabel = 'MENUNGGU ADMIN';
                                $statusClass = 'warn';
                                $statusDesc = 'Staff tujuan sudah setuju.';
                            } elseif ($staffStatus === 'rejected') {
                                $statusLabel = 'DITOLAK STAFF';
                                $statusClass = 'bad';
                                $statusDesc = 'Staff tujuan menolak permintaan ini.';
                            } elseif ($isTarget) {
                                $statusLabel = 'MENUNGGU KAMU';
                                $statusClass = 'warn';
                                $statusDesc = 'Kamu perlu memberi jawaban sekarang.';
                            }
                        }
                        $fromName = $staffMap->get((int) ($req->from_karyawan_id ?? 0))->nama_karyawan ?? 'Staff';
                        $toName = $staffMap->get((int) ($req->to_karyawan_id ?? 0))->nama_karyawan ?? 'Staff';
                    @endphp
                    <div class="swap-history-card">
                        <div class="swap-head">
                            <div>
                                <div class="swap-date">{{ $req->from_tanggal?->format('Y-m-d') ?? ($req->tanggal?->format('Y-m-d') ?? '-') }}</div>
                                <div class="swap-meta">{{ $shiftCode((int) ($req->from_shift ?? 0)) }} ke {{ $req->to_tanggal?->format('Y-m-d') ?? '-' }}  -  {{ $shiftCode((int) ($req->to_shift ?? 0)) }}</div>
                            </div>
                            <span class="pill {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>
                        <div class="swap-history-note">Pemohon: {{ $fromName }}  -  Target: {{ $toName }}</div>
                        <div class="status-desc">{{ $statusDesc }}</div>
                        <div class="swap-history-actions">
                            <a class="btn-neutral btn-mini" href="{{ route('staff.messages.show', ['type' => 'swap', 'id' => $req->id]) }}">Buka Pesan</a>
                            @if((int) ($req->to_karyawan_id ?? 0) === (int) ($karyawan->id_karyawan ?? 0) && (string) $req->status === 'pending' && (string) ($req->staff_status ?? 'pending') === 'pending')
                                <form method="post" action="{{ route('staff.self_schedule.swap.approve', $req) }}">
                                    @csrf
                                    <input type="text" name="note" placeholder="Catatan (opsional)">
                                    <button class="btn-pick primary" type="submit">Setujui</button>
                                </form>
                                <form method="post" action="{{ route('staff.self_schedule.swap.reject', $req) }}">
                                    @csrf
                                    <input type="text" name="note" placeholder="Alasan (opsional)">
                                    <button class="btn-pick" type="submit">Tolak</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th class="u-w-190">Pertukaran</th>
                            <th>Staff</th>
                            <th class="u-w-220">Status</th>
                            <th class="u-w-120">Pesan</th>
                            <th class="u-w-220">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($swapRequests as $req)
                        @php
                            $adminStatus = strtolower((string) ($req->status ?? 'pending'));
                            $staffStatus = strtolower((string) ($req->staff_status ?? 'pending'));
                            $isTarget = (int) ($req->to_karyawan_id ?? 0) === (int) ($karyawan->id_karyawan ?? 0);
                            $statusLabel = 'MENUNGGU STAFF';
                            $statusClass = 'warn';
                            $statusDesc = 'Menunggu jawaban staff tujuan.';
                            if ($adminStatus === 'approved') {
                                $statusLabel = 'DISETUJUI ADMIN';
                                $statusClass = 'ok';
                                $statusDesc = 'Jadwal resmi ditukar oleh admin.';
                            } elseif ($adminStatus === 'rejected') {
                                $statusLabel = 'DITOLAK ADMIN';
                                $statusClass = 'bad';
                                $statusDesc = 'Permintaan berhenti di tahap admin.';
                            } else {
                                if ($staffStatus === 'approved') {
                                    $statusLabel = 'MENUNGGU ADMIN';
                                    $statusClass = 'warn';
                                    $statusDesc = 'Staff tujuan sudah setuju.';
                                } elseif ($staffStatus === 'rejected') {
                                    $statusLabel = 'DITOLAK STAFF';
                                    $statusClass = 'bad';
                                    $statusDesc = 'Staff tujuan menolak permintaan ini.';
                                } elseif ($isTarget) {
                                    $statusLabel = 'MENUNGGU KAMU';
                                    $statusClass = 'warn';
                                    $statusDesc = 'Kamu perlu memberi jawaban sekarang.';
                                }
                            }
                            $fromName = $staffMap->get((int) ($req->from_karyawan_id ?? 0))->nama_karyawan ?? 'Staff';
                            $toName = $staffMap->get((int) ($req->to_karyawan_id ?? 0))->nama_karyawan ?? 'Staff';
                        @endphp
                        <tr>
                            <td>
                                <div class="history-table-title">{{ $req->from_tanggal?->format('Y-m-d') ?? ($req->tanggal?->format('Y-m-d') ?? '-') }}  -  {{ $shiftCode((int) ($req->from_shift ?? 0)) }}</div>
                                <div class="history-table-sub">Target: {{ $req->to_tanggal?->format('Y-m-d') ?? '-' }}  -  {{ $shiftCode((int) ($req->to_shift ?? 0)) }}</div>
                            </td>
                            <td>{{ $fromName }} ke {{ $toName }}</td>
                            <td>
                                <div class="status-wrap">
                                    <span class="pill {{ $statusClass }}">{{ $statusLabel }}</span>
                                    <div class="status-desc">{{ $statusDesc }}</div>
                                </div>
                            </td>
                            <td>
                                <a class="btn-neutral btn-mini" href="{{ route('staff.messages.show', ['type' => 'swap', 'id' => $req->id]) }}">Buka</a>
                            </td>
                            <td>
                                @if((int) ($req->to_karyawan_id ?? 0) === (int) ($karyawan->id_karyawan ?? 0) && (string) $req->status === 'pending' && (string) ($req->staff_status ?? 'pending') === 'pending')
                                    <div class="swap-form u-mt-0">
                                        <form method="post" action="{{ route('staff.self_schedule.swap.approve', $req) }}">
                                            @csrf
                                            <input type="text" name="note" placeholder="Catatan (opsional)">
                                            <button class="btn-pick primary" type="submit">Setujui</button>
                                        </form>
                                        <form method="post" action="{{ route('staff.self_schedule.swap.reject', $req) }}">
                                            @csrf
                                            <input type="text" name="note" placeholder="Alasan (opsional)">
                                            <button class="btn-pick" type="submit">Tolak</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="sub">Tidak ada aksi</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="history-empty">
                <div class="history-empty-title">Belum ada permintaan tukar</div>
                <div class="history-empty-sub">Nanti semua riwayat pertukaran akan tampil di bagian ini.</div>
            </div>
        @endif
        </div>
    </div>
</div>

<div class="swap-modal" id="swapModal" aria-hidden="true">
    <div class="swap-card-modal" role="dialog" aria-modal="true">
        <div class="swap-modal-head">
            <div>
                <h3>Ajukan Tukar Shift</h3>
                <div class="swap-info" id="swapInfo">Jadwal kamu:</div>
            </div>
            <button class="btn-neutral btn-mini" type="button" data-swap-close>Tutup</button>
        </div>
        <form id="swapForm" method="post" action="{{ route('staff.self_schedule.swap') }}">
            @csrf
            <input type="hidden" name="tanggal" value="">
            <div class="swap-modal-body">
                <div>
                    <label class="sub u-block u-mb-6">Tanggal target</label>
                    <input type="date" name="target_tanggal" required min="{{ $minTarget ?? now()->addDay()->toDateString() }}">
                    <div class="swap-hint">
                        @if(($minDays ?? 0) > 0)
                            Saran tercepat yang bisa dipilih: {{ $minTarget }}.
                        @else
                            Kamu bisa mulai memilih dari tanggal besok.
                        @endif
                    </div>
                </div>
                <div>
                    <label class="sub u-block u-mb-6">{{ $employmentType === \App\Models\Karyawan::EMPLOYMENT_PART_TIME ? 'Slot target' : 'Shift target' }}</label>
                    <select name="target_shift" required></select>
                </div>
                <div>
                    <label class="sub u-block u-mb-6">Staff target</label>
                    <select name="target_karyawan_id" required>
                        <option value="">Pilih staff...</option>
                    </select>
                    <div class="swap-hint" id="swapHint">Pilih tanggal dan {{ $employmentType === \App\Models\Karyawan::EMPLOYMENT_PART_TIME ? 'slot' : 'shift' }} target dulu.</div>
                </div>
                <div>
                    <label class="sub u-block u-mb-6">Pesan untuk Staff</label>
                    <input type="text" name="pesan" placeholder="Contoh: boleh tukar jadwal ini?">
                </div>
            </div>
            <div class="swap-modal-actions">
                <button class="btn-pick" type="button" data-swap-close>Batal</button>
                <button class="btn-pick primary" type="submit">Kirim Permintaan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('swapModal');
        const modalInfo = document.getElementById('swapInfo');
        const form = document.getElementById('swapForm');
        const baseUrl = '/staff/tukar-jadwal/available';
        const minTarget = @json($minTarget ?? now()->addDay()->toDateString());
        const activeShiftCount = @json((int) ($activeShiftCount ?? 2));

        const dateInput = form ? form.querySelector('input[name="target_tanggal"]') : null;
        const shiftSelect = form ? form.querySelector('select[name="target_shift"]') : null;
        const shiftCodes = {{ Illuminate\Support\Js::from([
            1 => $shiftCode(1),
            2 => $shiftCode(2),
            3 => $shiftCode(3),
        ]) }};
        const staffSelect = form ? form.querySelector('select[name="target_karyawan_id"]') : null;
        const pesanInput = form ? form.querySelector('input[name="pesan"]') : null;
        const tanggalInput = form ? form.querySelector('input[name="tanggal"]') : null;
        const hint = form ? form.querySelector('#swapHint') : null;
        const fallbackOptions = staffSelect ? staffSelect.innerHTML : '';

        let itemsAll = [];
        let lastMessage = '';
        let myShiftsOnDate = [];

        const renderOptions = (items, message) => {
            if (!staffSelect) return;

            staffSelect.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = items.length > 0 ? 'Pilih staff...' : (message || 'Tidak ada staff terjadwal');
            staffSelect.appendChild(placeholder);

            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.id;
                const jabatan = item.jabatan ? ` - ${item.jabatan}` : '';
                const shiftLabel = item.shift ? ` - ${shiftCodes[item.shift] || ('S' + item.shift)}` : '';
                option.textContent = `${item.nama}${jabatan}${shiftLabel}`;
                staffSelect.appendChild(option);
            });
        };

        const updateShiftOptions = (shifts) => {
            if (!shiftSelect) return;

            const current = Number(shiftSelect.value || 0);
            const list = Array.isArray(shifts) && shifts.length > 0
                ? shifts
                : Array.from({ length: Math.max(1, activeShiftCount) }, (_, index) => index + 1);
            const filtered = list.filter((shift) => !myShiftsOnDate.includes(shift));

            shiftSelect.innerHTML = '';
            if (filtered.length === 0) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Tidak ada slot tersedia';
                shiftSelect.appendChild(option);
                if (hint) hint.textContent = 'Kamu sudah punya semua slot di tanggal target.';
                return;
            }

            filtered.forEach((shift) => {
                const option = document.createElement('option');
                option.value = shift;
                option.textContent = shiftCodes[shift] || `Shift ${shift}`;
                if (shift === current) option.selected = true;
                shiftSelect.appendChild(option);
            });

            if (!filtered.includes(current)) {
                shiftSelect.value = String(filtered[0] || '');
            }
        };

        const applyShiftFilter = () => {
            const selected = Number(shiftSelect?.value || 0);
            if (myShiftsOnDate.includes(selected)) {
                const message = 'Kamu sudah punya slot ini di tanggal target.';
                renderOptions([], message);
                if (hint) hint.textContent = message;
                return;
            }

            if (!itemsAll || itemsAll.length === 0) {
                const message = lastMessage || 'Tidak ada staff terjadwal pada tanggal ini.';
                renderOptions([], message);
                if (hint) hint.textContent = message;
                return;
            }

            const filtered = itemsAll.filter((item) => Number(item.shift || 0) === selected);
            renderOptions(filtered, 'Tidak ada staff di slot ini.');
            if (hint) {
                hint.textContent = filtered.length > 0
                    ? `Ditemukan ${filtered.length} staff di slot ini.`
                    : 'Tidak ada staff di slot ini.';
            }
        };

        const fetchStaff = async () => {
            if (!dateInput || !staffSelect || !shiftSelect) return;
            if (!dateInput.value || !shiftSelect.value) {
                renderOptions([], 'Pilih tanggal dan slot dulu');
                if (hint) hint.textContent = 'Pilih tanggal dan slot target dulu.';
                return;
            }

            staffSelect.disabled = true;
            renderOptions([], 'Memuat staff...');
            try {
                const response = await fetch(`${baseUrl}?date=${encodeURIComponent(dateInput.value)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    throw new Error('NON_JSON');
                }

                const data = await response.json();
                itemsAll = Array.isArray(data.items) ? data.items : [];
                lastMessage = (typeof data.message === 'string' ? data.message : '') || '';
                myShiftsOnDate = Array.isArray(data.my_shifts) ? data.my_shifts.map((shift) => Number(shift)) : [];
                updateShiftOptions(Array.isArray(data.shifts) ? data.shifts : []);
                applyShiftFilter();
            } catch (error) {
                if (staffSelect) staffSelect.innerHTML = fallbackOptions;
                if (hint) {
                    hint.textContent = (error && error.message === 'NON_JSON')
                        ? 'Gagal memuat karena sesi login mungkin habis. Coba refresh halaman.'
                        : 'Gagal memuat daftar staff. Coba ganti tanggal atau shift.';
                }
            } finally {
                if (staffSelect) staffSelect.disabled = false;
            }
        };

        const openModal = (dateValue, shiftValue) => {
            if (!modal || !form) return;

            if (tanggalInput) tanggalInput.value = dateValue;
            if (dateInput) {
                dateInput.value = minTarget || dateValue;
                dateInput.min = minTarget;
            }

            if (shiftSelect) {
                shiftSelect.innerHTML = '';
                for (let index = 1; index <= Math.max(1, activeShiftCount); index++) {
                    const option = document.createElement('option');
                    option.value = index;
                    option.textContent = shiftCodes[index] || `Shift ${index}`;
                    if (index === Number(shiftValue)) option.selected = true;
                    shiftSelect.appendChild(option);
                }
            }

            if (pesanInput) pesanInput.value = '';
            if (modalInfo) modalInfo.textContent = `Jadwal kamu: ${dateValue}  -  ${shiftCodes[Number(shiftValue)] || ('Shift ' + shiftValue)}`;
            if (hint) hint.textContent = 'Memuat daftar staff...';

            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            fetchStaff();
        };

        const closeModal = () => {
            if (!modal) return;
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        };

        document.querySelectorAll('[data-swap-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const dateValue = button.getAttribute('data-date');
                const shiftValue = button.getAttribute('data-shift');
                if (dateValue && shiftValue) openModal(dateValue, shiftValue);
            });
        });

        if (dateInput) dateInput.addEventListener('change', fetchStaff);
        if (shiftSelect) shiftSelect.addEventListener('change', applyShiftFilter);

        document.querySelectorAll('[data-swap-close]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                closeModal();
            });
        });
    });
</script>
@endsection



