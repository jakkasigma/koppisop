@extends('layouts.app')

@section('title', 'Pengumuman')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Operasional</div>
            <h1>Pengumuman</h1>
            <p>Kelola informasi staf, promo internal, dan update operasional per jabatan.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip soft">Aktif: {{ $items->where('is_active', true)->count() }}</span>
            <span class="admin-chip">Nonaktif: {{ $items->where('is_active', false)->count() }}</span>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Tabel Pengumuman</h3>
                <div class="panel-sub">Status promo otomatis mengikuti periode. Non‑promo mengikuti aktif/nonaktif.</div>
            </div>
            <div class="panel-actions">
                <button class="btn-primary" type="button" id="openAnnouncementModal">+ Pengumuman</button>
            </div>
        </div>
        <div class="note">
            Filter di bawah mengikuti status promo: Terjadwal, Aktif, atau Berakhir.
            Promo yang berakhir tetap muncul di dashboard selama 3 hari.
        </div>
        <div class="tabs">
            <a class="tab-on {{ in_array(($status ?? 'all'), ['aktif', 'berjalan'], true) ? 'active' : '' }}" href="{{ route('dashboard.announcements.index', ['status' => 'aktif']) }}">Aktif</a>
            <a class="tab-info {{ ($status ?? '') === 'terjadwal' ? 'active' : '' }}" href="{{ route('dashboard.announcements.index', ['status' => 'terjadwal']) }}">Terjadwal</a>
            <a class="tab-off {{ ($status ?? '') === 'berakhir' ? 'active' : '' }}" href="{{ route('dashboard.announcements.index', ['status' => 'berakhir']) }}">Berakhir</a>
            <a class="{{ ($status ?? '') === 'all' ? 'active' : '' }}" href="{{ route('dashboard.announcements.index', ['status' => 'all']) }}">Semua</a>
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
                        <th>Judul</th>
                        <th class="u-w-140">Target</th>
                        <th class="u-w-140">Status</th>
                        <th class="u-w-120">Dibaca</th>
                        <th class="u-w-200"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->title }}</strong>
                                <div class="sub">{{ $item->published_at ? $item->published_at->format('Y-m-d H:i') : '-' }}</div>
                            </td>
                            <td>{{ $item->target_role ?? 'Semua' }}</td>
                            <td>
                                @php
                                    $promoStatus = $item->promo_status ?? null;
                                    $promoClass = match ($promoStatus) {
                                        'Aktif', 'Berjalan' => 'active',
                                        'Terjadwal' => 'warn',
                                        'Berakhir' => 'inactive',
                                        default => null,
                                    };
                                @endphp
                                @if($promoStatus)
                                    <span class="status {{ $promoClass }}">{{ strtoupper($promoStatus) }}</span>
                                @else
                                    <span class="status {{ $item->is_active ? 'active' : 'inactive' }}">
                                        {{ $item->is_active ? 'AKTIF' : 'NONAKTIF' }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ (int) ($readCounts[$item->id] ?? 0) }}</td>
                            <td>
                                <div class="action-buttons">
                                    <a class="btn-neutral" href="{{ route('dashboard.announcements.show', $item) }}" data-announcement-detail="{{ $item->id }}">Detail</a>
                                    <a class="btn-neutral" href="{{ route('dashboard.announcements.edit', $item) }}">Edit</a>
                                    <form method="post" action="{{ route('dashboard.announcements.destroy', $item) }}" class="u-inline">
                                        @csrf
                                        @method('delete')
                                        <button class="btn-neutral danger" type="submit" onclick="return confirm('Hapus pengumuman ini?')">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="sub">Belum ada pengumuman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="announcementModal" aria-hidden="true" data-open="{{ old('title') ? '1' : '0' }}">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Buat Pengumuman">
        <div class="modal-head">
            <h3 class="modal-title">Buat Pengumuman</h3>
        </div>
        <div class="modal-body">
            <form method="post" action="{{ route('dashboard.announcements.store') }}" enctype="multipart/form-data" class="form-grid">
                @csrf
                <div class="field">
                    <label>Judul</label>
                    <input type="text" name="title" value="{{ old('title') }}" required>
                </div>
                <div class="field">
                    <label>Isi Pengumuman</label>
                    <textarea name="body" required>{{ old('body') }}</textarea>
                </div>
                <div class="field">
                    <label>Target Jabatan</label>
                    <select name="target_role">
                        <option value="">Semua</option>
                        @foreach($roles as $role)
                            <option value="{{ $role }}" @selected(old('target_role') === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Gambar (opsional)</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div class="field">
                    <label>Jadwal Tayang</label>
                    <input type="datetime-local" name="published_at" value="{{ old('published_at') }}">
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        Aktif
                    </label>
                </div>
                <div class="actions-inline">
                    <button class="btn-primary" type="submit">Simpan</button>
                    <button class="btn-neutral" type="button" id="cancelAnnouncementModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-backdrop detail-modal" id="announcementDetailModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Detail Pengumuman">
        <div class="modal-head">
            <h3 class="modal-title">Detail Pengumuman</h3>
        </div>
        <div class="modal-body">
            @foreach($items as $item)
                @php
                    $detailReaders = $readersByAnnouncement[$item->id] ?? [];
                @endphp
                <div class="detail-panel" data-announcement-panel="{{ $item->id }}" hidden>
                    <div class="detail-header">
                        <div>
                            <h2 class="detail-title">{{ $item->title }}</h2>
                            <div class="detail-meta">
                                <span class="detail-pill">Target: {{ $item->target_role ?? 'Semua' }}</span>
                                <span class="detail-pill">Tayang: {{ $item->published_at ? $item->published_at->format('Y-m-d H:i') : '-' }}</span>
                                <span class="detail-pill ok">{{ $item->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                            </div>
                        </div>
                        <span class="detail-pill">Dibaca: {{ (int) ($readCounts[$item->id] ?? 0) }}</span>
                    </div>
                    <div class="detail-body u-pre-line">{{ $item->body }}</div>
                    @if($item->image_path)
                        <div class="detail-poster">
                            <img src="{{ asset('storage/' . $item->image_path) }}" alt="Poster">
                        </div>
                    @endif
                    <div class="detail-section">
                        <h3>Read Receipt</h3>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th class="u-w-160">Jabatan</th>
                                        <th class="u-w-180">Dibaca</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($detailReaders as $r)
                                        <tr>
                                            <td>{{ $r->nama_karyawan ?? '-' }}</td>
                                            <td>{{ $r->jabatan ?? '-' }}</td>
                                            <td>{{ $r->read_at ? \Illuminate\Support\Carbon::parse($r->read_at)->format('Y-m-d H:i') : '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="sub">Belum ada yang membaca.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="detail-footer">
                <button class="btn-neutral" type="button" id="closeAnnouncementDetail">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const openBtn = document.getElementById('openAnnouncementModal');
    const modal = document.getElementById('announcementModal');
    const cancelBtn = document.getElementById('cancelAnnouncementModal');
    if (!openBtn || !modal) return;
    const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); };
    const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); };
    openBtn.addEventListener('click', open);
    cancelBtn?.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    if (modal.dataset.open === '1') open();
})();
</script>
<script>
(() => {
    const detailModal = document.getElementById('announcementDetailModal');
    const closeBtn = document.getElementById('closeAnnouncementDetail');
    const openers = document.querySelectorAll('[data-announcement-detail]');
    if (!detailModal || openers.length === 0) return;
    const panels = detailModal.querySelectorAll('[data-announcement-panel]');
    const openDetail = (id) => {
        panels.forEach(panel => {
            panel.hidden = panel.dataset.announcementPanel !== id;
        });
        detailModal.classList.add('show');
        detailModal.setAttribute('aria-hidden', 'false');
    };
    const closeDetail = () => {
        detailModal.classList.remove('show');
        detailModal.setAttribute('aria-hidden', 'true');
    };
    openers.forEach(btn => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const id = btn.dataset.announcementDetail;
            if (id) openDetail(id);
        });
    });
    closeBtn?.addEventListener('click', closeDetail);
    detailModal.addEventListener('click', (event) => {
        if (event.target === detailModal) closeDetail();
    });
})();
</script>
@endsection



