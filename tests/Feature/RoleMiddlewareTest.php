<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function daftarkanRute(string $role): void
    {
        Route::middleware(['auth', 'role:'.$role])
            ->get('/uji-'.str_replace(',', '-', $role), fn () => response()->json(['ok' => true]));
    }

    public function test_admin_dengan_role_sesuai_boleh_masuk(): void
    {
        $this->daftarkanRute('admin_ecommerce');

        $admin = User::factory()->create(['role' => 'admin_ecommerce']);

        $this->actingAs($admin)
            ->getJson('/uji-admin_ecommerce')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_user_biasa_ditolak_dengan_pesan_sesuai_user_story(): void
    {
        $this->daftarkanRute('admin_ecommerce');

        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->getJson('/uji-admin_ecommerce')
            ->assertStatus(403)
            ->assertJson(['message' => 'Anda tidak memiliki akses ke halaman ini']);
    }

    public function test_admin_lintas_role_ditolak(): void
    {
        $this->daftarkanRute('admin_ecommerce');

        $adminScm = User::factory()->create(['role' => 'admin_scm']);

        $this->actingAs($adminScm)
            ->getJson('/uji-admin_ecommerce')
            ->assertStatus(403)
            ->assertJson(['message' => 'Anda tidak memiliki akses ke halaman ini']);
    }

    public function test_beberapa_role_diizinkan_sekaligus(): void
    {
        $this->daftarkanRute('admin_ecommerce,admin_scm');

        $this->actingAs(User::factory()->create(['role' => 'admin_ecommerce']))
            ->getJson('/uji-admin_ecommerce-admin_scm')
            ->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'admin_scm']))
            ->getJson('/uji-admin_ecommerce-admin_scm')
            ->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->getJson('/uji-admin_ecommerce-admin_scm')
            ->assertStatus(403);
    }

    public function test_guest_ditolak_sebelum_pemeriksaan_role(): void
    {
        $this->daftarkanRute('admin_ecommerce');

        $this->getJson('/uji-admin_ecommerce')->assertStatus(401);
    }

    public function test_has_role_membandingkan_secara_persis(): void
    {
        $user = User::factory()->create(['role' => 'admin_ecommerce']);

        $this->assertTrue($user->hasRole('admin_ecommerce'));
        $this->assertTrue($user->hasRole('user', 'admin_ecommerce'));
        $this->assertFalse($user->hasRole('admin_scm'));
    }
}
