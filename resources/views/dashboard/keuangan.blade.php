@extends('layouts.app')

@section('title', 'Keuangan')

@section('scripts')
<script>
    (function () {
        const openButtons = document.querySelectorAll('[data-open-modal]');
        const closeButtons = document.querySelectorAll('[data-close-modal]');
        const openModal = (id) => {
            const modal = document.getElementById(id);
            if (modal) modal.removeAttribute('hidden');
        };
        const closeModal = (id) => {
            const modal = document.getElementById(id);
            if (modal) modal.setAttribute('hidden', 'hidden');
        };
        openButtons.forEach((btn) => {
            btn.addEventListener('click', () => openModal(btn.getAttribute('data-open-modal')));
        });
        closeButtons.forEach((btn) => {
            btn.addEventListener('click', () => closeModal(btn.getAttribute('data-close-modal')));
        });
    })();
</script>
@endsection
@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Keuangan</div>
            <h1>Keuangan</h1>
            <p>Kelola setoran kas, saldo belum disetor, dan jadwal setor operasional.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip {{ $isSetoranOverdue ? '' : 'soft' }}">{{ $isSetoranOverdue ? 'Setoran jatuh tempo' : 'Setoran aman' }}</span>
            <span class="admin-chip">Interval {{ (int) $setoranIntervalDays }} hari</span>
        </div>
    </div>

    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert err">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="admin-kpi-grid">
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Status Jadwal Setoran</div>
            <div class="admin-kpi-value">
                <span class="pill {{ $isSetoranOverdue ? 'warn' : 'ok' }}">
                    {{ $isSetoranOverdue ? 'Jatuh Tempo' : 'Aman' }}
                </span>
            </div>
            <div class="admin-kpi-meta">Interval: {{ (int) $setoranIntervalDays }} hari</div>
        </div>
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Saldo Belum Disetor</div>
            <div class="admin-kpi-value">Rp {{ number_format((float) $saldoBelumDisetor, 0, ',', '.') }}</div>
            <div class="admin-kpi-meta">Cash all-time - pengeluaran - total setor</div>
        </div>
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Setoran Bersih Hari Ini</div>
            <div class="admin-kpi-value">Rp {{ number_format((float) $setoranHariIni, 0, ',', '.') }}</div>
            <div class="admin-kpi-meta">Cash hari ini Rp {{ number_format((float) $totalCashHariIni, 0, ',', '.') }} | Pengeluaran Rp {{ number_format((float) $totalPengeluaranHariIni, 0, ',', '.') }}</div>
        </div>
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Setor Terakhir</div>
            <div class="admin-kpi-value value-sm">{{ $lastSetoranAt ? $lastSetoranAt->format('Y-m-d H:i') : 'Belum ada' }}</div>
            <div class="admin-kpi-meta">
                Jatuh tempo berikutnya:
                {{ $nextSetoranDueAt ? $nextSetoranDueAt->format('Y-m-d H:i') : 'Segera setor pertama' }}
            </div>
            @if($setoranDueDays !== null)
                <div class="admin-kpi-meta u-mt-4">
                    @if($setoranDueDays >= 0)
                        Sisa {{ $setoranDueDays }} hari
                    @else
                        Terlambat {{ abs((int) $setoranDueDays) }} hari
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h3 class="u-m-0 u-mb-8">Tandai Setoran</h3>
        <form class="setor-form" method="post" action="{{ route('dashboard.setoran.store') }}">
            @csrf
            <div class="row">
                <input type="number" name="nominal" min="0" step="1" placeholder="Nominal setor (opsional)">
                <input type="text" name="catatan" maxlength="255" placeholder="Catatan setor (opsional)">
                <button class="btn-primary" type="submit">Tandai Setor</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3 class="u-m-0 u-mb-8">Histori Setoran</h3>
        <form method="get" action="{{ route('dashboard.keuangan') }}" class="form-inline" id="keuangan-filter-form">
            <input type="hidden" id="keuangan_awal"  name="tanggal_awal"  value="{{ $filters['tanggal_awal'] ?? '' }}">
            <input type="hidden" id="keuangan_akhir" name="tanggal_akhir" value="{{ $filters['tanggal_akhir'] ?? '' }}">
            <button
                type="button"
                class="btn-daterange-trigger {{ (!empty($filters['tanggal_awal']) || !empty($filters['tanggal_akhir'])) ? 'has-value' : '' }}"
                data-daterange-trigger
                data-start="#keuangan_awal"
                data-end="#keuangan_akhir"
            >
                <span class="dp-trigger-icon">&#128197;</span>
                @if(!empty($filters['tanggal_awal']) && !empty($filters['tanggal_akhir']))
                    <span class="dp-trigger-range">{{ \Carbon\Carbon::parse($filters['tanggal_awal'])->translatedFormat('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($filters['tanggal_akhir'])->translatedFormat('d M Y') }}</span>
                @else
                    <span class="dp-trigger-label">Pilih Periode</span>
                @endif
            </button>
            <button class="btn-primary" type="submit">Filter</button>
            <a class="btn-primary" href="{{ route('dashboard.keuangan.export_excel', ['tanggal_awal' => $filters['tanggal_awal'] ?? null, 'tanggal_akhir' => $filters['tanggal_akhir'] ?? null]) }}">Export Excel</a>
            <a class="btn-neutral" href="{{ route('dashboard.keuangan') }}">Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Tanggal Setor</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Catatan</th>
                    <th>User</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse($setoranRows as $row)
                    @php
                        $catatanText = (string) ($row->catatan ?? '');
                        $isKoreksi = str_starts_with($catatanText, 'Koreksi setoran #');
                        $nominal = (float) ($row->nominal ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $row->tanggal_setor }}</td>
                        <td><span class="tag {{ $isKoreksi ? 'koreksi' : 'normal' }}">{{ $isKoreksi ? 'Koreksi' : 'Normal' }}</span></td>
                        <td class="num {{ $nominal < 0 ? 'minus' : 'plus' }}">
                            {{ $nominal < 0 ? '-' : '' }}Rp {{ number_format(abs($nominal), 0, ',', '.') }}
                        </td>
                        <td>{{ $row->catatan ?: '-' }}</td>
                        <td>{{ $row->user_name ?: '-' }}</td>
                        <td>
                            <div class="u-grid u-gap-6 u-minw-220">
                                <form method="post" action="{{ route('dashboard.setoran.catatan.update', ['setoran' => $row->id]) }}" class="u-flex u-gap-6 u-align-center">
                                    @csrf
                                    <input type="text" name="catatan" maxlength="255" value="{{ $row->catatan }}" placeholder="Edit catatan" class="u-input-sm">
                                    <button class="btn-neutral btn-sm" type="submit">Simpan</button>
                                </form>
                                <button class="btn-primary btn-sm" type="button" data-open-modal="koreksi-{{ $row->id }}">Koreksi Nominal</button>
                            </div>
                            <div class="modal" id="koreksi-{{ $row->id }}" hidden>
                                <div class="modal-backdrop" data-close-modal="koreksi-{{ $row->id }}"></div>
                                <div class="modal-card">
                                    <div class="modal-title">Koreksi Nominal Setoran</div>
                                    <div class="modal-sub">Nominal sebelumnya: <strong>Rp {{ number_format((float) ($row->nominal ?? 0), 0, ',', '.') }}</strong></div>
                                    <form method="post" action="{{ route('dashboard.setoran.nominal.correct', ['setoran' => $row->id]) }}" class="u-grid u-gap-8" onsubmit="return confirm('Buat koreksi nominal untuk data setoran ini?');">
                                        @csrf
                                        <label class="u-text-sm u-font-700">Nominal Baru
                                            <input type="number" name="nominal_baru" min="0" step="1" value="{{ (int) round((float) ($row->nominal ?? 0)) }}" class="u-input-md">
                                        </label>
                                        <label class="u-text-sm u-font-700">Alasan Koreksi
                                            <input type="text" name="catatan" maxlength="255" placeholder="Contoh: salah input nominal" class="u-input-md">
                                        </label>
                                        <div class="modal-actions">
                                            <button class="btn-neutral" type="button" data-close-modal="koreksi-{{ $row->id }}">Batal</button>
                                            <button class="btn-primary" type="submit">Simpan Koreksi</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Belum ada histori setoran.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pages">{{ $setoranRows->appends(['audit_page' => request('audit_page')])->links() }}</div>
    </div>

    <div class="panel">
        <h3 class="u-m-0 u-mb-8">Log Audit Setoran</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Aksi</th>
                    <th>Setoran ID</th>
                    <th>Perubahan Nominal</th>
                    <th>Perubahan Catatan</th>
                    <th>User</th>
                </tr>
                </thead>
                <tbody>
                @forelse($auditRows as $row)
                    @php
                        $aksi = (string) ($row->aksi ?? '-');
                        $aksiLabel = match ($aksi) {
                            'buat_setoran' => 'Buat Setoran',
                            'ubah_catatan' => 'Ubah Catatan',
                            'koreksi_nominal' => 'Koreksi Nominal',
                            default => strtoupper($aksi),
                        };
                        $nominalLama = $row->nominal_lama !== null ? (float) $row->nominal_lama : null;
                        $nominalBaru = $row->nominal_baru !== null ? (float) $row->nominal_baru : null;
                        $catatanLama = $row->catatan_lama !== null ? (string) $row->catatan_lama : '';
                        $catatanBaru = $row->catatan_baru !== null ? (string) $row->catatan_baru : '';
                    @endphp
                    <tr>
                        <td>{{ $row->dibuat_pada }}</td>
                        <td>{{ $aksiLabel }}</td>
                        <td>#{{ (int) ($row->setoran_id ?? 0) }}</td>
                        <td class="num">
                            @if($nominalLama !== null || $nominalBaru !== null)
                                {{ $nominalLama !== null ? 'Rp ' . number_format($nominalLama, 0, ',', '.') : '-' }}
                                ->
                                {{ $nominalBaru !== null ? 'Rp ' . number_format($nominalBaru, 0, ',', '.') : '-' }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            {{ $catatanLama !== '' ? $catatanLama : '-' }}
                            ->
                            {{ $catatanBaru !== '' ? $catatanBaru : '-' }}
                        </td>
                        <td>{{ $row->user_name ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Belum ada log audit setoran.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pages">{{ $auditRows->appends(['setoran_page' => request('setoran_page')])->links() }}</div>
    </div>
</div>
@endsection


