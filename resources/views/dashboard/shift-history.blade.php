@extends('layouts.app')

@section('title', 'Riwayat Shift Kasir')
@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <h1>Riwayat Shift Kasir</h1>
        <p>Monitoring pembukaan dan penutupan shift kasir.</p>
        
    </div>

    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Kasir</th>
                    <th>Shift</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                    <th>Kas Awal</th>
                    <th>Omzet</th>
                    <th>Cash</th>
                    <th>Delivery</th>
                    <th>Pengeluaran</th>
                    <th>Trx</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>#{{ $row->id }}</td>
                        <td>{{ $row->user?->name ?? '-' }}</td>
                        <td>{{ (int) $row->shift_ke }}</td>
                        <td>{{ $row->started_at }}</td>
                        <td>{{ $row->ended_at ?? '-' }}</td>
                        <td>Rp {{ number_format((float) $row->kas_awal, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $row->total_omzet, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $row->total_cash, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) ($row->total_delivery ?? 0), 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $row->total_pengeluaran, 0, ',', '.') }}</td>
                        <td>{{ number_format((int) $row->total_trx, 0, ',', '.') }}</td>
                        <td><a class="btn-neutral" href="{{ route('kasir.shift.struk', ['shift' => $row->id]) }}" target="_blank" rel="noopener">Struk</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="muted">Belum ada data shift.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pages">{{ $rows->links() }}</div>
        @if($rows->lastPage() > 1)
            <div class="page-jump-wrap">
                <form class="page-jump-form" method="get" action="{{ route('dashboard.shift_history') }}">
                    <label for="shiftPageJumpInput">Halaman</label>
                    <input
                        id="shiftPageJumpInput"
                        type="number"
                        name="page"
                        min="1"
                        max="{{ $rows->lastPage() }}"
                        value="{{ min(max((int) request('page', $rows->currentPage()), 1), $rows->lastPage()) }}"
                    >
                    <button class="btn-primary" type="submit">Buka</button>
                    <span class="hint u-m-0">Maks {{ $rows->lastPage() }}</span>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection

