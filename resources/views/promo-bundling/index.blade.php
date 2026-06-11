@extends('layouts.app')

@section('title', 'Promo Bundling')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Promo Bundling</h1>
            <p>Kelola bundling sebagai paket siap jual dengan harga khusus.</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-primary" href="{{ route('bundling.create') }}">+ Bundling</a>
            <a class="btn-neutral" href="{{ route('diskon.index') }}">Kembali ke Diskon</a>
        </div>
    </div>

    <div class="panel">
        @if(session('success')) <div class="alert ok">{{ session('success') }}</div> @endif
        @if($errors->any()) <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama Promo</th>
                        <th>Isi Bundling</th>
                        <th class="num">Harga Bundle</th>
                        <th>Status</th>
                        <th>Periode</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($promo as $item)
                    <tr>
                        <td>{{ $item->nama_promo }}</td>
                        <td>
                            @foreach($item->items as $row)
                                <div>{{ $row->qty }}x {{ $row->produk?->nama_produk ?? '-' }}</div>
                            @endforeach
                        </td>
                        <td class="num">Rp {{ number_format((float) $item->harga_bundle, 0, ',', '.') }}</td>
                        <td>
                            @if($item->isAktifPada(now()))
                                <span class="badge on">Aktif</span>
                            @else
                                <span class="badge off">{{ $item->status_aktif ? 'Tidak Berlaku' : 'Nonaktif' }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="period-line">
                                {{ $item->tanggal_mulai?->format('Y-m-d') ?? '-' }}
                                s/d
                                {{ $item->tanggal_selesai?->format('Y-m-d') ?? '-' }}
                            </span>
                        </td>
                        <td>
                            <div class="aksi">
                                <a class="btn-neutral btn-mini" href="{{ route('bundling.edit', $item) }}">Edit</a>
                                <form class="inline" method="post" action="{{ route('bundling.destroy', $item) }}"
                                      onsubmit="return confirm('Hapus promo bundling ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger btn-mini" type="submit">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="u-text-muted">Belum ada promo bundling.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
