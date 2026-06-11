@extends('layouts.app')

@section('title', 'Profil Cafe')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Pengaturan</div>
            <h1>Profil Cafe</h1>
            <p>Atur identitas cafe untuk ditampilkan di portal dan kebutuhan branding.</p>
        </div>
    </div>

    <div class="panel">
        @if(session('success')) <div class="alert ok">{{ session('success') }}</div> @endif
        @if($errors->any()) <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif

        <div class="panel-grid">
            <form method="post" action="{{ route('cafe_profile.update') }}" enctype="multipart/form-data" class="form-grid">
                @csrf
                @method('put')

                <div>
                    <label>Nama Cafe</label>
                    <input type="text" name="nama_cafe" value="{{ old('nama_cafe', $profile->nama_cafe) }}" required>
                </div>
                <div>
                    <label>Tagline</label>
                    <input type="text" name="tagline" value="{{ old('tagline', $profile->tagline) }}" placeholder="Contoh: Kopi & cerita">
                </div>
                <div class="row2">
                    <div>
                        <label>Telepon</label>
                        <input type="text" name="telepon" value="{{ old('telepon', $profile->telepon) }}" placeholder="08xxxx">
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="{{ old('email', $profile->email) }}" placeholder="halo@cafe.com">
                    </div>
                </div>
                <div class="row2">
                    <div>
                        <label>Instagram</label>
                        <input type="text" name="instagram" value="{{ old('instagram', $profile->instagram) }}" placeholder="@cafe">
                    </div>
                    <div>
                        <label>Website</label>
                        <input type="text" name="website" value="{{ old('website', $profile->website) }}" placeholder="https://">
                    </div>
                </div>
                <div>
                    <label>Alamat</label>
                    <input type="text" name="alamat" value="{{ old('alamat', $profile->alamat) }}">
                </div>
                <div>
                    <label>Kota</label>
                    <input type="text" name="kota" value="{{ old('kota', $profile->kota) }}">
                </div>
                <div>
                    <label>Deskripsi</label>
                    <textarea name="deskripsi">{{ old('deskripsi', $profile->deskripsi) }}</textarea>
                </div>
                <div>
                    <label>Logo Cafe</label>
                    <input type="file" name="logo" accept="image/*">
                    <div class="muted u-mt-6">Jika upload logo baru, logo sebelumnya otomatis diganti.</div>
                </div>
                <div class="actions">
                    <button class="btn-primary" type="submit">Simpan Profil</button>
                    <a class="btn-neutral" href="{{ route('dashboard.index') }}">Kembali</a>
                </div>
            </form>

            <div>
                <div class="preview">
                    @if(!empty($profile->logo_path))
                        <img src="{{ asset('storage/' . $profile->logo_path) }}" alt="Logo Cafe">
                    @endif
                    <h3>{{ $profile->nama_cafe }}</h3>
                    <div class="sub">{{ $profile->tagline ?: 'Tagline belum diisi' }}</div>
                </div>
                <div class="card-note u-mt-10">
                    Profil cafe digunakan untuk branding di portal dan tampilan informasi. Logo akan disimpan di storage publik.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
