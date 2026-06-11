@extends('layouts.app')

@section('title', 'Permintaan Tukar Jadwal')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Jadwal</div>
            <h1>Permintaan Tukar Jadwal</h1>
            <p>Daftar permintaan tukar shift yang masuk dari portal karyawan.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip">Status: {{ strtoupper((string) ($status ?? 'pending')) }}</span>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Daftar Permintaan</h2>
                <div class="panel-sub">Status: {{ strtoupper((string) ($status ?? 'pending')) }}</div>
            </div>
            <div class="page-actions">
                <a class="btn-neutral" href="{{ route('dashboard.jadwal.index') }}">Kembali ke Jadwal</a>
            </div>
        </div>
        <div class="tabs">
            <a class="{{ ($status ?? 'pending') === 'pending' ? 'active' : '' }}" href="{{ route('dashboard.jadwal.swap_requests', ['status' => 'pending']) }}">Pending</a>
            <a class="{{ ($status ?? '') === 'approved' ? 'active' : '' }}" href="{{ route('dashboard.jadwal.swap_requests', ['status' => 'approved']) }}">Approved</a>
            <a class="{{ ($status ?? '') === 'rejected' ? 'active' : '' }}" href="{{ route('dashboard.jadwal.swap_requests', ['status' => 'rejected']) }}">Rejected</a>
        </div>

        @if (session('success'))
            <div class="alert ok u-mt-10">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert err u-mt-10">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="u-w-120">Tanggal</th>
                        <th class="u-w-140">Tanggal Target</th>
                        <th>Staff</th>
                        <th class="u-w-160">Shift</th>
                        <th class="u-w-120">Status</th>
                        <th class="u-w-140">Status Staff</th>
                        <th class="u-w-200">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        @php
                            $fromDate = $req->from_tanggal ? $req->from_tanggal->format('Y-m-d') : ($req->tanggal ? $req->tanggal->format('Y-m-d') : '-');
                            $toDate = $req->to_tanggal ? $req->to_tanggal->format('Y-m-d') : ($req->tanggal ? $req->tanggal->format('Y-m-d') : '-');
                            $fromName = $req->fromKaryawan?->nama_karyawan ?? '-';
                            $toName = $req->toKaryawan?->nama_karyawan ?? '-';
                            $fromShift = (int) ($req->from_shift ?? 0);
                            $toShift = (int) ($req->to_shift ?? 0);
                            $adminStatus = (string) ($req->status ?? 'pending');
                            $staffStatus = (string) ($req->staff_status ?? 'pending');
                            $staffNote = trim((string) ($req->staff_note ?? ''));
                            $adminNote = trim((string) ($req->note ?? ''));
                            $canRespond = $adminStatus === 'pending';
                        @endphp
                        <tr>
                            <td>{{ $fromDate }}</td>
                            <td>{{ $toDate }}</td>
                            <td>
                                <strong>{{ $fromName }}</strong>
                                <div class="sub">Tukar dengan: {{ $toName }}</div>
                            </td>
                            <td>S{{ $fromShift }} <-> S{{ $toShift }}</td>
                            <td><span class="status {{ $adminStatus }}">{{ strtoupper($adminStatus) }}</span></td>
                            <td>{{ strtoupper($staffStatus) }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn-neutral btn-mini js-swap-admin-detail"
                                    data-swap-id="{{ $req->id }}"
                                    data-from-date="{{ $fromDate }}"
                                    data-to-date="{{ $toDate }}"
                                    data-from-shift="{{ $fromShift }}"
                                    data-to-shift="{{ $toShift }}"
                                    data-from-name="{{ $fromName }}"
                                    data-to-name="{{ $toName }}"
                                    data-admin-status="{{ strtoupper($adminStatus) }}"
                                    data-staff-status="{{ strtoupper($staffStatus) }}"
                                    data-staff-note="{{ str_replace(["\r", "\n"], ' ', $staffNote) }}"
                                    data-admin-note="{{ str_replace(["\r", "\n"], ' ', $adminNote) }}"
                                    data-message-url="{{ $req->from_karyawan_id ? route('dashboard.chat.show', $req->from_karyawan_id) : '#' }}"
                                    data-approve-url="{{ route('dashboard.jadwal.swap_requests.approve', $req) }}"
                                    data-reject-url="{{ route('dashboard.jadwal.swap_requests.reject', $req) }}"
                                    data-can-respond="{{ $canRespond ? '1' : '0' }}"
                                >
                                    Detail
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="sub">Belum ada permintaan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="swap-admin-modal" id="swapAdminModal" aria-hidden="true">
        <div class="swap-admin-card" role="dialog" aria-modal="true">
            <div class="swap-admin-head">
                <div>
                    <div class="swap-admin-title">Detail Tukar Shift</div>
                    <div class="swap-admin-sub" id="swapAdminSubtitle"></div>
                </div>
            </div>
            <div class="swap-admin-body">
                <div class="swap-admin-row">
                    <div class="swap-admin-label">Ringkasan Tukar</div>
                    <div class="swap-admin-value" id="swapAdminDates"></div>
                </div>
                <div class="swap-admin-grid">
                    <div class="swap-admin-row">
                        <div class="swap-admin-label">Pemohon</div>
                        <div class="swap-admin-value" id="swapAdminFrom"></div>
                    </div>
                    <div class="swap-admin-row">
                        <div class="swap-admin-label">Target</div>
                        <div class="swap-admin-value" id="swapAdminTo"></div>
                    </div>
                    <div class="swap-admin-row">
                        <div class="swap-admin-label">Status Admin</div>
                        <div class="swap-admin-value"><span id="swapAdminStatus" class="swap-admin-badge"></span></div>
                    </div>
                    <div class="swap-admin-row">
                        <div class="swap-admin-label">Status Staff</div>
                        <div class="swap-admin-value"><span id="swapAdminStaffStatus" class="swap-admin-badge"></span></div>
                    </div>
                </div>
                <div class="swap-admin-row" id="swapAdminStaffNoteRow" style="display:none;">
                    <div class="swap-admin-label">Catatan Staff</div>
                    <div class="swap-admin-note-block" id="swapAdminStaffNote"></div>
                </div>
                <div class="swap-admin-row" id="swapAdminAdminNoteRow" style="display:none;">
                    <div class="swap-admin-label">Catatan Admin</div>
                    <div class="swap-admin-note-block" id="swapAdminAdminNote"></div>
                </div>
            </div>
            <div class="swap-admin-respond" id="swapAdminRespond" style="display:none;">
                <form method="post" id="swapAdminForm" action="#">
                    @csrf
                    <textarea name="note" placeholder="Catatan (opsional)"></textarea>
                    <div class="actions">
                        <button class="btn-primary" type="submit" data-action="approve">Setujui</button>
                        <button class="btn-neutral" type="submit" data-action="reject">Tolak</button>
                    </div>
                </form>
            </div>
            <div class="swap-admin-actions">
                <a class="btn-neutral btn-mini" id="swapAdminMessageLink" href="#">Buka Chat Admin</a>
                <button class="btn-neutral btn-mini" type="button" onclick="closeSwapAdmin()">Tutup</button>
            </div>
        </div>
    </div>
</div>
<script>
    const swapAdminModal = document.getElementById('swapAdminModal');
    const swapAdminSubtitle = document.getElementById('swapAdminSubtitle');
    const swapAdminDates = document.getElementById('swapAdminDates');
    const swapAdminFrom = document.getElementById('swapAdminFrom');
    const swapAdminTo = document.getElementById('swapAdminTo');
    const swapAdminStatus = document.getElementById('swapAdminStatus');
    const swapAdminStaffStatus = document.getElementById('swapAdminStaffStatus');
    const swapAdminStaffNoteRow = document.getElementById('swapAdminStaffNoteRow');
    const swapAdminStaffNote = document.getElementById('swapAdminStaffNote');
    const swapAdminAdminNoteRow = document.getElementById('swapAdminAdminNoteRow');
    const swapAdminAdminNote = document.getElementById('swapAdminAdminNote');
    const swapAdminRespond = document.getElementById('swapAdminRespond');
    const swapAdminForm = document.getElementById('swapAdminForm');
    const swapAdminMessageLink = document.getElementById('swapAdminMessageLink');
    let swapAdminAction = 'approve';

    function applySwapStatus(el, statusText) {
        if (!el) return;
        const raw = (statusText || '').toString().trim();
        const key = raw.toLowerCase();
        el.textContent = raw !== '' ? raw : '-';
        el.className = 'swap-admin-badge' + (key ? ` is-${key}` : '');
    }

    function openSwapAdmin(btn) {
        if (!swapAdminModal || !btn) return;
        const data = btn.dataset || {};
        const fromDate = data.fromDate || '-';
        const toDate = data.toDate || '-';
        const fromShift = data.fromShift || '-';
        const toShift = data.toShift || '-';
        swapAdminDates.textContent = `${fromDate} (S${fromShift}) → ${toDate} (S${toShift})`;
        swapAdminFrom.textContent = data.fromName || '-';
        swapAdminTo.textContent = data.toName || '-';
        applySwapStatus(swapAdminStatus, data.adminStatus || '');
        applySwapStatus(swapAdminStaffStatus, data.staffStatus || '');
        const staffNote = (data.staffNote || '').trim();
        const adminNote = (data.adminNote || '').trim();
        if (staffNote !== '') {
            swapAdminStaffNoteRow.style.display = '';
            swapAdminStaffNote.textContent = staffNote;
        } else {
            swapAdminStaffNoteRow.style.display = 'none';
            swapAdminStaffNote.textContent = '';
        }
        if (adminNote !== '') {
            swapAdminAdminNoteRow.style.display = '';
            swapAdminAdminNote.textContent = adminNote;
        } else {
            swapAdminAdminNoteRow.style.display = 'none';
            swapAdminAdminNote.textContent = '';
        }
        if (swapAdminSubtitle) {
            swapAdminSubtitle.textContent = `Pemohon: ${data.fromName || '-'} • Target: ${data.toName || '-'}`;
        }
        if (swapAdminRespond) {
            const canRespond = (data.canRespond || '') === '1';
            swapAdminRespond.style.display = canRespond ? 'grid' : 'none';
        }
        if (swapAdminForm) {
            if (data.approveUrl) swapAdminForm.dataset.approveUrl = data.approveUrl;
            if (data.rejectUrl) swapAdminForm.dataset.rejectUrl = data.rejectUrl;
        }
        if (swapAdminMessageLink) {
            swapAdminMessageLink.href = data.messageUrl || '#';
        }
        swapAdminModal.classList.add('show');
        swapAdminModal.setAttribute('aria-hidden', 'false');
    }

    function closeSwapAdmin() {
        if (!swapAdminModal) return;
        swapAdminModal.classList.remove('show');
        swapAdminModal.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.js-swap-admin-detail').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openSwapAdmin(btn);
        });
    });

    const qs = new URLSearchParams(window.location.search);
    const openSwapId = qs.get('swap_id');
    if (openSwapId) {
        const targetBtn = document.querySelector(`.js-swap-admin-detail[data-swap-id="${openSwapId}"]`);
        if (targetBtn) {
            openSwapAdmin(targetBtn);
            targetBtn.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    if (swapAdminForm) {
        swapAdminForm.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                swapAdminAction = btn.dataset.action || 'approve';
            });
        });
        swapAdminForm.addEventListener('submit', function (e) {
            const approveUrl = swapAdminForm.dataset.approveUrl || '';
            const rejectUrl = swapAdminForm.dataset.rejectUrl || '';
            const targetUrl = swapAdminAction === 'reject' ? rejectUrl : approveUrl;
            if (targetUrl === '') {
                e.preventDefault();
                return;
            }
            swapAdminForm.setAttribute('action', targetUrl);
        });
    }

    if (swapAdminModal) {
        swapAdminModal.addEventListener('click', function (e) {
            if (e.target === swapAdminModal) closeSwapAdmin();
        });
    }
</script>
@endsection






