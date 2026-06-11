@extends('layouts.app')

@section('title', 'Pengajuan Izin & Sakit')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Persetujuan</div>
            <h1>Pengajuan Izin & Sakit</h1>
            <p>Tinjau permintaan staf, cek bukti, lalu putuskan status pengajuan dengan cepat.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip">Status: {{ strtoupper((string) $status) }}</span>
            <span class="admin-chip soft">{{ number_format(method_exists($rows, 'total') ? $rows->total() : $rows->count(), 0, ',', '.') }} pengajuan</span>
        </div>
    </div>

    <div class="tabs">
        <a class="{{ $status === 'pending' ? 'active' : '' }}" href="{{ route('dashboard.leave.index', ['status' => 'pending']) }}">Pending</a>
        <a class="{{ $status === 'approved' ? 'active' : '' }}" href="{{ route('dashboard.leave.index', ['status' => 'approved']) }}">Approved</a>
        <a class="{{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('dashboard.leave.index', ['status' => 'rejected']) }}">Rejected</a>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Daftar Pengajuan</h2>
                <div class="panel-sub">Klik baris untuk melihat ringkasan sebelum membuka detail lengkap.</div>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th>Jenis</th>
                        <th>Periode</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $st = (string) ($row->status ?? 'pending');
                            $cls = $st === 'approved' ? 'ok' : ($st === 'rejected' ? 'bad' : 'warn');
                        @endphp
                        <tr
                            class="clickable-row"
                            data-leave-id="{{ $row->id }}"
                            data-nama="{{ e($row->karyawan?->nama_karyawan ?? '-') }}"
                            data-jenis="{{ e(strtoupper((string) $row->jenis)) }}"
                            data-status="{{ e(strtoupper($st)) }}"
                            data-periode="{{ e(($row->tanggal_awal?->format('Y-m-d') ?? '-') . ' s/d ' . ($row->tanggal_akhir?->format('Y-m-d') ?? '-')) }}"
                            data-alasan="{{ e((string) ($row->alasan ?? '-')) }}"
                            data-bukti="{{ $row->bukti_path ? asset('storage/' . $row->bukti_path) : '' }}"
                            data-detail-url="{{ route('dashboard.leave.show', $row) }}"
                        >
                            <td>{{ $row->karyawan?->nama_karyawan ?? '-' }}</td>
                            <td>{{ strtoupper((string) $row->jenis) }}</td>
                            <td>{{ $row->tanggal_awal?->format('Y-m-d') ?? '-' }} s/d {{ $row->tanggal_akhir?->format('Y-m-d') ?? '-' }}</td>
                            <td><span class="pill {{ $cls }}">{{ strtoupper($st) }}</span></td>
                            <td>
                                <a class="btn-neutral btn-mini" href="{{ route('dashboard.leave.show', $row) }}">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="u-text-muted">Belum ada pengajuan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pages">{{ $rows->links() }}</div>
    </div>
</div>

<div class="leave-modal" id="leaveDetailModal" aria-hidden="true">
    <div class="leave-card" role="dialog" aria-modal="true">
        <div class="leave-head">
            <div>
                <div class="leave-title">Detail Izin</div>
                <div class="leave-sub" id="leaveModalSubtitle"></div>
            </div>
        </div>
        <div class="leave-body">
            <div class="leave-grid">
                <div class="leave-row">
                    <div class="leave-label">Karyawan</div>
                    <div class="leave-value" id="leaveModalNama">-</div>
                </div>
                <div class="leave-row">
                    <div class="leave-label">Jenis</div>
                    <div class="leave-value" id="leaveModalJenis">-</div>
                </div>
                <div class="leave-row">
                    <div class="leave-label">Periode</div>
                    <div class="leave-value" id="leaveModalPeriode">-</div>
                </div>
                <div class="leave-row">
                    <div class="leave-label">Status</div>
                    <div class="leave-value"><span id="leaveModalStatus" class="pill warn">-</span></div>
                </div>
            </div>
            <div class="leave-row">
                <div class="leave-label">Alasan</div>
                <div class="leave-note" id="leaveModalAlasan">-</div>
            </div>
            <a class="btn-neutral btn-mini" id="leaveModalBukti" href="#" target="_blank" rel="noopener">Lihat Bukti</a>
        </div>
        <div class="leave-actions">
            <a class="btn-neutral btn-mini" id="leaveModalDetail" href="#">Buka Detail Lengkap</a>
            <button class="btn-neutral btn-mini" type="button" onclick="closeLeaveModal()">Tutup</button>
        </div>
    </div>
</div>

<script>
    const leaveModal = document.getElementById('leaveDetailModal');
    const leaveModalSubtitle = document.getElementById('leaveModalSubtitle');
    const leaveModalNama = document.getElementById('leaveModalNama');
    const leaveModalJenis = document.getElementById('leaveModalJenis');
    const leaveModalPeriode = document.getElementById('leaveModalPeriode');
    const leaveModalStatus = document.getElementById('leaveModalStatus');
    const leaveModalAlasan = document.getElementById('leaveModalAlasan');
    const leaveModalBukti = document.getElementById('leaveModalBukti');
    const leaveModalDetail = document.getElementById('leaveModalDetail');

    function openLeaveModal(row) {
        if (!leaveModal || !row) return;
        leaveModalNama.textContent = row.dataset.nama || '-';
        leaveModalJenis.textContent = row.dataset.jenis || '-';
        leaveModalPeriode.textContent = row.dataset.periode || '-';
        leaveModalAlasan.textContent = row.dataset.alasan || '-';
        leaveModalSubtitle.textContent = row.dataset.status ? `Status: ${row.dataset.status}` : '';
        const status = (row.dataset.status || '').toLowerCase();
        leaveModalStatus.textContent = row.dataset.status || '-';
        leaveModalStatus.className = 'pill ' + (status === 'approved' ? 'ok' : (status === 'rejected' ? 'bad' : 'warn'));
        if (row.dataset.bukti) {
            leaveModalBukti.href = row.dataset.bukti;
            leaveModalBukti.style.display = 'inline-flex';
        } else {
            leaveModalBukti.href = '#';
            leaveModalBukti.style.display = 'none';
        }
        if (leaveModalDetail) {
            leaveModalDetail.href = row.dataset.detailUrl || '#';
        }
        leaveModal.classList.add('show');
        leaveModal.setAttribute('aria-hidden', 'false');
    }

    function closeLeaveModal() {
        if (!leaveModal) return;
        leaveModal.classList.remove('show');
        leaveModal.setAttribute('aria-hidden', 'true');
    }

    if (leaveModal) {
        leaveModal.addEventListener('click', (e) => {
            if (e.target === leaveModal) {
                closeLeaveModal();
            }
        });
    }

    document.querySelectorAll('tr[data-leave-id]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('a, button, form')) return;
            openLeaveModal(row);
        });
    });

    const leaveQs = new URLSearchParams(window.location.search);
    const openLeaveId = leaveQs.get('leave_id');
    if (openLeaveId) {
        const row = document.querySelector(`tr[data-leave-id="${openLeaveId}"]`);
        if (row) {
            openLeaveModal(row);
            row.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }
</script>
@endsection
