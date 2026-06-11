@extends('layouts.staff')

@section('title', 'Izin & Sakit')

@section('content')
<div class="container app-shell">
    @php
        $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
        $nama = (string) ($staffKaryawan->nama_karyawan ?? 'Karyawan');
        $jabatan = trim((string) ($staffKaryawan->jabatan ?? 'Staff')) ?: 'Staff';
        $tipeKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
        $durasiKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentDurationLabel')) ? $staffKaryawan->employmentDurationLabel() : '8 jam';
        $adminChatUrl = route('staff.messages.show', ['type' => 'admin_chat', 'id' => $staffKaryawan->id_karyawan]);
        $totalRows = (int) ($rows?->count() ?? 0);
        $pendingRows = isset($rows) ? (int) $rows->where('status', 'pending')->count() : 0;
        $approvedRows = isset($rows) ? (int) $rows->where('status', 'approved')->count() : 0;
        $rejectedRows = isset($rows) ? (int) $rows->where('status', 'rejected')->count() : 0;
        $jadwalCount = (int) collect($jadwalOptions ?? [])->count();
    @endphp
    <div class="staff-mobile-page-screen">
        <section class="staff-mobile-page-stage">
            @include('staff.partials.mobile-page-header', [
                'pageTitle' => 'Izin & Sakit',
                'pageMark' => 'IZ',
                'staffName' => $nama,
                'greetingTitle' => 'Halo, ' . $nama,
                'greetingSubtitle' => 'Ajukan izin sesuai jadwal masukmu lalu pantau balasannya dari sini.',
                'employmentLabel' => $tipeKerja,
                'employmentMeta' => $jabatan . ' • ' . $durasiKerja,
            ])

            <article class="staff-mobile-page-summary-card">
                <div class="staff-mobile-page-summary-topline">
                    <div class="staff-mobile-page-summary-period">
                        <span class="staff-mobile-page-summary-label">Pengajuan</span>
                        <strong>{{ $pendingRows }} Aktif</strong>
                    </div>
                    <span class="staff-mobile-page-pill">{{ $approvedRows }} disetujui</span>
                </div>

                <div class="staff-mobile-page-summary-stats">
                    <article>
                        <span>Diproses</span>
                        <strong>{{ $pendingRows }}</strong>
                    </article>
                    <article>
                        <span>Disetujui</span>
                        <strong>{{ $approvedRows }}</strong>
                    </article>
                    <article>
                        <span>Ditolak</span>
                        <strong>{{ $rejectedRows }}</strong>
                    </article>
                    <article>
                        <span>Jadwal Tersedia</span>
                        <strong>{{ $jadwalCount }}</strong>
                    </article>
                </div>

                <div class="staff-mobile-page-summary-actions">
                    <a class="btn-neutral" href="{{ route('staff.home') }}">Kembali</a>
                    <a class="btn-primary" href="{{ $adminChatUrl }}">Pesan Admin</a>
                </div>
            </article>
        </section>

        <div class="panel leave-form-panel">
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

        <div class="leave-section-head">
            <div>
                <h2 class="leave-section-title">Form Pengajuan</h2>
                <div class="leave-section-sub">Pilih tanggal dari jadwalmu, tambahkan alasan seperlunya, lalu kirim ke admin.</div>
            </div>
            <span class="pill gray">{{ $totalRows }} riwayat</span>
        </div>

        <form method="post" action="{{ route('staff.leave.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Jenis Pengajuan</label>
                    <select name="jenis" required>
                        <option value="izin" @selected(old('jenis') === 'izin')>Izin</option>
                        <option value="sakit" @selected(old('jenis') === 'sakit')>Sakit</option>
                    </select>
                </div>
                <div class="field">
                    <label>Bukti Pendukung</label>
                    <input type="file" name="bukti" accept="image/*,application/pdf">
                    <div class="field-note">Boleh dikosongkan kalau belum ada file pendukung.</div>
                </div>

                <div class="field u-col-full">
                    <label>Tanggal Sesuai Jadwal</label>
                    <div class="leave-picker-card">
                        <div>
                            <div class="leave-picker-title">Pilih Jadwal Masuk</div>
                            <div class="leave-picker-sub">Kamu bisa pilih lebih dari satu tanggal selama memang ada jadwal masuk di hari tersebut.</div>
                        </div>
                        <button class="btn-neutral" type="button" onclick="openSchedulePicker()">Pilih Jadwal</button>
                    </div>
                    <div class="selected-dates" id="selectedDates">
                        <span class="selected-empty">Belum ada tanggal dipilih.</span>
                    </div>
                </div>

                <div class="field u-col-full">
                    <label>Alasan Utama</label>
                    <textarea name="alasan" placeholder="Tulis alasan singkat agar admin mudah memahami pengajuanmu.">{{ old('alasan') }}</textarea>
                </div>

                <div class="field u-col-full">
                    <label>Pesan Tambahan untuk Admin</label>
                    <textarea name="pesan" placeholder="Contoh: Saya sudah koordinasi dengan tim shift pagi.">{{ old('pesan') }}</textarea>
                </div>
            </div>

            <div class="actions">
                <button class="btn-primary" type="submit">Kirim Pengajuan</button>
            </div>

            <div class="schedule-modal" id="scheduleModal" aria-hidden="true">
                <div class="schedule-card" role="dialog" aria-modal="true">
                    <div class="schedule-head">
                        <div>
                            <strong>Pilih Jadwal Masuk</strong>
                            <div class="leave-meta">Hanya tanggal yang memang ada jadwalmu yang bisa dipilih.</div>
                        </div>
                        <button class="btn-neutral" type="button" onclick="closeSchedulePicker()">Tutup</button>
                    </div>
                    <div class="schedule-body" id="scheduleList">
                        @forelse(($jadwalOptions ?? []) as $opt)
                            @php
                                $tgl = $opt->tanggal?->format('Y-m-d') ?? '';
                                $shift = (int) ($opt->shift_ke ?? 0);
                                $checked = in_array($tgl, (array) old('tanggal_pilihan', []), true);
                            @endphp
                            <label class="schedule-item">
                                <div class="schedule-info">
                                    <input type="checkbox" name="tanggal_pilihan[]" value="{{ $tgl }}" @checked($checked) onchange="refreshSelectedDates()">
                                    <span class="schedule-date">{{ $tgl }}</span>
                                    @if($shift > 0)
                                        <span class="schedule-shift">Shift {{ $shift }}</span>
                                    @endif
                                </div>
                            </label>
                        @empty
                            <div class="leave-empty">
                                <div class="leave-empty-title">Belum ada jadwal yang bisa dipilih</div>
                                <div class="leave-empty-sub">Kalau jadwal belum muncul, tunggu jadwal kerja berikutnya diisi lebih dulu.</div>
                            </div>
                        @endforelse
                    </div>
                    <div class="schedule-actions">
                        <button class="btn-primary" type="button" onclick="closeSchedulePicker()">Selesai</button>
                    </div>
                </div>
            </div>
        </form>
        </div>

        <div class="panel leave-history-panel" id="leave-history">
        <div class="leave-section-head">
            <div>
                <h2 class="leave-section-title">Riwayat Pengajuan</h2>
                <div class="leave-section-sub">Cek status pengajuan, lalu lanjut ke chat kalau ingin melihat balasan admin lebih lengkap.</div>
            </div>
            <span class="pill gray">{{ $pendingRows }} diproses  -  {{ $rejectedRows }} ditolak</span>
        </div>

        <div class="leave-cards">
            @forelse($rows as $row)
                @php
                    $status = (string) ($row->status ?? 'pending');
                    $statusClass = $status === 'approved' ? 'ok' : ($status === 'rejected' ? 'bad' : 'warn');
                    $latest = $lastMessages[(int) $row->id] ?? null;
                @endphp
                <div class="leave-card">
                    <div class="leave-head">
                        <div>
                            <div class="leave-title">{{ strtoupper((string) $row->jenis) }}</div>
                            <div class="leave-meta">{{ $row->tanggal_awal?->format('Y-m-d') ?? '-' }} s/d {{ $row->tanggal_akhir?->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <span class="pill {{ $statusClass }}">{{ strtoupper($status) }}</span>
                    </div>
                    <div class="leave-card-note">
                        {{ $latest ? \Illuminate\Support\Str::limit($latest->message, 110) : 'Belum ada balasan tambahan di percakapan ini.' }}
                    </div>
                    <div class="leave-card-actions">
                        <a class="btn-neutral btn-mini" href="{{ route('staff.messages.show', ['type' => 'leave', 'id' => $row->id]) }}">Buka Pesan</a>
                    </div>
                </div>
            @empty
                <div class="history-empty">
                    <div class="history-empty-title">Belum ada pengajuan</div>
                    <div class="history-empty-sub">Setelah kamu mengirim izin atau sakit, riwayatnya akan muncul di sini.</div>
                </div>
            @endforelse
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Jenis</th>
                        <th>Periode</th>
                        <th>Status</th>
                        <th>Update Terakhir</th>
                        <th>Pesan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $status = (string) ($row->status ?? 'pending');
                            $statusClass = $status === 'approved' ? 'ok' : ($status === 'rejected' ? 'bad' : 'warn');
                            $latest = $lastMessages[(int) $row->id] ?? null;
                        @endphp
                        <tr>
                            <td>{{ strtoupper((string) $row->jenis) }}</td>
                            <td>{{ $row->tanggal_awal?->format('Y-m-d') ?? '-' }} s/d {{ $row->tanggal_akhir?->format('Y-m-d') ?? '-' }}</td>
                            <td><span class="pill {{ $statusClass }}">{{ strtoupper($status) }}</span></td>
                            <td>{{ $latest ? \Illuminate\Support\Str::limit($latest->message, 70) : '-' }}</td>
                            <td>
                                <a class="btn-neutral btn-mini" href="{{ route('staff.messages.show', ['type' => 'leave', 'id' => $row->id]) }}">Buka</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="u-color-muted">Belum ada pengajuan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
    </div>
</div>
<script>
    function openSchedulePicker() {
        const modal = document.getElementById('scheduleModal');
        if (modal) {
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        }
        return false;
    }

    function closeSchedulePicker() {
        const modal = document.getElementById('scheduleModal');
        if (modal) {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }
        refreshSelectedDates();
        return false;
    }

    function refreshSelectedDates() {
        const container = document.getElementById('selectedDates');
        if (!container) return;

        const checked = Array.from(document.querySelectorAll('input[name="tanggal_pilihan[]"]:checked'));
        if (checked.length === 0) {
            container.innerHTML = '<span class="selected-empty">Belum ada tanggal dipilih.</span>';
            return;
        }

        const pills = checked.map(function (element) {
            return '<span class="pill gray">' + element.value + '</span>';
        });
        container.innerHTML = pills.join('');
    }

    document.addEventListener('DOMContentLoaded', function () {
        refreshSelectedDates();
    });
</script>
@endsection


