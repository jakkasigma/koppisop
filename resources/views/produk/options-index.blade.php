@extends('layouts.app')

@section('title', 'Pengaturan Opsi Kasir')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <div>
            <h1>Pengaturan Opsi Kasir</h1>
            
        </div>
        <div class="action">
            <a class="btn-neutral" href="{{ route('produk.index') }}">Kembali ke Produk</a>
        </div>
    </div>
    </div>

    <div class="panel">
        @if (session('success'))
            <div class="alert ok">{{ session('success') }}</div>
        @endif

        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Produk</th><th>Kategori</th><th>Opsi Aktif</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($produk as $item)
                    <tr>
                        <td>{{ $item->id_produk }}</td>
                        <td>{{ $item->nama_produk }}</td>
                        <td>{{ $item->kategori?->nama_kategori ?? '-' }}</td>
                        <td>
                            @if($item->is_temperature_enabled)<span class="pill">Suhu</span>@endif
                            @if($item->is_sugar_enabled)<span class="pill">Sugar</span>@endif
                            @if($item->is_cup_size_enabled)<span class="pill">Cup</span>@endif
                            @if($item->is_spicy_enabled)<span class="pill">Spicy</span>@endif
                            @if(! $item->is_temperature_enabled && ! $item->is_sugar_enabled && ! $item->is_cup_size_enabled && ! $item->is_spicy_enabled)
                                <span>-</span>
                            @endif
                        </td>
                        <td>
                            <a class="btn-primary" href="{{ route('produk.options.edit', $item) }}">Atur Opsi</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada data produk.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

