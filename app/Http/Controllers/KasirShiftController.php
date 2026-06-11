<?php

namespace App\Http\Controllers;

use App\Models\KasirShiftSession;
use App\Models\KasirShiftPengeluaran;
use App\Models\Pesanan;
use App\Models\StrukSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KasirShiftController extends Controller
{
    public function start(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'kasir') {
            return redirect()->route('kasir.index');
        }

        $activeShift = KasirShiftSession::query()
            ->forUser((int) $user->id)
            ->active()
            ->latest('started_at')
            ->first();

        if ($activeShift) {
            return redirect()->route('kasir.index');
        }

        $activeShiftCount = $this->resolveActiveShiftCount();
        $lastShiftNo = KasirShiftSession::query()
            ->forUser((int) $user->id)
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->value('shift_ke');
        $lastShiftNo = (int) ($lastShiftNo ?? 0);
        $suggestedShift = $lastShiftNo > 0 ? $lastShiftNo + 1 : 1;
        if ($suggestedShift > $activeShiftCount) {
            $suggestedShift = 1;
        }

        return view('kasir.shift-start', [
            'kasAwalSistem' => $this->resolveSystemKasAwal(),
            'activeShiftCount' => $activeShiftCount,
            'suggestedShift' => $suggestedShift,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'kasir') {
            return redirect()->route('kasir.index');
        }

        $activeShiftCount = $this->resolveActiveShiftCount();
        $allowedShift = range(1, $activeShiftCount);
        $data = $request->validate([
            'shift_ke' => ['required', Rule::in($allowedShift)],
        ]);
        $kasAwalSistem = $this->resolveSystemKasAwal();

        KasirShiftSession::query()
            ->forUser((int) $user->id)
            ->active()
            ->update(['ended_at' => now()]);

        KasirShiftSession::create([
            'user_id' => (int) $user->id,
            'shift_ke' => (int) $data['shift_ke'],
            'kas_awal' => $kasAwalSistem,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        return redirect()
            ->route('kasir.index')
            ->with('success', 'Shift berhasil dimulai. Selamat bekerja.');
    }

    public function closePreview(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'kasir') {
            return redirect()->route('kasir.index');
        }

        $activeShift = $this->activeShiftForUser((int) $user->id);
        if (! $activeShift) {
            return redirect()->route('kasir.shift.start')->withErrors([
                'shift' => 'Belum ada shift aktif untuk ditutup.',
            ]);
        }

        $summary = $this->buildShiftSummary($activeShift, now());

        return view('kasir.shift-close', [
            'shift' => $activeShift,
            'summary' => $summary,
        ]);
    }

    public function report(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'kasir') {
            return redirect()->route('kasir.index');
        }

        $activeShift = $this->activeShiftForUser((int) $user->id);
        if (! $activeShift) {
            return redirect()->route('kasir.shift.start')->withErrors([
                'shift' => 'Belum ada shift aktif.',
            ]);
        }

        $pengeluaran = KasirShiftPengeluaran::query()
            ->with('user')
            ->where('kasir_shift_session_id', (int) $activeShift->id)
            ->orderByDesc('pengeluaran_at')
            ->orderByDesc('id')
            ->get();

        return view('kasir.shift-report', [
            'shift' => $activeShift,
            'summary' => $this->buildShiftSummary($activeShift, now()),
            'pengeluaran' => $pengeluaran,
        ]);
    }

    public function addExpense(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'kasir') {
            return redirect()->route('kasir.index');
        }

        $data = $request->validate([
            'nominal' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:200'],
        ]);

        $activeShift = $this->activeShiftForUser((int) $user->id);
        if (! $activeShift) {
            return redirect()->route('kasir.shift.start')->withErrors([
                'shift' => 'Belum ada shift aktif.',
            ]);
        }

        KasirShiftPengeluaran::create([
            'kasir_shift_session_id' => (int) $activeShift->id,
            'user_id' => (int) $user->id,
            'nominal' => (float) $data['nominal'],
            'keterangan' => trim((string) ($data['keterangan'] ?? '')) ?: null,
            'pengeluaran_at' => now(),
        ]);

        return redirect()
            ->route('kasir.shift.report')
            ->with('success', 'Pengeluaran shift berhasil ditambahkan.');
    }

    public function deleteExpense(Request $request, KasirShiftPengeluaran $expense): RedirectResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'kasir') {
            return redirect()->route('kasir.index');
        }

        $activeShift = $this->activeShiftForUser((int) $user->id);
        if (! $activeShift || (int) $expense->kasir_shift_session_id !== (int) $activeShift->id) {
            return redirect()->route('kasir.shift.report')->withErrors([
                'expense' => 'Data pengeluaran tidak ditemukan pada shift aktif.',
            ]);
        }

        $expense->delete();

        return redirect()
            ->route('kasir.shift.report')
            ->with('success', 'Pengeluaran shift berhasil dihapus.');
    }

    public function close(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'kasir') {
            return redirect()->route('kasir.index');
        }

        $shiftId = DB::transaction(function () use ($user): int {
            $shift = KasirShiftSession::query()
                ->forUser((int) $user->id)
                ->active()
                ->lockForUpdate()
                ->latest('started_at')
                ->first();

            if (! $shift) {
                throw ValidationException::withMessages([
                    'shift' => 'Tidak ada shift aktif yang bisa ditutup.',
                ]);
            }

            $closedAt = now();
            $summary = $this->buildShiftSummary($shift, $closedAt);

            $shift->update([
                'ended_at' => $closedAt,
                'total_trx' => $summary['total_trx'],
                'total_omzet' => $summary['total_omzet'],
                'total_cash' => $summary['total_cash'],
                'total_qris' => $summary['total_qris'],
                'total_debit' => $summary['total_debit'],
                'total_delivery' => $summary['total_delivery'],
                'total_pengeluaran' => $summary['total_pengeluaran'],
                'estimasi_kas_akhir' => $summary['estimasi_kas_akhir'],
                'kas_akhir_input' => null,
            ]);

            return (int) $shift->id;
        });

        return redirect()
            ->route('kasir.shift.struk', ['shift' => $shiftId])
            ->with('success', 'Shift berhasil ditutup.');
    }

    public function struk(Request $request, KasirShiftSession $shift): View
    {
        $user = $request->user();
        abort_unless($user, 401);

        $isAdmin = $user->role === 'admin';
        $isOwnerKasir = $user->role === 'kasir' && (int) $shift->user_id === (int) $user->id;
        abort_unless($isAdmin || $isOwnerKasir, 403);

        $paper = $this->resolvePaperPreference($request);
        $endAt = $shift->ended_at ?? now();
        $produkTerjualRows = DB::table('detail_pesanan as dp')
            ->join('pesanan as p', 'p.id_pesanan', '=', 'dp.id_pesanan')
            ->leftJoin('produk as pr', 'pr.id_produk', '=', 'dp.id_produk')
            ->selectRaw("COALESCE(pr.nama_produk, 'Produk dihapus') as nama_produk")
            ->selectRaw('SUM(dp.jumlah) as qty')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $shift->started_at)
            ->where('p.waktu_pembayaran', '<=', $endAt)
            ->groupByRaw("COALESCE(pr.nama_produk, 'Produk dihapus')")
            ->orderByRaw('SUM(dp.jumlah) DESC')
            ->orderBy('nama_produk')
            ->get();

        $transaksiRows = Pesanan::query()
            ->select(['id_pesanan'])
            ->where('status_pembayaran', 'lunas')
            ->where('waktu_pembayaran', '>=', $shift->started_at)
            ->where('waktu_pembayaran', '<=', $endAt)
            ->get();
        $aggregate = Pesanan::query()
            ->selectRaw('COALESCE(SUM(COALESCE(subtotal_harga, total_harga + COALESCE(diskon_nominal,0) - COALESCE(pajak_nominal,0))),0) as total_bruto')
            ->selectRaw('COALESCE(SUM(COALESCE(pajak_nominal,0)),0) as total_pajak')
            ->selectRaw('COALESCE(SUM(total_harga),0) as total_netto')
            ->selectRaw("COALESCE(SUM(CASE WHEN metode_pembayaran = 'cash' THEN total_harga ELSE 0 END),0) as total_cash")
            ->selectRaw("COALESCE(SUM(CASE WHEN metode_pembayaran = 'qris' THEN total_harga ELSE 0 END),0) as total_qris")
            ->selectRaw("COALESCE(SUM(CASE WHEN metode_pembayaran = 'debit' THEN total_harga ELSE 0 END),0) as total_debit")
            ->selectRaw("COALESCE(SUM(CASE WHEN metode_pembayaran IN ('shopeefood','gofood','grabfood') THEN total_harga ELSE 0 END),0) as total_delivery")
            ->where('status_pembayaran', 'lunas')
            ->where('waktu_pembayaran', '>=', $shift->started_at)
            ->where('waktu_pembayaran', '<=', $endAt)
            ->first();

        $summary = [
            'total_trx' => (int) ($shift->total_trx ?: $transaksiRows->count()),
            'total_bruto' => (float) ($aggregate->total_bruto ?? 0),
            'total_pajak' => (float) ($aggregate->total_pajak ?? 0),
            'total_omzet' => (float) ($aggregate->total_netto ?? 0),
            'total_cash' => (float) ($aggregate->total_cash ?? 0),
            'total_qris' => (float) ($aggregate->total_qris ?? 0),
            'total_debit' => (float) ($aggregate->total_debit ?? 0),
            'total_delivery' => (float) ($aggregate->total_delivery ?? 0),
            'total_pengeluaran' => (float) $shift->total_pengeluaran,
            'estimasi_kas_akhir' => (float) ($shift->estimasi_kas_akhir ?? 0),
        ];

        return view('kasir.shift-struk', [
            'shift' => $shift->load('user'),
            'summary' => $summary,
            'produkTerjualRows' => $produkTerjualRows,
            'paper' => $paper,
            'strukSetting' => StrukSetting::current(),
        ]);
    }

    public function history(Request $request): View
    {
        $rows = KasirShiftSession::query()
            ->with('user')
            ->orderByDesc('started_at')
            ->paginate(25)
            ->onEachSide(1)
            ->withQueryString();

        return view('dashboard.shift-history', [
            'rows' => $rows,
        ]);
    }

    private function activeShiftForUser(int $userId): ?KasirShiftSession
    {
        return KasirShiftSession::query()
            ->forUser($userId)
            ->active()
            ->latest('started_at')
            ->first();
    }

    private function resolveSystemKasAwal(): float
    {
        $lastSaldoAkhir = KasirShiftSession::query()
            ->whereNotNull('ended_at')
            ->whereNotNull('estimasi_kas_akhir')
            ->orderByDesc('ended_at')
            ->value('estimasi_kas_akhir');

        if ($lastSaldoAkhir !== null) {
            return max(0, (float) $lastSaldoAkhir);
        }

        $configuredFloat = StrukSetting::query()->value('default_cash_float');
        if ($configuredFloat !== null) {
            return max(0, (float) $configuredFloat);
        }

        return 0.0;
    }

    private function resolveActiveShiftCount(): int
    {
        $count = (int) (StrukSetting::query()->value('active_shift_count') ?? 2);

        return max(1, min(3, $count));
    }

    private function buildShiftSummary(KasirShiftSession $shift, $end): array
    {
        $start = $shift->started_at;
        $row = Pesanan::query()
            ->selectRaw('COUNT(*) as total_trx')
            ->selectRaw('COALESCE(SUM(COALESCE(subtotal_harga, total_harga + COALESCE(diskon_nominal,0) - COALESCE(pajak_nominal,0))),0) as total_bruto')
            ->selectRaw('COALESCE(SUM(COALESCE(pajak_nominal,0)),0) as total_pajak')
            ->selectRaw("COALESCE(SUM(total_harga),0) as total_omzet")
            ->selectRaw("COALESCE(SUM(CASE WHEN metode_pembayaran = 'cash' THEN total_harga ELSE 0 END),0) as total_cash")
            ->selectRaw("COALESCE(SUM(CASE WHEN metode_pembayaran = 'qris' THEN total_harga ELSE 0 END),0) as total_qris")
            ->selectRaw("COALESCE(SUM(CASE WHEN metode_pembayaran = 'debit' THEN total_harga ELSE 0 END),0) as total_debit")
            ->selectRaw("COALESCE(SUM(CASE WHEN metode_pembayaran IN ('shopeefood','gofood','grabfood') THEN total_harga ELSE 0 END),0) as total_delivery")
            ->where('status_pembayaran', 'lunas')
            ->where('waktu_pembayaran', '>=', $start)
            ->where('waktu_pembayaran', '<=', $end)
            ->first();
        $totalPengeluaran = (float) KasirShiftPengeluaran::query()
            ->where('kasir_shift_session_id', (int) $shift->id)
            ->where('pengeluaran_at', '>=', $start)
            ->where('pengeluaran_at', '<=', $end)
            ->sum('nominal');
        $totalCash = (float) ($row->total_cash ?? 0);
        $estimasiKasAkhir = ((float) $shift->kas_awal + $totalCash) - $totalPengeluaran;

        return [
            'total_trx' => (int) ($row->total_trx ?? 0),
            'total_bruto' => (float) ($row->total_bruto ?? 0),
            'total_pajak' => (float) ($row->total_pajak ?? 0),
            'total_omzet' => (float) ($row->total_omzet ?? 0),
            'total_cash' => $totalCash,
            'total_qris' => (float) ($row->total_qris ?? 0),
            'total_debit' => (float) ($row->total_debit ?? 0),
            'total_delivery' => (float) ($row->total_delivery ?? 0),
            'total_pengeluaran' => $totalPengeluaran,
            'estimasi_kas_akhir' => $estimasiKasAkhir,
        ];
    }

    private function resolvePaperPreference(Request $request): string
    {
        $requestedPaper = $request->query('paper');
        $user = $request->user();

        if (in_array($requestedPaper, ['58', '80'], true)) {
            if ($user && $user->paper_preference !== $requestedPaper) {
                $user->forceFill(['paper_preference' => $requestedPaper])->save();
            }

            return $requestedPaper;
        }

        if ($user && in_array((string) $user->paper_preference, ['58', '80'], true)) {
            return (string) $user->paper_preference;
        }

        return '80';
    }
}
