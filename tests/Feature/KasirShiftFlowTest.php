<?php

namespace Tests\Feature;

use App\Models\KasirShiftSession;
use App\Models\StrukSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KasirShiftFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_login_redirects_to_shift_start_page(): void
    {
        $kasir = User::factory()->create([
            'role' => 'kasir',
            'email' => 'kasir-shift@example.test',
            'password' => Hash::make('password'),
        ]);

        $this->post(route('login.submit'), [
            'email' => $kasir->email,
            'password' => 'password',
        ])->assertRedirect(route('kasir.shift.start'));
    }

    public function test_kasir_cannot_access_kasir_page_without_active_shift(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)
            ->get(route('kasir.index'))
            ->assertRedirect(route('kasir.shift.start'));
    }

    public function test_kasir_can_start_shift_and_then_access_kasir_page(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)
            ->post(route('kasir.shift.store'), [
                'shift_ke' => 1,
            ])
            ->assertRedirect(route('kasir.index'));

        $this->assertDatabaseHas('kasir_shift_sessions', [
            'user_id' => $kasir->id,
            'shift_ke' => 1,
            'ended_at' => null,
        ]);

        $this->actingAs($kasir)
            ->get(route('kasir.index'))
            ->assertOk();
    }

    public function test_start_shift_uses_system_cash_from_latest_closed_shift(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 2,
            'kas_awal' => 100000,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(1),
            'total_trx' => 10,
            'total_omzet' => 220000,
            'total_cash' => 120000,
            'total_qris' => 60000,
            'total_debit' => 40000,
            'total_pengeluaran' => 15000,
            'estimasi_kas_akhir' => 205000,
        ]);

        $this->actingAs($kasir)
            ->post(route('kasir.shift.store'), [
                'shift_ke' => 1,
            ])
            ->assertRedirect(route('kasir.index'));

        $newShift = KasirShiftSession::query()
            ->forUser((int) $kasir->id)
            ->active()
            ->latest('started_at')
            ->first();

        $this->assertNotNull($newShift);
        $this->assertEquals(205000.0, (float) $newShift->kas_awal);
    }

    public function test_logout_closes_active_shift_for_kasir(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $shift = KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 2,
            'kas_awal' => 200000,
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        $this->actingAs($kasir)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertNotNull($shift->fresh()->ended_at);
    }

    public function test_kasir_shift_choice_follows_active_shift_count_setting(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        StrukSetting::current()->update(['active_shift_count' => 1]);

        $this->actingAs($kasir)
            ->post(route('kasir.shift.store'), [
                'shift_ke' => 2,
            ])
            ->assertSessionHasErrors('shift_ke');

        $this->assertDatabaseMissing('kasir_shift_sessions', [
            'user_id' => $kasir->id,
            'shift_ke' => 2,
            'ended_at' => null,
        ]);
    }
}
