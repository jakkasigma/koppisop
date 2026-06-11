@extends('layouts.app')

@section('title', 'Absensi Karyawan')

@section('content')
<style>[x-cloak] { display: none !important; }</style>
<div class='container admin-form-page' x-data='absenModal' x-cloak>
    @php
        $today = now()->toDateString();
        $needsAttention = (int) (($correctionSummary['needs_attention'] ?? 0));
        $requestedCount = (int) (($correctionSummary['requested'] ?? 0));
        $forgotCount = (int) (($correctionSummary['forgot'] ?? 0));
        $waitingCount = (int) (($correctionSummary['waiting_checkout'] ?? 0));
    @endphp

    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Attendance Review</div>
            <h1>Absensi Karyawan</h1>
            <p>Monitoring absensi harian dari QR publik dan portal staf, lengkap dengan koreksi jam pulang dan verifikasi admin.</p>
        </div>
        <div class="admin-page-actions">
            <a class="admin-chip soft" href="{{ route('dashboard.share_qr') }}">Share QR</a>
            <a class="admin-chip" href="{{ route('dashboard.absensi.settings') }}">Pengaturan Absensi</a>
        </div>
    </div>

    <div class="admin-kpi-grid">
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Masuk Tercatat</div>
            <div class="admin-kpi-value">{{ number_format((int) $totalMasuk, 0, ',', '.') }}</div>
            <div class="admin-kpi-meta">Absensi masuk pada periode terpilih</div>
        </div>
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Perlu Koreksi</div>
            <div class="admin-kpi-value">{{ number_format($needsAttention, 0, ',', '.') }}</div>
            <div class="admin-kpi-meta">{{ number_format($requestedCount, 0, ',', '.') }} usulan admin &middot; {{ number_format($forgotCount, 0, ',', '.') }} lupa pulang</div>
        </div>
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Menunggu Pulang</div>
            <div class="admin-kpi-value">{{ number_format($waitingCount, 0, ',', '.') }}</div>
            <div class="admin-kpi-meta">Shift yang belum ditutup staf</div>
        </div>
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Data Ditampilkan</div>
            <div class="admin-kpi-value">{{ number_format((int) $rows->total(), 0, ',', '.') }}</div>
            <div class="admin-kpi-meta">Periode {{ $tanggalAwal }} s/d {{ $tanggalAkhir }}</div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif

    <div class="quickbar-wrap">
        <div class="quickbar">
            <span class="label">Shortcut</span>
            <a class="shortcut" href="{{ route('dashboard.absensi', ['tanggal_awal' => $today, 'tanggal_akhir' => $today]) }}">Hari Ini</a>
            <a class="shortcut" href="{{ route('dashboard.absensi', ['tanggal_awal' => now()->subDay()->toDateString(), 'tanggal_akhir' => now()->subDay()->toDateString()]) }}">Kemarin</a>
            <a class="shortcut" href="{{ route('dashboard.absensi', ['tanggal_awal' => now()->subDays(6)->toDateString(), 'tanggal_akhir' => $today]) }}">Minggu Ini</a>
            <a class="shortcut {{ ($selectedCorrectionFilter ?? 'all') === 'needs_attention' ? 'is-hot' : '' }}" href="{{ route('dashboard.absensi', ['tanggal_awal' => $tanggalAwal, 'tanggal_akhir' => $tanggalAkhir, 'id_karyawan' => $selectedKaryawanId, 'correction_state' => 'needs_attention']) }}">Perlu Koreksi</a>
            <span class="spacer"></span>
            <a class="btn-shareqr" href="{{ route('dashboard.share_qr') }}">Share QR</a>
        </div>
    </div>

    <div class="filter-row">
        <div class="filters">
            <details @if($hasActiveFilter) open @endif>
                <summary>
                    <span class="left">
                        <span>Filter</span>
                        @if($hasActiveFilter)
                            <span class="badge">Aktif</span>
                        @else
                            <span class="hint">Klik untuk pilih tanggal/karyawan</span>
                        @endif
                    </span>
                    <span class="chev">v</span>
                </summary>
                <form method="get" action="{{ route('dashboard.absensi') }}" id="absensi-filter-form">
                    <input type="hidden" id="absensi_awal"  name="tanggal_awal"  value="{{ $tanggalAwal }}">
                    <input type="hidden" id="absensi_akhir" name="tanggal_akhir" value="{{ $tanggalAkhir }}">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                        <button
                            type="button"
                            class="btn-daterange-trigger {{ ($tanggalAwal || $tanggalAkhir) ? 'has-value' : '' }}"
                            data-daterange-trigger
                            data-start="#absensi_awal"
                            data-end="#absensi_akhir"
                        >
                            <span class="dp-trigger-icon">&#128197;</span>
                            @if($tanggalAwal && $tanggalAkhir)
                                <span class="dp-trigger-range">{{ \Carbon\Carbon::parse($tanggalAwal)->translatedFormat('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($tanggalAkhir)->translatedFormat('d M Y') }}</span>
                            @else
                                <span class="dp-trigger-label">Pilih Periode</span>
                            @endif
                        </button>
                    </div>
                    <label>Karyawan
                        <select name="id_karyawan">
                            <option value="">Semua</option>
                            @foreach($karyawan as $k)
                                <option value="{{ $k->id_karyawan }}" @selected((int) ($selectedKaryawanId ?? 0) === (int) $k->id_karyawan)>{{ $k->nama_karyawan }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Koreksi
                        <select name="correction_state">
                            @foreach(($correctionFilterOptions ?? []) as $filterValue => $filterLabel)
                                <option value="{{ $filterValue }}" @selected(($selectedCorrectionFilter ?? 'all') === $filterValue)>{{ $filterLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn-primary" type="submit">Terapkan</button>
                    <a class="btn-neutral" href="{{ route('dashboard.absensi') }}">Reset</a>
                    <a class="btn-primary" href="{{ route('dashboard.absensi.export_excel', ['tanggal_awal' => $tanggalAwal, 'tanggal_akhir' => $tanggalAkhir, 'id_karyawan' => $selectedKaryawanId, 'correction_state' => $selectedCorrectionFilter ?? 'all']) }}">Export</a>
                </form>
            </details>
        </div>
        <a class="btn-primary settings-btn" href="{{ route('dashboard.absensi.settings') }}">Pengaturan Absensi</a>
    </div>

    <div class="panel attendance-panel">
        <div class="attendance-panel-head">
            <div>
                <div class="attendance-panel-kicker">Histori Absensi</div>
                <h2 class="attendance-panel-title">Daftar absensi harian karyawan</h2>
                <p class="attendance-panel-sub">Klik salah satu baris untuk melihat ringkasan jam masuk-pulang, durasi kerja, selfie, koreksi jam pulang bila ada, lalu verifikasi atau tolak langsung dari popup.</p>
            </div>
            <div class="attendance-panel-side">
                <span class="attendance-total">{{ $rows->total() }} data</span>
                <span class="attendance-hint">Periode {{ $tanggalAwal }} s/d {{ $tanggalAkhir }}</span>
            </div>
        </div>
        <div class="attendance-summary-strip">
            <a class="attendance-summary-card {{ ($selectedCorrectionFilter ?? 'all') === 'needs_attention' ? 'is-active' : '' }}" href="{{ route('dashboard.absensi', ['tanggal_awal' => $tanggalAwal, 'tanggal_akhir' => $tanggalAkhir, 'id_karyawan' => $selectedKaryawanId, 'correction_state' => 'needs_attention']) }}">
                <span class="attendance-summary-label">Perlu Koreksi</span>
                <strong>{{ (int) (($correctionSummary['needs_attention'] ?? 0)) }}</strong>
                <small>Butuh tindak lanjut admin</small>
            </a>
            <a class="attendance-summary-card {{ ($selectedCorrectionFilter ?? 'all') === 'requested' ? 'is-active' : '' }}" href="{{ route('dashboard.absensi', ['tanggal_awal' => $tanggalAwal, 'tanggal_akhir' => $tanggalAkhir, 'id_karyawan' => $selectedKaryawanId, 'correction_state' => 'requested']) }}">
                <span class="attendance-summary-label">Menunggu Admin</span>
                <strong>{{ (int) (($correctionSummary['requested'] ?? 0)) }}</strong>
                <small>Usulan jam pulang masuk</small>
            </a>
            <a class="attendance-summary-card {{ ($selectedCorrectionFilter ?? 'all') === 'forgot' ? 'is-active' : '' }}" href="{{ route('dashboard.absensi', ['tanggal_awal' => $tanggalAwal, 'tanggal_akhir' => $tanggalAkhir, 'id_karyawan' => $selectedKaryawanId, 'correction_state' => 'forgot']) }}">
                <span class="attendance-summary-label">Lupa Pulang</span>
                <strong>{{ (int) (($correctionSummary['forgot'] ?? 0)) }}</strong>
                <small>Sudah lewat jam normal</small>
            </a>
            <a class="attendance-summary-card {{ ($selectedCorrectionFilter ?? 'all') === 'waiting_checkout' ? 'is-active' : '' }}" href="{{ route('dashboard.absensi', ['tanggal_awal' => $tanggalAwal, 'tanggal_akhir' => $tanggalAkhir, 'id_karyawan' => $selectedKaryawanId, 'correction_state' => 'waiting_checkout']) }}">
                <span class="attendance-summary-label">Menunggu Pulang</span>
                <strong>{{ (int) (($correctionSummary['waiting_checkout'] ?? 0)) }}</strong>
                <small>Masih aktif / belum lengkap</small>
            </a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Karyawan</th>
                        <th>Masuk</th>
                        <th>Pulang</th>
                        <th>Durasi</th>
                        <th>Status</th>
                        <th>Verifikasi</th>
                        <th>Sumber</th>
                        <th>Selfie</th>
                        <th>Shift</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $st = (string) ($row->status ?? '');
                            $correctionMeta = (array) ($row->correction_meta ?? []);
                            $correctionKey = (string) ($correctionMeta['key'] ?? 'complete');
                            $correctionLabel = (string) ($correctionMeta['label'] ?? 'Lengkap');
                            $correctionNote = (string) ($correctionMeta['note'] ?? '');
                            $stLabel = match ($st) {
                                'hadir' => 'Hadir',
                                'telat' => 'Telat',
                                'alpa' => 'Alpa',
                                'tidak_dijadwalkan' => 'Tidak Dijadwalkan',
                                default => ($st !== '' ? ucwords(str_replace('_', ' ', $st)) : '-'),
                            };
                            $stCls = match ($st) {
                                'hadir' => 'ok',
                                'telat' => 'warn',
                                'alpa' => 'err',
                                'tidak_dijadwalkan' => 'off',
                                default => ($st !== '' ? 'off' : 'off'),
                            };
                            $src = (string) ($row->absensi_source ?? '');
                            $srcLabel = $src === 'kasir' ? 'Kasir' : ($src === 'portal' ? 'HP/Portal' : '-');
                            $srcCls = $src === 'kasir' ? 'ok' : ($src === 'portal' ? 'off' : 'off');
                            $verificationRaw = (string) ($row->verification_status ?? 'pending');
                            $awaitingCheckout = (bool) ($row->waktu_masuk && ! $row->waktu_pulang);
                            $canFinalize = ! $awaitingCheckout;
                            $verificationLabel = match (true) {
                                $verificationRaw === 'verified' => 'Terverifikasi',
                                $verificationRaw === 'rejected' => 'Ditolak',
                                $correctionKey === 'requested' => 'Menunggu Koreksi Admin',
                                $correctionKey === 'rejected' => 'Koreksi Ditolak',
                                $correctionKey === 'forgot' => 'Lupa Absen Pulang',
                                $correctionKey === 'expired' => 'Perlu Koreksi Admin',
                                $awaitingCheckout => 'Menunggu Pulang',
                                default => 'Siap Diverifikasi',
                            };
                            $verificationCls = match (true) {
                                $verificationRaw === 'verified' => 'ok',
                                $verificationRaw === 'rejected' => 'err',
                                in_array($correctionKey, ['requested', 'forgot'], true) => 'warn',
                                in_array($correctionKey, ['rejected', 'expired'], true) => 'err',
                                $awaitingCheckout => 'off',
                                default => 'warn',
                            };
                            $shiftNo = !empty($row->shift_no) ? (int) $row->shift_no : 0;
                            $shiftTime = $shiftNo === 1
                                ? (string) ($setting->shift1_start_time ?? '07:00')
                                : ($shiftNo === 2
                                    ? (string) ($setting->shift2_start_time ?? '15:00')
                                    : (string) ($setting->shift3_start_time ?? '23:00'));
                            $statusWithDelta = $stLabel;
                            if ($row->waktu_masuk && $shiftNo > 0 && $st === 'telat') {
                                $startAt = \Illuminate\Support\Carbon::parse(($row->tanggal?->format('Y-m-d') ?? '') . ' ' . $shiftTime . ':00');
                                $deltaMin = (int) $row->waktu_masuk->diffInMinutes($startAt, false);
                                if ($deltaMin > 0) {
                                    $statusWithDelta = 'Telat (' . $deltaMin . ' menit)';
                                }
                            }
                            $geoStatus = '-';
                            if ($row->geo_lat !== null && $row->geo_lng !== null) {
                                $maxAcc = (int) ($setting->absensi_geo_max_accuracy_m ?? 80);
                                $geoStatus = $row->geo_accuracy_m !== null && (int) $row->geo_accuracy_m <= $maxAcc ? 'Lolos' : 'Perlu cek';
                            }
                            $durasiLabel = '-';
                            if ($row->waktu_masuk && $row->waktu_pulang && $row->waktu_pulang->greaterThan($row->waktu_masuk)) {
                                $durasiMenit = (int) $row->waktu_masuk->diffInMinutes($row->waktu_pulang);
                                $durasiJam = intdiv($durasiMenit, 60);
                                $sisaMenit = $durasiMenit % 60;
                                $durasiParts = [];
                                if ($durasiJam > 0) {
                                    $durasiParts[] = $durasiJam . ' jam';
                                }
                                if ($sisaMenit > 0) {
                                    $durasiParts[] = $sisaMenit . ' menit';
                                }
                                $durasiLabel = $durasiParts !== [] ? implode(' ', $durasiParts) : '0 menit';
                            }
                        @endphp
                        <tr class="row-click" @click="openModalFromRow($el.dataset)"
                            data-id="{{ $row->id_absensi }}"
                            data-tanggal="{{ $row->tanggal?->format('Y-m-d') ?? '-' }}"
                            data-karyawan="{{ $row->karyawan?->nama_karyawan ?? '-' }}"
                            data-jabatan="{{ $row->karyawan?->jabatan ?? '-' }}"
                            data-masuk="{{ $row->waktu_masuk ? $row->waktu_masuk->format('H:i') : '-' }}"
                            data-status-raw="{{ $st }}"
                            data-pulang="{{ $row->waktu_pulang ? $row->waktu_pulang->format('H:i') : '-' }}"
                            data-durasi="{{ $durasiLabel }}"
                            data-status="{{ $statusWithDelta }}"
                            data-verification="{{ $verificationLabel }}"
                            data-verification-raw="{{ $verificationRaw }}"
                            data-can-finalize="{{ $canFinalize ? '1' : '0' }}"
                            data-finalize-note="{{ $canFinalize ? 'Data absensi harian sudah lengkap dan siap diverifikasi.' : 'Staf belum absen pulang. Admin bisa memantau dulu, tapi verifikasi final menunggu data harian lengkap.' }}"
                            data-correction-key="{{ $correctionKey }}"
                            data-correction-label="{{ $correctionLabel }}"
                            data-correction-note="{{ $correctionNote }}"
                            data-correction-deadline="{{ (string) ($correctionMeta['deadline_label'] ?? '-') }}"
                            data-correction-requested-pulang="{{ (string) ($correctionMeta['requested_pulang_label'] ?? '-') }}"
                            data-correction-requested-pulang-input="{{ (string) ($correctionMeta['requested_pulang_input'] ?? '') }}"
                            data-correction-request-note="{{ (string) ($correctionMeta['requested_note'] ?? '') }}"
                            data-correction-review-note="{{ (string) ($correctionMeta['review_note'] ?? '') }}"
                            data-correction-expected="{{ (string) ($correctionMeta['expected_checkout_label'] ?? '-') }}"
                            data-correction-expected-input="{{ (string) ($correctionMeta['expected_checkout_input'] ?? '') }}"
                            data-shift="{{ !empty($row->shift_no) ? 'S' . (int) $row->shift_no : '-' }}"
                            data-source="{{ $srcLabel }}"
                            data-catatan="{{ $row->catatan ?? '-' }}"
                            data-selfie="{{ !empty($row->selfie_path) ? asset('storage/' . ltrim((string) $row->selfie_path, '/')) : '' }}"
                            data-verify-url="{{ route('dashboard.absensi.verify', $row) }}"
                            data-reject-url="{{ route('dashboard.absensi.reject', $row) }}"
                            data-correction-approve-url="{{ route('dashboard.absensi.checkout_correction.approve', $row) }}"
                            data-correction-reject-url="{{ route('dashboard.absensi.checkout_correction.reject', $row) }}"
                            data-correction-manual-url="{{ route('dashboard.absensi.checkout_correction.manual', $row) }}"
                        >
                            <td data-label="Tanggal">{{ $row->tanggal?->format('Y-m-d') ?? '-' }}</td>
                            <td data-label="Karyawan"><strong>{{ $row->karyawan?->nama_karyawan ?? '-' }}</strong> <span class="u-text-muted">{{ $row->karyawan?->jabatan ? '(' . $row->karyawan->jabatan . ')' : '' }}</span></td>
                            <td data-label="Masuk">{{ $row->waktu_masuk ? $row->waktu_masuk->format('H:i') : '-' }}</td>
                            <td data-label="Pulang">{{ $row->waktu_pulang ? $row->waktu_pulang->format('H:i') : '-' }}</td>
                            <td data-label="Durasi">
                                <span class="tiny {{ $row->waktu_pulang ? 'is-worked' : '' }}">{{ $durasiLabel }}</span>
                            </td>
                            <td data-label="Status">
                                <span class="pill-status {{ $stCls }}">{{ $stLabel }}</span>
                            </td>
                            <td data-label="Verifikasi">
                                <span class="pill-status {{ $verificationCls }}">{{ $verificationLabel }}</span>
                            </td>
                            <td data-label="Sumber">
                                <span class="pill-status {{ $srcCls }}">{{ $srcLabel }}</span>
                            </td>
                            <td data-label="Selfie">
                                @if(!empty($row->selfie_path))
                                    @php
                                        $url = asset('storage/' . ltrim((string) $row->selfie_path, '/'));
                                    @endphp
                                    <a href="{{ $url }}" target="_blank" rel="noopener" title="Lihat selfie">
                                        <img class="thumb" alt="Selfie" src="{{ $url }}">
                                    </a>
                                @else
                                    <span class="tiny">-</span>
                                @endif
                            </td>
                            <td data-label="Shift">
                                @if(!empty($row->shift_no))
                                    <span class="pill-status off">S{{ (int) $row->shift_no }}</span>
                                @else
                                    <span class="tiny">-</span>
                                @endif
                            </td>
                            <td data-label="Catatan">{{ $row->catatan ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="u-text-muted">Belum ada data absensi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pages">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- Load Alpine.js CDN -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div 
    class="modal-backdrop" 
    id="absenDetailModal" 
    x-show="isOpen" 
    :class="{ 'show': isOpen }"
    role="dialog" 
    aria-modal="true" 
    aria-label="Detail Absensi"
    @click.self="closeModal()"
    x-cloak
>
    <div class="modal-card">
        <div class="modal-head">
            <div>
                <h3 class="modal-title" x-text="absen.karyawan ? 'Verifikasi absensi ' + absen.karyawan : 'Verifikasi absensi'">Detail Absensi</h3>
                <p class="modal-sub" x-text="absen.pulang && absen.pulang !== '-' ? 'Jam kerja tercatat ' + absen.masuk + ' - ' + absen.pulang + ' dengan durasi ' + absen.durasi + '.' : 'Absen masuk tercatat ' + absen.masuk + '. Jam pulang belum dicatat.'">Ringkasan lengkap absensi terpilih untuk proses verifikasi admin.</p>
            </div>
            <button type="button" class="btn-neutral modal-close-btn" @click="closeModal()">Tutup</button>
        </div>
        <div class="modal-body">
            <div class="verify-summary">
                <div class="verify-summary-main">
                    <div class="verify-summary-title" x-text="absen.canFinalize === '1' ? 'Siap diverifikasi' : 'Menunggu Pulang'">Siap diverifikasi</div>
                    <div class="verify-summary-sub" x-text="absen.canFinalize === '1' ? 'Cek jam masuk, jam pulang, lalu putuskan verifikasi.' : 'Staf belum absen pulang. Anda bisa memantau dulu.'">Cek jam masuk, jam pulang, lalu putuskan verifikasi.</div>
                    <div class="verify-summary-note" :class="{ 'is-waiting': absen.canFinalize !== '1' }" x-text="absen.finalizeNote">Data absensi harian sudah lengkap dan siap diverifikasi.</div>
                </div>
                <div class="verify-summary-side">
                    <span class="badge" :class="absen.verificationRaw === 'verified' ? 'ok' : (absen.verificationRaw === 'rejected' ? 'bad' : (absen.canFinalize === '1' ? 'warn' : ''))" x-text="absen.verification">-</span>
                    <span class="badge" :class="absen.status && absen.status.toLowerCase().includes('telat') ? 'warn' : (absen.statusRaw === 'alpa' ? 'bad' : 'ok')" x-text="absen.status">-</span>
                </div>
            </div>
            <div class="meta-grid">
                <div class="meta-card">
                    <div class="meta-label">Tanggal</div>
                    <div class="meta-value" x-text="absen.tanggal || '-'">-</div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Karyawan</div>
                    <div class="meta-value"><span x-text="absen.karyawan || '-'">-</span> <span x-text="absen.jabatan ? '(' + absen.jabatan + ')' : ''"></span></div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Shift</div>
                    <div class="meta-value" x-text="absen.shift || '-'">-</div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Masuk</div>
                    <div class="meta-value" x-text="absen.masuk || '-'">-</div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Pulang</div>
                    <div class="meta-value" x-text="absen.pulang || '-'">-</div>
                </div>
                <div class="meta-card is-highlight">
                    <div class="meta-label">Durasi Kerja</div>
                    <div class="meta-value" x-text="absen.durasi || '-'">-</div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Status</div>
                    <div class="meta-value">
                        <span class="badge" :class="absen.status && absen.status.toLowerCase().includes('telat') ? 'warn' : (absen.statusRaw === 'alpa' ? 'bad' : 'ok')" x-text="absen.status || '-'">-</span>
                    </div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Verifikasi</div>
                    <div class="meta-value">
                        <span class="badge" :class="absen.verificationRaw === 'verified' ? 'ok' : (absen.verificationRaw === 'rejected' ? 'bad' : (absen.canFinalize === '1' ? 'warn' : ''))" x-text="absen.verification || '-'">-</span>
                    </div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Koreksi Pulang</div>
                    <div class="meta-value">
                        <span class="badge" :class="absen.correctionKey === 'complete' ? 'ok' : (absen.correctionKey === 'requested' || absen.correctionKey === 'forgot' ? 'warn' : (absen.correctionKey === 'waiting_checkout' ? '' : 'bad'))" x-text="absen.correctionLabel || '-'">-</span>
                    </div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Sumber</div>
                    <div class="meta-value">
                        <span class="badge" :class="absen.source === 'Kasir' ? 'ok' : (absen.source === 'HP/Portal' ? 'warn' : '')" x-text="absen.source || '-'">-</span>
                    </div>
                </div>
                <div class="meta-card u-grid-span-full">
                    <div class="meta-label">Catatan</div>
                    <div class="meta-value" x-text="absen.catatan || '-'">-</div>
                </div>
            </div>
            <div class="modal-photo-wrap" x-show="absen.selfie" style="margin-top: 15px;">
                <img class="modal-photo" :src="absen.selfie" alt="Selfie Absensi" style="max-width: 100%; border-radius: 8px;">
            </div>
            <div class="modal-correction-wrap" x-show="absen.correctionKey && absen.correctionKey !== 'complete'" style="margin-top: 15px;">
                <div class="meta-grid">
                    <div class="meta-card">
                        <div class="meta-label">Status Koreksi</div>
                        <div class="meta-value" x-text="absen.correctionLabel || '-'">-</div>
                    </div>
                    <div class="meta-card">
                        <div class="meta-label">Jam Pulang Usulan</div>
                        <div class="meta-value" x-text="absen.correctionRequestedPulang && absen.correctionRequestedPulang !== '-' ? absen.correctionRequestedPulang : (absen.correctionExpected || '-')">-</div>
                    </div>
                    <div class="meta-card">
                        <div class="meta-label">Batas Koreksi</div>
                        <div class="meta-value" x-text="absen.correctionDeadline || '-'">-</div>
                    </div>
                    <div class="meta-card u-grid-span-full">
                        <div class="meta-label">Catatan Koreksi</div>
                        <div class="meta-value" x-text="absen.correctionRequestNote || '-'">-</div>
                    </div>
                    <div class="meta-card u-grid-span-full">
                        <div class="meta-label">Catatan Admin</div>
                        <div class="meta-value" x-text="absen.correctionReviewNote || '-'">-</div>
                    </div>
                </div>
                <div class="modal-correction-actions" style="margin-top: 15px;">
                    <form :action="absen.correctionApproveUrl" method="post" class="modal-inline-form" x-show="absen.correctionKey === 'requested'">
                        @csrf
                        <input type="hidden" name="note" value="">
                        <button class="btn-primary" type="submit">Setujui Koreksi</button>
                    </form>
                    <form :action="absen.correctionRejectUrl" method="post" class="modal-reject-form" x-show="absen.correctionKey === 'requested'">
                        @csrf
                        <label for="mdCorrectionRejectNote">Catatan penolakan</label>
                        <input id="mdCorrectionRejectNote" type="text" name="note" x-model="correctionRejectNote" placeholder="Isi alasan koreksi ditolak" required class="u-input-lg">
                        <button class="btn-danger" type="submit">Tolak Koreksi</button>
                    </form>
                    <form :action="absen.correctionManualUrl" method="post" class="modal-reject-form" x-show="absen.canFinalize !== '1'">
                        @csrf
                        <label for="mdManualPulang">Jam pulang manual</label>
                        <input id="mdManualPulang" type="datetime-local" name="manual_pulang" x-model="manualPulang" required class="u-input-lg">
                        <label for="mdManualNote">Catatan admin</label>
                        <input id="mdManualNote" type="text" name="manual_note" x-model="manualNote" placeholder="Contoh: staf lupa klik absen pulang" required class="u-input-lg">
                        <button class="btn-primary" type="submit">Simpan Jam Pulang Manual</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-actions" style="margin-top: 20px;">
            <button type="button" class="btn-neutral" @click="closeModal()">Tutup</button>
            <form :action="absen.verifyUrl" method="post" class="modal-inline-form" :class="{ 'is-disabled': absen.canFinalize !== '1' }">
                @csrf
                <input type="hidden" name="note" value="">
                <button class="btn-primary" type="submit" :disabled="absen.canFinalize !== '1'" x-text="absen.canFinalize === '1' ? 'Verifikasi' : 'Menunggu Pulang'">Verifikasi</button>
            </form>
            <form :action="absen.rejectUrl" method="post" class="modal-reject-form" :class="{ 'is-disabled': absen.canFinalize !== '1' }">
                @csrf
                <label for="mdRejectNote">Catatan admin</label>
                <input id="mdRejectNote" type="text" name="note" x-model="rejectNote" :disabled="absen.canFinalize !== '1'" placeholder="Isi alasan penolakan atau catatan verifikasi" required class="u-input-lg">
                <button class="btn-danger" type="submit" :disabled="absen.canFinalize !== '1'">Tolak</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('absenModal', () => ({
        isOpen: false,
        absen: {},
        manualPulang: '',
        manualNote: '',
        rejectNote: '',
        correctionRejectNote: '',

        openModalFromRow(dataset) {
            this.absen = dataset;
            this.manualPulang = dataset.correctionRequestedPulangInput || dataset.correctionExpectedInput || '';
            this.manualNote = dataset.correctionReviewNote || '';
            this.rejectNote = '';
            this.correctionRejectNote = '';
            this.isOpen = true;
        },
        closeModal() {
            this.isOpen = false;
        },
        init() {
            const qs = new URLSearchParams(window.location.search);
            const openId = qs.get('absensi_id');
            if (openId) {
                this.$nextTick(() => {
                    const row = document.querySelector(`tr.row-click[data-id="${openId}"]`);
                    if (row) {
                        this.openModalFromRow(row.dataset);
                        row.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    }
                });
            }
        }
    }));
});
</script>
@endsection