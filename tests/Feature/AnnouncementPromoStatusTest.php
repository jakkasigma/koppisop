<?php

namespace Tests\Feature;

use App\Models\Announcement;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnnouncementPromoStatusTest extends TestCase
{
    public function test_future_period_promo_is_marked_as_akan_mulai_before_it_starts(): void
    {
        $announcement = new Announcement([
            'title' => 'Info Promo',
            'body' => 'Bundling Weekend Nilai: 15% Periode: 2026-03-29 s/d 2026-03-31 Status: Aktif',
        ]);

        $promoInfo = $announcement->resolvePromoStatus(
            Carbon::create(2026, 3, 27, 10, 0, 0, 'Asia/Jakarta')
        );

        $this->assertSame('Akan Mulai', $promoInfo['status']);
        $this->assertSame('2026-03-29', $promoInfo['start_at']?->toDateString());
        $this->assertSame('2026-03-31', $promoInfo['end_at']?->toDateString());
    }

    public function test_active_promo_stays_aktif_until_it_really_ends(): void
    {
        $announcement = new Announcement([
            'title' => 'Info Promo',
            'body' => 'Bundling Weekend Nilai: 15% Periode: 2026-03-29 s/d 2026-03-31 Status: Aktif',
        ]);

        $promoInfo = $announcement->resolvePromoStatus(
            Carbon::create(2026, 3, 30, 10, 0, 0, 'Asia/Jakarta')
        );

        $this->assertSame('Aktif', $promoInfo['status']);
    }
}
