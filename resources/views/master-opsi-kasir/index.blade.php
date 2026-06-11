@extends('layouts.app')

@section('title', 'Master Opsi Kasir')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Master Opsi Kasir</h1>
            <p>Kelola opsi pilihan dinamis yang bisa digunakan produk di kasir.</p>
        </div>
        <div class="admin-page-actions">
            <button class="btn-primary" type="button" id="openOpsiModal">+ Tambah Opsi</button>
            <a class="btn-neutral" href="{{ route('produk.index') }}">Kembali ke Produk</a>
        </div>
    </div>

    <div class="panel">
        @if (session('success'))
            <div class="alert ok">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert err">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Urutan</th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Wajib</th>
                        <th class="num">Jml Opsi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>{{ $item->urutan }}</td>
                        <td><code>{{ $item->kode_opsi }}</code></td>
                        <td>{{ $item->nama_opsi }}</td>
                        <td>
                            <span class="badge {{ $item->is_active ? 'on' : 'off' }}">
                                {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $item->is_required ? 'on' : 'off' }}">
                                {{ $item->is_required ? 'Wajib' : 'Opsional' }}
                            </span>
                        </td>
                        <td class="num">{{ count($item->resolvedOptions()) }}</td>
                        <td>
                            <div class="aksi">
                                <a class="btn-neutral btn-mini" href="{{ route('master_opsi_kasir.edit', $item) }}">Edit</a>
                                <form class="inline" method="post" action="{{ route('master_opsi_kasir.destroy', $item) }}"
                                      onsubmit="return confirm('Hapus master opsi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger btn-mini" type="submit">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="u-text-muted">Belum ada master opsi kasir.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="opsiModal" aria-hidden="true" data-open="{{ old('nama_opsi') ? '1' : '0' }}">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Tambah Master Opsi Kasir">
        <div class="modal-head">
            <h3 class="modal-title">Tambah Master Opsi Kasir</h3>
        </div>
        <div class="modal-body">
            <form method="post" action="{{ route('master_opsi_kasir.store') }}" class="form-grid">
                @csrf
                <div>
                    <label>Nama Opsi</label>
                    <input type="text" name="nama_opsi" value="{{ old('nama_opsi') }}" required>
                </div>
                <div>
                    <label>Kode Opsi (opsional)</label>
                    <input type="text" name="kode_opsi" value="{{ old('kode_opsi') }}">
                </div>
                <div>
                    <label>Urutan</label>
                    <input type="number" name="urutan" min="0" value="{{ old('urutan', 0) }}">
                </div>
                <div class="full">
                    <label class="check">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <span>Aktif</span>
                    </label>
                </div>
                <div class="full">
                    <label class="check">
                        <input type="checkbox" name="is_required" value="1" {{ old('is_required') ? 'checked' : '' }}>
                        <span>Wajib dipilih saat produk menggunakan opsi ini</span>
                    </label>
                </div>
                <div class="full">
                    <label>Daftar Pilihan</label>
                    <textarea name="opsi_text" rows="5" placeholder="tanpa_saos|Tanpa Saos|0&#10;extra_saos|Extra Saos|2000" required>{{ old('opsi_text') }}</textarea>
                    <div class="hint">Format: value|Label|ExtraHarga (1 baris 1 opsi).</div>
                </div>
                <div class="actions full">
                    <button class="btn-primary" type="submit">Simpan</button>
                    <button class="btn-neutral" type="button" id="cancelOpsiModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const openBtn = document.getElementById('openOpsiModal');
    const modal = document.getElementById('opsiModal');
    const cancelBtn = document.getElementById('cancelOpsiModal');
    if (!openBtn || !modal) return;
    const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); };
    const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); };
    openBtn.addEventListener('click', open);
    cancelBtn?.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    if (modal.dataset.open === '1') open();
})();
</script>
@endsection

