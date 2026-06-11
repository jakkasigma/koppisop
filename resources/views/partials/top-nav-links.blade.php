@php
    $user = auth()->user();
    $setting = \App\Models\StrukSetting::current();
    $isKasirRoute = request()->routeIs('kasir.*') || request()->routeIs('admin.kasir.*');
    $isAdmin = $user?->role === 'admin';
@endphp

@if($isAdmin && !$isKasirRoute)
    {{-- Menu dinonaktifkan untuk admin karena sudah menggunakan sidebar di sisi kiri. --}}
@elseif($isAdmin && $isKasirRoute)
    <div class="nav-section">
        <p class="nav-section-title">Kasir Admin</p>
        <div class="nav-links">
            <a class="{{ request()->routeIs('admin.kasir.index') ? 'active' : '' }}" href="{{ route('admin.kasir.index') }}">Kasir</a>
            <a class="{{ request()->routeIs('transaksi.*') ? 'active' : '' }}" href="{{ route('transaksi.index') }}">Transaksi</a>
            <a href="{{ route('dashboard.index') }}">Kembali ke Dashboard</a>
        </div>
    </div>
@else
    <div class="nav-section">
        <p class="nav-section-title">Kasir</p>
        <div class="nav-links">
            <a class="{{ request()->routeIs('kasir.index') ? 'active' : '' }}" href="{{ route('kasir.index') }}">Kasir</a>
            <a class="{{ request()->routeIs('transaksi.*') ? 'active' : '' }}" href="{{ route('transaksi.index') }}">Transaksi</a>
            <a class="{{ request()->routeIs('kasir.absen.*') ? 'active' : '' }}" href="{{ route('kasir.absen.form') }}">Absensi</a>
        </div>
    </div>
@endif
