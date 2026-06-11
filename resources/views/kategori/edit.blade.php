@extends('layouts.app')

@section('title', 'Edit Kategori')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <div class="hero-split">
            <div>
                <h1>Edit Kategori</h1>
                <p class="hero-sub">Perbarui kategori agar struktur menu tetap rapi.</p>
                
            </div>
            <div class="hero-side">
                <a class="btn-ghost" href="{{ route('kategori.index') }}">Kembali</a>
            </div>
        </div>
    </div>
    </div>

    <div class="panel form-panel">
        @if ($errors->any())
            <div class="alert err">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <form method="post" action="{{ route('kategori.update', $kategori) }}" class="form-grid">
            @csrf
            @method('PUT')
            <div>
                <label>Nama Kategori</label>
                <input type="text" name="nama_kategori" value="{{ old('nama_kategori', $kategori->nama_kategori) }}" required>
            </div>
            <div>
                <label>Deskripsi</label>
                <textarea name="deskripsi" rows="4">{{ old('deskripsi', $kategori->deskripsi) }}</textarea>
            </div>
            <div class="actions">
                <button class="btn-primary" type="submit">Update</button>
                <a class="btn-neutral" href="{{ route('kategori.index') }}">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection
