@extends('layouts.app')

@section('title', 'Master Pelanggan')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <div>
            <h1>Master Pelanggan</h1>
            
        </div>
    </div>
    </div>
    <div class="panel">
        <div class="panel-actions">
            <button class="btn-primary" type="button" id="openPelangganModal">+ Pelanggan</button>
        </div>
        @if(session('success')) <div class="alert ok">{{ session('success') }}</div> @endif
        @if($errors->any()) <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Nama</th><th>IG</th><th>Telepon</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($pelanggan as $item)
                    <tr>
                        <td>{{ $item->id_pelanggan }}</td>
                        <td>{{ $item->nama }}</td>
                        <td>{{ $item->username_ig }}</td>
                        <td>{{ $item->no_telepon }}</td>
                        <td>
                            <a class="btn-neutral" href="{{ route('pelanggan.edit', $item) }}">Edit</a>
                            <form class="inline" method="post" action="{{ route('pelanggan.destroy', $item) }}" onsubmit="return confirm('Hapus pelanggan ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn-danger" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada data pelanggan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="pelangganModal" aria-hidden="true" data-open="{{ old('nama') ? '1' : '0' }}">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Tambah Pelanggan">
        <div class="modal-head">
            <h3 class="modal-title">Tambah Pelanggan</h3>
        </div>
        <div class="modal-body">
            <form method="post" action="{{ route('pelanggan.store') }}" class="form-grid">
                @csrf
                <div class="full">
                    <label>Nama</label>
                    <input type="text" name="nama" value="{{ old('nama') }}" required>
                </div>
                <div>
                    <label>Username Instagram</label>
                    <input type="text" name="username_ig" value="{{ old('username_ig') }}">
                </div>
                <div>
                    <label>No Telepon</label>
                    <input type="text" name="no_telepon" value="{{ old('no_telepon') }}">
                </div>
                <div class="actions full">
                    <button class="btn-primary" type="submit">Simpan</button>
                    <button class="btn-neutral" type="button" id="cancelPelangganModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const openBtn = document.getElementById('openPelangganModal');
    const modal = document.getElementById('pelangganModal');
    const cancelBtn = document.getElementById('cancelPelangganModal');
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

