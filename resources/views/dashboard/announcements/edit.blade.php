@extends('layouts.app')

@section('title', 'Edit Pengumuman')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Pengumuman</div>
            <h1>Edit Pengumuman</h1>
            <p>Perbarui konten, target, atau status.</p>
        </div>
    </div>

    <div class="panel">
        @if ($errors->any())
            <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif

        <form method="post" action="{{ route('dashboard.announcements.update', $announcement) }}" enctype="multipart/form-data" class="form-grid">
            @csrf
            @method('put')
            <div class="field">
                <label>Judul</label>
                <input type="text" name="title" value="{{ old('title', $announcement->title) }}" required>
            </div>
            <div class="field">
                <label>Isi Pengumuman</label>
                <textarea name="body" required>{{ old('body', $announcement->body) }}</textarea>
            </div>
            <div class="field">
                <label>Target Jabatan</label>
                <select name="target_role">
                    <option value="">Semua</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" @selected(old('target_role', $announcement->target_role) === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Gambar (opsional)</label>
                <input type="file" name="image" accept="image/*">
                @if($announcement->image_path)
                    <div class="preview">
                        <img src="{{ asset('storage/' . $announcement->image_path) }}" alt="Poster">
                    </div>
                @endif
            </div>
            <div class="field">
                <label>Jadwal Tayang</label>
                <input type="datetime-local" name="published_at" value="{{ old('published_at', $announcement->published_at ? $announcement->published_at->format('Y-m-d\TH:i') : '') }}">
            </div>
            <div class="field">
                <label>
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $announcement->is_active))>
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
