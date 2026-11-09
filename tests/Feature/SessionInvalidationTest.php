<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private function daftarkanRute(): void
    {
        Route::middleware(['web', 'auth'])
            ->get('/uji-sesi', fn () => response()->json(['ok' => true]));
    }

    public function test_sesi_yang_sudah_ada_ditolak_setelah_password_diubah(): void
    {
        $this->daftarkanRute();

        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'passwordLama123',
            'role' => 'admin_ecommerce',
        ]);

        // Sesi A: admin login dan memakai aplikasi seperti biasa.
        $this->postJson('/login', [
            'email' => 'admin@example.test',
            'password' => 'passwordLama123',
        ])->assertOk();

        $this->getJson('/uji-sesi')->assertOk();

        // Simpan isi sesi A, lalu tinggalkan sesi itu sejenak.
        $sesiLama = session()->all();

        Auth::guard('web')->logout();
        session()->flush();

        // Sesi B (guest): password diubah lewat tautan reset.
        $token = Password::broker()->createToken($user);

        $this->postJson('/reset-password', [
            'token' => $token,
            'email' => 'admin@example.test',
            'password' => 'passwordBaru123',
            'password_confirmation' => 'passwordBaru123',
        ])->assertOk();

        // Sesi A dipakai lagi: hash password di dalamnya sudah tidak cocok.
        $this->withSession($sesiLama)
            ->getJson('/uji-sesi')
            ->assertStatus(401);

        $this->assertGuest();
    }

    public function test_sesi_lain_yang_masih_memegang_hash_lama_ditolak(): void
    {
        $this->daftarkanRute();

        $user = User::factory()->create(['password' => 'passwordLama123']);
        $hashLama = $user->password;

        $user->forceFill(['password' => 'passwordBaru123'])->save();

        // Meniru perangkat lain: sesinya masih menyimpan hash password lama.
        $this->withSession(['password_hash_'.config('auth.defaults.guard') => $hashLama])
            ->actingAs($user->fresh())
            ->getJson('/uji-sesi')
            ->assertStatus(401);

        $this->assertGuest();
    }

    public function test_login_baru_setelah_password_diubah_tetap_bisa_masuk(): void
    {
        $this->daftarkanRute();

        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'passwordLama123',
        ]);

        $user->forceFill(['password' => 'passwordBaru123'])->save();

        $this->postJson('/login', [
            'email' => 'admin@example.test',
            'password' => 'passwordLama123',
        ])->assertStatus(422);

        $this->postJson('/login', [
            'email' => 'admin@example.test',
            'password' => 'passwordBaru123',
        ])->assertOk();

        $this->getJson('/uji-sesi')->assertOk();
    }
}
