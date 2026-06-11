@extends('layouts.app')

@section('title', 'Buat Pengumuman')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Pengumuman</div>
            <h1>Buat Pengumuman</h1>
            <p>Pengumuman akan tampil di portal karyawan dan bisa dibaca ulang.</p>
        </div>
    </div>

    <div class="panel">
        @if ($errors->any())
            <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif

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
            <div class="actions">
                <button class="btn-primary" type="submit">Simpan</button>
                <a class="btn-neutral" href="{{ route('dashboard.announcements.index') }}">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection
