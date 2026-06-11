@extends('layouts.staff')

@section('title', 'Riwayat Pengajuan')

@section('content')
<div class="container app-shell">
    @php
        $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
        $nama = (string) ($staffKaryawan->nama_karyawan ?? 'Karyawan');
        $jabatan = trim((string) ($staffKaryawan->jabatan ?? 'Staff')) ?: 'Staff';
        $tipeKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
        $durasiKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentDurationLabel')) ? $staffKaryawan->employmentDurationLabel() : '8 jam';
        $adminChatUrl = route('staff.messages.show', ['type' => 'admin_chat', 'id' => $staffKaryawan->id_karyawan]);

        $swapCount = (int) ($swapRequests?->count() ?? 0);
        $swapNeedYou = isset($swapRequests)
            ? (int) $swapRequests->filter(function ($req) use ($staffKaryawan) {
                return (int) ($req->to_karyawan_id ?? 0) === (int) ($staffKaryawan->id_karyawan ?? 0)
                    && (string) ($req->status ?? '') === 'pending'
                    && (string) ($req->staff_status ?? 'pending') === 'pending';
            })->count()
            : 0;
        $swapWaitingAdmin = isset($swapRequests)
            ? (int) $swapRequests->filter(fn ($req) => (string) ($req->status ?? '') === 'pending' && (string) ($req->staff_status ?? '') === 'approved')->count()
            : 0;
        $swapFinished = isset($swapRequests)
            ? (int) $swapRequests->filter(fn ($req) => in_array((string) ($req->status ?? ''), ['approved', 'rejected'], true))->count()
            : 0;

        $leaveCount = (int) ($leaveRows?->count() ?? 0);
        $leavePending = isset($leaveRows)
            ? (int) $leaveRows->where('status', 'pending')->count()
            : 0;
        $leaveFinished = max(0, $leaveCount - $leavePending);
    @endphp
    <div class="staff-mobile-page-screen">
        <section class="staff-mobile-page-stage">
            @include('staff.partials.mobile-page-header', [
                'pageTitle' => 'Riwayat Pengajuan',
                'pageMark' => 'RY',
                'staffName' => $nama,
                'greetingTitle' => 'Halo, ' . $nama,
                'greetingSubtitle' => 'Pantau semua tukar shift dan izin tanpa pindah-pindah menu.',
                'employmentLabel' => $tipeKerja,
                'employmentMeta' => $jabatan . ' • ' . $durasiKerja,
            ])

            <article class="staff-mobile-page-summary-card">
                <div class="staff-mobile-page-summary-topline">
                    <div class="staff-mobile-page-summary-period">
                        <span class="staff-mobile-page-summary-label">Ringkasan</span>
                        <strong>{{ $swapCount + $leaveCount }} Pengajuan</strong>
                    </div>
                    <span class="staff-mobile-page-pill">{{ $swapNeedYou + $leavePending }} aktif</span>
                </div>

                <div class="staff-mobile-page-summary-stats">
                    <article>
                        <span>Menunggu Kamu</span>
                        <strong>{{ $swapNeedYou }}</strong>
                    </article>
                    <article>
                        <span>Menunggu Admin</span>
                        <strong>{{ $swapWaitingAdmin }}</strong>
                    </article>
                    <article>
                        <span>Izin Diproses</span>
                        <strong>{{ $leavePending }}</strong>
                    </article>
                    <article>
                        <span>Selesai</span>
                        <strong>{{ $swapFinished + $leaveFinished }}</strong>
                    </article>
                </div>

                <div class="staff-mobile-page-summary-actions">
                    <a class="btn-neutral" href="{{ route('staff.home') }}">Kembali</a>
                    <a class="btn-primary" href="{{ $adminChatUrl }}">Pesan Admin</a>
                    <a class="history-jump-link" href="#history-swap">Tukar Shift</a>
                    <a class="history-jump-link" href="#history-leave">Izin &amp; Sakit</a>
                </div>
            </article>
        </section>

        <div class="panel">
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
        </div>

        <div class="panel history-section" id="history-swap">
        <div class="history-section-head">
            <div>
                <h2 class="history-section-title">Riwayat Tukar Shift</h2>
                <div class="history-section-sub">Cek status pengajuan, detail pertukaran, lalu respon langsung kalau memang menunggu jawabanmu.</div>
            </div>
            <span class="pill gray">{{ $swapCount }} item</span>
        </div>

        @if(isset($swapRequests) && $swapRequests->count() > 0)
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th class="u-w-220">Tukar Jadwal</th>
                            <th>Peran</th>
                            <th class="u-w-220">Status</th>
                            <th class="u-w-180">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($swapRequests as $req)
                        @php
                            $fromDate = $req->from_tanggal?->format('Y-m-d') ?? ($req->tanggal?->format('Y-m-d') ?? '-');
                            $toDate = $req->to_tanggal?->format('Y-m-d') ?? ($req->tanggal?->format('Y-m-d') ?? '-');
                            $fromShift = (int) ($req->from_shift ?? 0);
                            $toShift = (int) ($req->to_shift ?? 0);
                            $adminStatus = strtolower((string) ($req->status ?? 'pending'));
                            $staffStatus = strtolower((string) ($req->staff_status ?? 'pending'));
                            $isTarget = (int) ($req->to_karyawan_id ?? 0) === (int) ($karyawan->id_karyawan ?? 0);
                            $roleLabel = $isTarget ? 'Target' : 'Pemohon';
                            $fromLabel = $staffMap[(int) ($req->from_karyawan_id ?? 0)] ?? ('Karyawan #' . (int) ($req->from_karyawan_id ?? 0));
                            $toLabel = $staffMap[(int) ($req->to_karyawan_id ?? 0)] ?? ('Karyawan #' . (int) ($req->to_karyawan_id ?? 0));
                            $staffNote = trim((string) ($req->staff_note ?? ''));
                            $adminNote = trim((string) ($req->note ?? ''));
                            $statusLabel = 'MENUNGGU STAFF';
                            $statusClass = 'warn';
                            $statusDesc = 'Menunggu jawaban staff target.';
                            if ($adminStatus === 'approved') {
                                $statusLabel = 'DISETUJUI ADMIN';
                                $statusClass = 'ok';
                                $statusDesc = 'Jadwal sudah resmi ditukar.';
                            } elseif ($adminStatus === 'rejected') {
                                $statusLabel = 'DITOLAK ADMIN';
                                $statusClass = 'bad';
                                $statusDesc = 'Permintaan tidak dilanjutkan admin.';
                            } else {
                                if ($staffStatus === 'approved') {
                                    $statusLabel = 'MENUNGGU ADMIN';
                                    $statusClass = 'warn';
                                    $statusDesc = 'Staff target sudah setuju.';
                                } elseif ($staffStatus === 'rejected') {
                                    $statusLabel = 'DITOLAK STAFF';
                                    $statusClass = 'bad';
                                    $statusDesc = 'Staff target menolak permintaan ini.';
                                } elseif ($isTarget) {
                                    $statusLabel = 'MENUNGGU KAMU';
                                    $statusClass = 'warn';
                                    $statusDesc = 'Kamu perlu memberi jawaban.';
                                }
                            }
                            $canRespond = $isTarget && (string) $req->status === 'pending' && (string) ($req->staff_status ?? 'pending') === 'pending';
                        @endphp
                        <tr>
                            <td>
                                <div class="history-table-title">{{ $fromDate }} · S{{ $fromShift }}</div>
                                <div class="history-table-sub">Ditukar ke {{ $toDate }} · S{{ $toShift }}</div>
                            </td>
                            <td><span class="pill gray">{{ strtoupper($roleLabel) }}</span></td>
                            <td>
                                <div class="status-wrap">
                                    <span class="pill {{ $statusClass }}">{{ $statusLabel }}</span>
                                    <div class="status-desc">{{ $statusDesc }}</div>
                                </div>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="btn-neutral btn-mini js-swap-detail"
                                    data-swap-id="{{ $req->id }}"
                                    data-from-date="{{ $fromDate }}"
                                    data-from-shift="{{ $fromShift }}"
                                    data-to-date="{{ $toDate }}"
                                    data-to-shift="{{ $toShift }}"
                                    data-role="{{ $roleLabel }}"
                                    data-status-label="{{ $statusLabel }}"
                                    data-status-class="{{ $statusClass }}"
                                    data-status-desc="{{ $statusDesc }}"
                                    data-admin-status="{{ strtoupper($adminStatus) }}"
                                    data-staff-status="{{ strtoupper($staffStatus) }}"
                                    data-pemohon="{{ $fromLabel }}"
                                    data-target="{{ $toLabel }}"
                                    data-staff-note="{{ str_replace(["\r", "\n"], ' ', $staffNote) }}"
                                    data-admin-note="{{ str_replace(["\r", "\n"], ' ', $adminNote) }}"
                                    data-can-respond="{{ $canRespond ? '1' : '0' }}"
                                    data-approve-url="{{ route('staff.self_schedule.swap.approve', $req) }}"
                                    data-reject-url="{{ route('staff.self_schedule.swap.reject', $req) }}"
                                >
                                    Detail
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="history-cards">
                @foreach($swapRequests as $req)
                    @php
                        $fromDate = $req->from_tanggal?->format('Y-m-d') ?? ($req->tanggal?->format('Y-m-d') ?? '-');
                        $toDate = $req->to_tanggal?->format('Y-m-d') ?? ($req->tanggal?->format('Y-m-d') ?? '-');
                        $fromShift = (int) ($req->from_shift ?? 0);
                        $toShift = (int) ($req->to_shift ?? 0);
                        $adminStatus = strtolower((string) ($req->status ?? 'pending'));
                        $staffStatus = strtolower((string) ($req->staff_status ?? 'pending'));
                        $isTarget = (int) ($req->to_karyawan_id ?? 0) === (int) ($karyawan->id_karyawan ?? 0);
                        $roleLabel = $isTarget ? 'Target' : 'Pemohon';
                        $fromLabel = $staffMap[(int) ($req->from_karyawan_id ?? 0)] ?? ('Karyawan #' . (int) ($req->from_karyawan_id ?? 0));
                        $toLabel = $staffMap[(int) ($req->to_karyawan_id ?? 0)] ?? ('Karyawan #' . (int) ($req->to_karyawan_id ?? 0));
                        $staffNote = trim((string) ($req->staff_note ?? ''));
                        $adminNote = trim((string) ($req->note ?? ''));
                        $statusLabel = 'MENUNGGU STAFF';
                        $statusClass = 'warn';
                        $statusDesc = 'Menunggu jawaban staff target.';
                        if ($adminStatus === 'approved') {
                            $statusLabel = 'DISETUJUI ADMIN';
                            $statusClass = 'ok';
                            $statusDesc = 'Jadwal sudah resmi ditukar.';
                        } elseif ($adminStatus === 'rejected') {
                            $statusLabel = 'DITOLAK ADMIN';
                            $statusClass = 'bad';
                            $statusDesc = 'Permintaan tidak dilanjutkan admin.';
                        } else {
                            if ($staffStatus === 'approved') {
                                $statusLabel = 'MENUNGGU ADMIN';
                                $statusClass = 'warn';
                                $statusDesc = 'Staff target sudah setuju.';
                            } elseif ($staffStatus === 'rejected') {
                                $statusLabel = 'DITOLAK STAFF';
                                $statusClass = 'bad';
                                $statusDesc = 'Staff target menolak permintaan ini.';
                            } elseif ($isTarget) {
                                $statusLabel = 'MENUNGGU KAMU';
                                $statusClass = 'warn';
                                $statusDesc = 'Kamu perlu memberi jawaban.';
                            }
                        }
                        $canRespond = $isTarget && (string) $req->status === 'pending' && (string) ($req->staff_status ?? 'pending') === 'pending';
                    @endphp
                    <div class="history-card">
                        <div class="history-head">
                            <div>
                                <div class="history-title">{{ $fromDate }} · S{{ $fromShift }}</div>
                                <div class="history-meta">Target tukar: {{ $toDate }} · S{{ $toShift }}</div>
                            </div>
                            <span class="pill gray">{{ strtoupper($roleLabel) }}</span>
                        </div>
                        <div class="status-wrap">
                            <span class="pill {{ $statusClass }}">{{ $statusLabel }}</span>
                            <div class="status-desc">{{ $statusDesc }}</div>
                        </div>
                        <div class="history-actions">
                            <button
                                type="button"
                                class="btn-neutral btn-mini js-swap-detail"
                                data-swap-id="{{ $req->id }}"
                                data-from-date="{{ $fromDate }}"
                                data-from-shift="{{ $fromShift }}"
                                data-to-date="{{ $toDate }}"
                                data-to-shift="{{ $toShift }}"
                                data-role="{{ $roleLabel }}"
                                data-status-label="{{ $statusLabel }}"
                                data-status-class="{{ $statusClass }}"
                                data-status-desc="{{ $statusDesc }}"
                                data-admin-status="{{ strtoupper($adminStatus) }}"
                                data-staff-status="{{ strtoupper($staffStatus) }}"
                                data-pemohon="{{ $fromLabel }}"
                                data-target="{{ $toLabel }}"
                                data-staff-note="{{ str_replace(["\r", "\n"], ' ', $staffNote) }}"
                                data-admin-note="{{ str_replace(["\r", "\n"], ' ', $adminNote) }}"
                                data-can-respond="{{ $canRespond ? '1' : '0' }}"
                                data-approve-url="{{ route('staff.self_schedule.swap.approve', $req) }}"
                                data-reject-url="{{ route('staff.self_schedule.swap.reject', $req) }}"
                            >
                                Detail
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="history-empty">
                <div class="history-empty-title">Belum ada permintaan tukar shift.</div>
                <div class="history-empty-sub">Nanti semua riwayat pertukaran jadwal akan muncul otomatis di sini.</div>
            </div>
        @endif
        </div>

        <div class="panel history-section" id="history-leave">
        <div class="history-section-head">
            <div>
                <h2 class="history-section-title">Riwayat Izin &amp; Sakit</h2>
                <div class="history-section-sub">Semua pengajuan izin dan sakit yang pernah kamu kirim ke admin.</div>
            </div>
            <span class="pill gray">{{ $leaveCount }} item</span>
        </div>

        @if(isset($leaveRows) && $leaveRows->count() > 0)
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Status</th>
                            <th>Pesan</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($leaveRows as $row)
                        @php
                            $status = (string) ($row->status ?? 'pending');
                            $statusClass = $status === 'approved' ? 'ok' : ($status === 'rejected' ? 'bad' : 'warn');
                        @endphp
                        <tr>
                            <td>
                                <div class="history-table-title">{{ $row->tanggal_awal?->format('Y-m-d') ?? '-' }}</div>
                                <div class="history-table-sub">s/d {{ $row->tanggal_akhir?->format('Y-m-d') ?? '-' }}</div>
                            </td>
                            <td><span class="pill gray">{{ strtoupper((string) $row->jenis) }}</span></td>
                            <td><span class="pill {{ $statusClass }}">{{ strtoupper($status) }}</span></td>
                            <td><a class="btn-neutral btn-mini" href="{{ $adminChatUrl }}">Buka Chat</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="history-cards">
                @foreach($leaveRows as $row)
                    @php
                        $status = (string) ($row->status ?? 'pending');
                        $statusClass = $status === 'approved' ? 'ok' : ($status === 'rejected' ? 'bad' : 'warn');
                    @endphp
                    <div class="history-card">
                        <div class="history-head">
                            <div>
                                <div class="history-title">{{ strtoupper((string) $row->jenis) }}</div>
                                <div class="history-meta">{{ $row->tanggal_awal?->format('Y-m-d') ?? '-' }} s/d {{ $row->tanggal_akhir?->format('Y-m-d') ?? '-' }}</div>
                            </div>
                            <span class="pill {{ $statusClass }}">{{ strtoupper($status) }}</span>
                        </div>
                        <div class="history-actions">
                            <a class="btn-neutral btn-mini" href="{{ $adminChatUrl }}">Buka Chat</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="history-empty">
                <div class="history-empty-title">Belum ada pengajuan izin atau sakit.</div>
                <div class="history-empty-sub">Setelah kamu mengirim pengajuan, statusnya akan muncul di halaman ini.</div>
            </div>
        @endif
    </div>

    <div class="swap-detail-modal" id="swapDetailModal" aria-hidden="true">
        <div class="swap-detail-card" role="dialog" aria-modal="true" aria-labelledby="swapDetailTitle">
            <div class="swap-detail-head">
                <div>
                    <div class="swap-detail-title" id="swapDetailTitle">Detail Tukar Shift</div>
                    <div class="swap-detail-sub" id="swapDetailSubtitle"></div>
                </div>
            </div>

            <div class="swap-detail-body">
                <div class="detail-row detail-row-highlight">
                    <div class="detail-label">Tukar Jadwal</div>
                    <div class="detail-value" id="swapDetailDates"></div>
                </div>
                <div class="detail-grid">
                    <div class="detail-row">
                        <div class="detail-label">Peran Kamu</div>
                        <div class="detail-value" id="swapDetailRole"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status Saat Ini</div>
                        <div class="detail-inline">
                            <span class="pill" id="swapDetailStatusLabel"></span>
                            <div class="detail-note" id="swapDetailStatusDesc"></div>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Pemohon</div>
                        <div class="detail-value" id="swapDetailPemohon"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Target</div>
                        <div class="detail-value" id="swapDetailTarget"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status Staff</div>
                        <div class="detail-value" id="swapDetailStaffStatus"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status Admin</div>
                        <div class="detail-value" id="swapDetailAdminStatus"></div>
                    </div>
                </div>
                <div class="detail-row" id="swapDetailStaffNoteRow" hidden>
                    <div class="detail-label">Catatan Staff</div>
                    <div class="detail-value" id="swapDetailStaffNote"></div>
                </div>
                <div class="detail-row" id="swapDetailAdminNoteRow" hidden>
                    <div class="detail-label">Catatan Admin</div>
                    <div class="detail-value" id="swapDetailAdminNote"></div>
                </div>
            </div>

            <div class="swap-detail-respond" id="swapDetailRespond" hidden>
                <div class="swap-detail-respond-title">Respon Kamu</div>
                <form method="post" id="swapDetailRespondForm" action="#">
                    @csrf
                    <label class="detail-field">
                        <span class="detail-label">Catatan</span>
                        <textarea name="note" placeholder="Tulis alasan setuju atau tolak (opsional)"></textarea>
                    </label>
                    <div class="detail-inline detail-inline-actions">
                        <button class="btn-mini primary" type="submit" data-action="approve">Setujui</button>
                        <button class="btn-mini danger" type="submit" data-action="reject">Tolak</button>
                    </div>
                </form>
            </div>

            <div class="swap-detail-actions">
                <a class="btn-neutral btn-mini" id="swapDetailChatLink" href="{{ $adminChatUrl }}">Buka Chat</a>
                <button class="btn-neutral btn-mini" type="button" id="swapDetailClose">Tutup</button>
            </div>
        </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const swapDetailModal = document.getElementById('swapDetailModal');
        const swapDetailSubtitle = document.getElementById('swapDetailSubtitle');
        const swapDetailDates = document.getElementById('swapDetailDates');
        const swapDetailRole = document.getElementById('swapDetailRole');
        const swapDetailPemohon = document.getElementById('swapDetailPemohon');
        const swapDetailTarget = document.getElementById('swapDetailTarget');
        const swapDetailStatusLabel = document.getElementById('swapDetailStatusLabel');
        const swapDetailStatusDesc = document.getElementById('swapDetailStatusDesc');
        const swapDetailStaffStatus = document.getElementById('swapDetailStaffStatus');
        const swapDetailAdminStatus = document.getElementById('swapDetailAdminStatus');
        const swapDetailStaffNoteRow = document.getElementById('swapDetailStaffNoteRow');
        const swapDetailStaffNote = document.getElementById('swapDetailStaffNote');
        const swapDetailAdminNoteRow = document.getElementById('swapDetailAdminNoteRow');
        const swapDetailAdminNote = document.getElementById('swapDetailAdminNote');
        const swapDetailRespond = document.getElementById('swapDetailRespond');
        const swapDetailRespondForm = document.getElementById('swapDetailRespondForm');
        const swapDetailClose = document.getElementById('swapDetailClose');
        let swapRespondAction = 'approve';

        function toggleNoteRow(row, valueNode, note) {
            const text = (note || '').trim();
            row.hidden = text === '';
            valueNode.textContent = text;
        }

        function openSwapDetail(button) {
            if (!swapDetailModal || !button) {
                return;
            }

            const data = button.dataset || {};
            const fromDate = data.fromDate || '-';
            const toDate = data.toDate || '-';
            const fromShift = data.fromShift || '-';
            const toShift = data.toShift || '-';

            swapDetailDates.textContent = `${fromDate} · S${fromShift} ke ${toDate} · S${toShift}`;
            swapDetailRole.textContent = data.role || '-';
            swapDetailPemohon.textContent = data.pemohon || '-';
            swapDetailTarget.textContent = data.target || '-';
            swapDetailStatusLabel.textContent = data.statusLabel || '-';
            swapDetailStatusLabel.className = `pill ${data.statusClass || 'gray'}`;
            swapDetailStatusDesc.textContent = data.statusDesc || '';
            swapDetailStaffStatus.textContent = data.staffStatus || '-';
            swapDetailAdminStatus.textContent = data.adminStatus || '-';
            swapDetailSubtitle.textContent = `Pemohon: ${data.pemohon || '-'} • Target: ${data.target || '-'}`;

            toggleNoteRow(swapDetailStaffNoteRow, swapDetailStaffNote, data.staffNote || '');
            toggleNoteRow(swapDetailAdminNoteRow, swapDetailAdminNote, data.adminNote || '');

            const canRespond = (data.canRespond || '') === '1';
            swapDetailRespond.hidden = !canRespond;

            if (swapDetailRespondForm) {
                swapDetailRespondForm.dataset.approveUrl = data.approveUrl || '';
                swapDetailRespondForm.dataset.rejectUrl = data.rejectUrl || '';
            }

            swapDetailModal.classList.add('show');
            swapDetailModal.setAttribute('aria-hidden', 'false');
        }

        function closeSwapDetail() {
            if (!swapDetailModal) {
                return;
            }

            swapDetailModal.classList.remove('show');
            swapDetailModal.setAttribute('aria-hidden', 'true');
        }

        document.querySelectorAll('.js-swap-detail').forEach(function (button) {
            button.addEventListener('click', function () {
                openSwapDetail(button);
            });
        });

        const swapAutoId = @json((string) request()->get('swap_id', ''));
        if (swapAutoId) {
            const autoButton = document.querySelector(`.js-swap-detail[data-swap-id="${swapAutoId}"]`);
            if (autoButton) {
                openSwapDetail(autoButton);
            }
        }

        if (swapDetailRespondForm) {
            swapDetailRespondForm.querySelectorAll('[data-action]').forEach(function (button) {
                button.addEventListener('click', function () {
                    swapRespondAction = button.dataset.action || 'approve';
                });
            });

            swapDetailRespondForm.addEventListener('submit', function (event) {
                const approveUrl = swapDetailRespondForm.dataset.approveUrl || '';
                const rejectUrl = swapDetailRespondForm.dataset.rejectUrl || '';
                const targetUrl = swapRespondAction === 'reject' ? rejectUrl : approveUrl;

                if (targetUrl === '') {
                    event.preventDefault();
                    return;
                }

                swapDetailRespondForm.setAttribute('action', targetUrl);
            });
        }

        if (swapDetailClose) {
            swapDetailClose.addEventListener('click', closeSwapDetail);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && swapDetailModal && swapDetailModal.classList.contains('show')) {
                closeSwapDetail();
            }
        });
    })();
</script>
@endsection
