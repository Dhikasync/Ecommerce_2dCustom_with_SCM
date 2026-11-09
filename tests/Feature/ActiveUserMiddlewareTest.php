<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ActiveUserMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function daftarkanRute(): void
    {
        Route::middleware(['web', 'auth', 'active'])
            ->get('/uji-aktif', fn () => response()->json(['ok' => true]));
    }

    public function test_akun_aktif_boleh_masuk(): void
    {
        $this->daftarkanRute();

        $admin = User::factory()->create(['role' => 'admin_ecommerce']);

        $this->actingAs($admin)
            ->getJson('/uji-aktif')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_akun_yang_dinonaktifkan_setelah_login_langsung_ditolak(): void
    {
        $this->daftarkanRute();

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'role' => 'admin_ecommerce',
            'password' => 'rahasia123',
        ]);

        $this->postJson('/login', [
            'email' => 'admin@example.test',
            'password' => 'rahasia123',
        ])->assertOk();

        // Tindakan darurat di luar aplikasi: admin dicabut aksesnya.
        $admin->update(['is_aktif' => false]);

        // Guard menyimpan user di memori selama satu proses test. Melupakannya
        // menyimulasikan request baru yang membaca ulang sesi dari database.
        Auth::guard('web')->forgetUser();

        $this->getJson('/uji-aktif')
            ->assertStatus(403)
            ->assertJson(['message' => 'Akun tidak aktif']);

        $this->assertGuest();
    }

    public function test_user_biasa_nonaktif_juga_ditolak(): void
    {
        $this->daftarkanRute();

        $user = User::factory()->create(['is_aktif' => false]);

        $this->actingAs($user)
            ->getJson('/uji-aktif')
            ->assertStatus(403)
            ->assertJson(['message' => 'Akun tidak aktif']);
    }

    public function test_guest_ditolak_sebelum_pemeriksaan_status_akun(): void
    {
        $this->daftarkanRute();

        $this->getJson('/uji-aktif')->assertStatus(401);
    }

    public function test_pemeriksaan_status_akun_berjalan_sebelum_pemeriksaan_role(): void
    {
        Route::middleware(['web', 'auth', 'active', 'role:admin_ecommerce'])
            ->get('/uji-aktif-role', fn () => response()->json(['ok' => true]));

        $adminScm = User::factory()->create([
            'role' => 'admin_scm',
            'is_aktif' => false,
        ]);

        // Akun nonaktif mendapat pesan "Akun tidak aktif", bukan pesan role.
        $this->actingAs($adminScm)
            ->getJson('/uji-aktif-role')
            ->assertStatus(403)
            ->assertJson(['message' => 'Akun tidak aktif']);
    }
}
