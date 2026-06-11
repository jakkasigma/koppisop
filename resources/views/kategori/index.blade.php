@extends('layouts.app')

@section('title', 'Master Kategori')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <div>
            <h1>Master Kategori</h1>
            
        </div>
    </div>
    </div>
    <div class="panel">
        <div class="panel-actions">
            <button class="btn-primary" type="button" id="openKategoriModal">+ Kategori</button>
        </div>
        @if (session('success')) <div class="alert ok">{{ session('success') }}</div> @endif
        @if ($errors->any())
            <div class="alert err">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Nama</th><th>Deskripsi</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($kategori as $item)
                    <tr>
                        <td>{{ $item->id_kategori }}</td>
                        <td>{{ $item->nama_kategori }}</td>
                        <td>{{ $item->deskripsi }}</td>
                        <td>
                            <a class="btn-neutral" href="{{ route('kategori.edit', $item) }}">Edit</a>
                            <form class="inline" method="post" action="{{ route('kategori.destroy', $item) }}" onsubmit="return confirm('Hapus kategori ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn-danger" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada data kategori.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="kategoriModal" aria-hidden="true" data-open="{{ old('nama_kategori') ? '1' : '0' }}">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Tambah Kategori">
        <div class="modal-head">
            <h3 class="modal-title">Tambah Kategori</h3>
        </div>
        <div class="modal-body">
            <form method="post" action="{{ route('kategori.store') }}" class="form-grid">
                @csrf
                <div>
                    <label>Nama Kategori</label>
                    <input type="text" name="nama_kategori" value="{{ old('nama_kategori') }}" required>
                </div>
                <div>
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" rows="3">{{ old('deskripsi') }}</textarea>
                </div>
                <div class="actions">
                    <button class="btn-primary" type="submit">Simpan</button>
                    <button class="btn-neutral" type="button" id="cancelKategoriModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const openBtn = document.getElementById('openKategoriModal');
    const modal = document.getElementById('kategoriModal');
    const cancelBtn = document.getElementById('cancelKategoriModal');
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

