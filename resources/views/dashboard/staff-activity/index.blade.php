@extends('layouts.app')

@section('title', 'Aktivitas Staf')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Monitoring</div>
            <h1>Aktivitas Staf</h1>
            <p>Pantau login, absensi, pengajuan, pesan, dan perubahan profil staf.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip">Total: {{ (int) ($summary['total'] ?? 0) }}</span>
            <span class="admin-chip soft">Hari ini: {{ (int) ($summary['today'] ?? 0) }}</span>
            <span class="admin-chip">Staf: {{ (int) ($summary['staff'] ?? 0) }}</span>
        </div>
    </div>

    <div class="panel">
        <form method="get" action="{{ route('dashboard.staff_activity.index') }}" class="staff-activity-filter-form" id="staff-activity-filter-form">
            <label>Cari
                <input type="text" name="q" value="{{ $search }}" placeholder="Nama staf atau ringkasan aksi">
            </label>
            <label>Aksi
                <select name="action">
                    <option value="">Semua aksi</option>
                    @foreach($actionOptions as $option)
                        <option value="{{ $option->action_key }}" @selected($selectedAction === $option->action_key)>{{ $option->action_label }}</option>
                    @endforeach
                </select>
            </label>
            <input type="hidden" id="activity_awal"  name="date_from" value="{{ $dateFrom }}">
            <input type="hidden" id="activity_akhir" name="date_to"   value="{{ $dateTo }}">
            <button
                type="button"
                class="btn-daterange-trigger {{ ($dateFrom || $dateTo) ? 'has-value' : '' }}"
                data-daterange-trigger
                data-start="#activity_awal"
                data-end="#activity_akhir"
            >
                <span class="dp-trigger-icon">&#128197;</span>
                @if($dateFrom && $dateTo)
                    <span class="dp-trigger-range">{{ \Carbon\Carbon::parse($dateFrom)->translatedFormat('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($dateTo)->translatedFormat('d M Y') }}</span>
                @else
                    <span class="dp-trigger-label">Pilih Periode</span>
                @endif
            </button>
            <button class="btn-primary" type="submit">Terapkan</button>
            <a class="btn-neutral" href="{{ route('dashboard.staff_activity.index') }}">Reset</a>
        </form>
    </div>

    <div class="staff-activity-list">
        @forelse($rows as $row)
            @php
                $initial = strtoupper(mb_substr((string) ($row->actor_name ?? 'S'), 0, 1));
                $employmentLabel = \App\Models\Karyawan::employmentTypeLabelFor($row->employment_type);
            @endphp
            <article class="panel staff-activity-card">
                <div class="staff-activity-card-head">
                    <div class="staff-activity-avatar">{{ $initial }}</div>
                    <div class="staff-activity-copy">
                        <strong>{{ $row->action_label }}</strong>
                        <div class="staff-activity-meta">
                            <span>{{ $row->actor_name ?: 'Staff' }}</span>
                            @if(($row->actor_role ?? '') !== '')
                                <span>{{ $row->actor_role }}</span>
                            @endif
                            <span>{{ $employmentLabel }}</span>
                        </div>
                    </div>
                    <time class="staff-activity-time" datetime="{{ $row->created_at?->toIso8601String() }}">{{ $row->created_at?->translatedFormat('d M Y • H:i') }}</time>
                </div>

                <p class="staff-activity-summary">{{ $row->summary }}</p>

                @if(($row->target_label ?? '') !== '' || !empty($row->meta))
                    <div class="staff-activity-tags">
                        @if(($row->target_label ?? '') !== '')
                            <span class="chip">Target: {{ $row->target_label }}</span>
                        @endif
                        @if(!empty($row->meta['tanggal']))
                            <span class="chip">{{ $row->meta['tanggal'] }}</span>
                        @endif
                        @if(!empty($row->meta['shift']))
                            <span class="chip">{{ $row->meta['shift'] }}</span>
                        @endif
                        @if(!empty($row->meta['jumlah_tanggal']))
                            <span class="chip">{{ (int) $row->meta['jumlah_tanggal'] }} tanggal</span>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <div class="panel staff-activity-empty">
                Belum ada log aktivitas staf yang cocok dengan filter ini.
            </div>
        @endforelse
    </div>

    @if(method_exists($rows, 'links'))
        <div class="panel staff-activity-pagination">
            {{ $rows->links() }}
        </div>
    @endif
</div>
@endsection
