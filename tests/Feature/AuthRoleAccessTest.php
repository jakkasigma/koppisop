<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuthRedirects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_protected_pages(): void
    {
        $this->get(route('kasir.index'))
            ->assertRedirect(route('login'));

        $this->get(route('dashboard.index'))
            ->assertRedirect(route('login'));
    }

    public function test_kasir_cannot_access_admin_only_pages(): void
    {
        $kasir = User::factory()->create([
            'role' => 'kasir',
        ]);

        $this->actingAs($kasir)
            ->get(route('dashboard.index'))
            ->assertRedirect(route('kasir.shift.start'));

        $this->actingAs($kasir)
            ->get(route('produk.index'))
            ->assertRedirect(route('kasir.shift.start'));

        $this->actingAs($kasir)
            ->get(route('dashboard.staff_activity.index'))
            ->assertRedirect(route('kasir.shift.start'));
    }

    public function test_admin_can_access_admin_pages(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('dashboard.staff_activity.index'))
            ->assertOk();
    }

    public function test_admin_login_ignores_kasir_intended_url_and_goes_to_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-intended@example.test',
            'password' => Hash::make('password'),
        ]);

        // Set intended URL to kasir by visiting protected route while guest.
        $this->get(route('kasir.index'))
            ->assertRedirect(route('login'));

        $this->post(route('login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard.index'));
    }

    public function test_kasir_login_ignores_admin_intended_url_and_goes_to_kasir_flow(): void
    {
        $kasir = User::factory()->create([
            'role' => 'kasir',
            'email' => 'kasir-intended@example.test',
            'password' => Hash::make('password'),
        ]);

        $this->get(route('dashboard.index'))
            ->assertRedirect(route('login'));

        $this->post(route('login.submit'), [
            'email' => $kasir->email,
            'password' => 'password',
        ])->assertRedirect(route('kasir.shift.start'));
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_role_home(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($admin)
            ->get(route('login'))
            ->assertRedirect(route('dashboard.index'));

        $this->actingAs($kasir)
            ->get(route('login'))
            ->assertRedirect(AuthRedirects::urlFor($kasir));
    }

    public function test_root_redirects_guest_to_login_and_authenticated_users_to_correct_home(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->get('/')
            ->assertRedirect(route('login'));

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route('dashboard.index'));

        $this->actingAs($kasir)
            ->get('/')
            ->assertRedirect(AuthRedirects::urlFor($kasir));
    }
}
