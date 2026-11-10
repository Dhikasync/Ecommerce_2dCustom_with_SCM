<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, string $token, array $override = []): array
    {
        return array_merge([
            'token' => $token,
            'email' => $user->email,
            'password' => 'passwordBaru123',
            'password_confirmation' => 'passwordBaru123',
        ], $override);
    }

    public function test_token_valid_mengubah_password_dan_menghapus_token(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@example.test',
            'password' => 'passwordLama123',
        ]);

        $token = Password::createToken($user);

        $this->postJson('/reset-password', $this->payload($user, $token))
            ->assertOk()
            ->assertJson([
                'message' => 'Password berhasil diubah, silakan login',
                'redirect' => url('/login'),
            ]);

        $segar = $user->fresh();

        $this->assertTrue(Hash::check('passwordBaru123', $segar->password));
        $this->assertFalse(Hash::check('passwordLama123', $segar->password));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->assertGuest();
    }

    public function test_token_hanya_bisa_dipakai_sekali(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.test']);
        $token = Password::createToken($user);

        $this->postJson('/reset-password', $this->payload($user, $token))
            ->assertOk();

        $this->postJson('/reset-password', $this->payload($user, $token))
            ->assertStatus(422)
            ->assertJson(['message' => 'Link reset tidak valid atau sudah dipakai']);

        $this->assertTrue(Hash::check('passwordBaru123', $user->fresh()->password));
    }

    public function test_token_tidak_dikenal_ditolak(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@example.test',
            'password' => 'passwordLama123',
        ]);

        Password::createToken($user);

        $this->postJson('/reset-password', $this->payload($user, 'token-palsu'))
            ->assertStatus(422)
            ->assertJson(['message' => 'Link reset tidak valid atau sudah dipakai']);

        $this->assertTrue(Hash::check('passwordLama123', $user->fresh()->password));
    }

    public function test_token_kedaluwarsa_setelah_24_jam_ditolak_dan_dihapus(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@example.test',
            'password' => 'passwordLama123',
        ]);

        $token = Password::createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(1441)]);

        $this->postJson('/reset-password', $this->payload($user, $token))
            ->assertStatus(422)
            ->assertJson(['message' => 'Link reset sudah kedaluwarsa, silakan minta ulang']);

        $this->assertTrue(Hash::check('passwordLama123', $user->fresh()->password));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_remember_token_diganti_setelah_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@example.test',
            'remember_token' => 'token-lama',
        ]);

        $token = Password::createToken($user);

        $this->postJson('/reset-password', $this->payload($user, $token))
            ->assertOk();

        $this->assertNotSame('token-lama', $user->fresh()->remember_token);
    }

    public function test_password_kurang_dari_delapan_karakter_ditolak(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.test']);
        $token = Password::createToken($user);

        $this->postJson('/reset-password', $this->payload($user, $token, [
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'Password minimal 8 karakter');

        $this->assertSame(1, DB::table('password_reset_tokens')->count());
    }

    public function test_konfirmasi_password_tidak_cocok_ditolak(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.test']);
        $token = Password::createToken($user);

        $this->postJson('/reset-password', $this->payload($user, $token, [
            'password_confirmation' => 'beda12345',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'Konfirmasi password tidak sesuai');
    }

    public function test_password_baru_bisa_dipakai_login(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@example.test',
            'password' => 'passwordLama123',
        ]);

        $token = Password::createToken($user);

        $this->postJson('/reset-password', $this->payload($user, $token))->assertOk();

        $this->postJson('/login', [
            'email' => 'budi@example.test',
            'password' => 'passwordLama123',
        ])->assertStatus(422);

        $this->postJson('/login', [
            'email' => 'budi@example.test',
            'password' => 'passwordBaru123',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);
    }
}
