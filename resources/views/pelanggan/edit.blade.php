@extends('layouts.app')

@section('title', 'Edit Pelanggan')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <div class="hero-split">
            <div>
                <h1>Edit Pelanggan</h1>
                <p class="hero-sub">Perbarui data pelanggan agar data transaksi akurat.</p>
                
            </div>
            <div class="hero-side">
                <a class="btn-ghost" href="{{ route('pelanggan.index') }}">Kembali</a>
            </div>
        </div>
    </div>
    </div>

    <div class="panel form-panel">
        @if($errors->any())
            <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <form method="post" action="{{ route('pelanggan.update', $pelanggan) }}" class="form-grid">
            @csrf
            @method('PUT')
            <div class="full"><label>Nama</label><input type="text" name="nama" value="{{ old('nama', $pelanggan->nama) }}" required></div>
            <div><label>Username Instagram</label><input type="text" name="username_ig" value="{{ old('username_ig', $pelanggan->username_ig) }}"></div>
            <div><label>No Telepon</label><input type="text" name="no_telepon" value="{{ old('no_telepon', $pelanggan->no_telepon) }}"></div>
            <div class="actions full">
                <button class="btn-primary" type="submit">Update</button>
                <a class="btn-neutral" href="{{ route('pelanggan.index') }}">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection
