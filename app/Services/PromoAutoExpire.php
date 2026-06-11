<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Diskon;
use App\Models\PromoBundling;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PromoAutoExpire
{
    public static function run(?Carbon $now = null): void
    {
        $now = $now ?? now();
        $today = $now->toDateString();

        self::expireDiskon($today);
        self::expireBundling($today);
    }

    private static function expireDiskon(string $today): void
    {
        $expired = Diskon::query()
            ->where('status_aktif', true)
            ->whereNotNull('tanggal_selesai')
            ->whereDate('tanggal_selesai', '<', $today)
            ->get();

        foreach ($expired as $diskon) {
            $diskon->update(['status_aktif' => false]);
            self::announceDiskonEnded($diskon);
        }
    }

    private static function expireBundling(string $today): void
    {
        $expired = PromoBundling::query()
            ->where('status_aktif', true)
            ->whereNotNull('tanggal_selesai')
            ->whereDate('tanggal_selesai', '<', $today)
            ->get();

        foreach ($expired as $bundling) {
            $bundling->update(['status_aktif' => false]);
            self::announceBundlingEnded($bundling);
        }
    }

    private static function announceDiskonEnded(Diskon $diskon): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        $nama = trim((string) ($diskon->nama_diskon ?? 'Promo'));
        $tipe = (string) ($diskon->tipe_diskon ?? '');
        $tipeLabel = match ($tipe) {
            'persen' => 'Diskon Persen',
            'nominal' => 'Diskon Nominal',
            'harga_kategori' => 'Harga Spesial Kategori',
            default => 'Promo',
        };
        $periodeSelesai = $diskon->tanggal_selesai?->format('Y-m-d');

        $bodyParts = array_filter([
            "{$tipeLabel} {$nama}",
            $periodeSelesai ? "Berakhir pada: {$periodeSelesai}" : null,
            'Status: Nonaktif',
        ]);
        $body = implode("\n", $bodyParts);

        $exists = Announcement::query()
            ->where('title', 'Promo Berakhir')
            ->where('body', 'like', '%' . $nama . '%')
            ->when($periodeSelesai, fn ($q) => $q->where('body', 'like', '%' . $periodeSelesai . '%'))
            ->exists();

        if (! $exists) {
            Announcement::create([
                'title' => 'Promo Berakhir',
                'body' => $body,
                'target_role' => null,
                'is_active' => false,
                'published_at' => now(),
            ]);
        }
    }

    private static function announceBundlingEnded(PromoBundling $promo): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        $nama = trim((string) ($promo->nama_promo ?? 'Bundling'));
        $periodeSelesai = $promo->tanggal_selesai?->format('Y-m-d');

        $bodyParts = array_filter([
            "Bundling {$nama}",
            $periodeSelesai ? "Berakhir pada: {$periodeSelesai}" : null,
            'Status: Nonaktif',
        ]);
        $body = implode("\n", $bodyParts);

        $exists = Announcement::query()
            ->where('title', 'Promo Bundling Berakhir')
            ->where('body', 'like', '%' . $nama . '%')
            ->when($periodeSelesai, fn ($q) => $q->where('body', 'like', '%' . $periodeSelesai . '%'))
            ->exists();

        if (! $exists) {
            Announcement::create([
                'title' => 'Promo Bundling Berakhir',
                'body' => $body,
                'target_role' => null,
                'is_active' => false,
                'published_at' => now(),
            ]);
        }
    }
}
