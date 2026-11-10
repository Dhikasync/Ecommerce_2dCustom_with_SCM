<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_mengakhiri_sesi_dan_membuat_ulang_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $idSesiLama = session()->getId();
        $tokenLama = session()->token();

        $this->postJson('/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logout berhasil']);

        $this->assertGuest();
        $this->assertNotSame($idSesiLama, session()->getId());
        $this->assertNotSame($tokenLama, session()->token());
    }

    public function test_setelah_logout_tidak_bisa_login_otomatis_kembali(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/logout')->assertOk();

        $this->get('/')->assertOk();
        $this->assertGuest();
    }

    public function test_guest_tidak_bisa_logout(): void
    {
        $this->postJson('/logout')->assertStatus(401);
    }

    public function test_logout_hanya_menerima_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logout')->assertStatus(405);

        $this->assertAuthenticatedAs($user);
    }
}
