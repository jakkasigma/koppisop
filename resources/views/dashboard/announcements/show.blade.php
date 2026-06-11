@extends('layouts.app')

@section('title', 'Detail Pengumuman')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Pengumuman</div>
            <h1>{{ $announcement->title }}</h1>
            <p>Detail pengumuman dan daftar pembaca.</p>
        </div>
    </div>

    <div class="panel">
        <div class="meta">
            <span class="pill">Target: {{ $announcement->target_role ?? 'Semua' }}</span>
            <span class="pill">Tayang: {{ $announcement->published_at ? $announcement->published_at->format('Y-m-d H:i') : '-' }}</span>
            <span class="pill ok">{{ $announcement->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
        </div>
        <div class="u-mt-10 u-pre-line">{{ $announcement->body }}</div>
        @if($announcement->image_path)
            <div class="poster">
                <img src="{{ asset('storage/' . $announcement->image_path) }}" alt="Poster">
            </div>
        @endif
    </div>

    <div class="panel u-mt-10">
        <a class="btn-neutral" href="{{ route('dashboard.announcements.index') }}">Kembali ke Pengumuman</a>
    </div>

    <div class="panel u-mt-12">
        <h2 class="u-m-0 u-mb-8">Read Receipt</h2>
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
                    @forelse($readers as $r)
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
@endsection

