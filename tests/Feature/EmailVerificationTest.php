<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'abcdefghijklmnopqrstuvwxyz0123456789abcdefghijklmnopqrstuvwxyz01';

    private function verificationUrl(User $user, ?string $token = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(1440), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
            'token' => $token ?? self::TOKEN,
        ]);
    }

    private function unverifiedUser(): User
    {
        $user = User::factory()->unverified()->create();

        $user->forceFill(['email_verification_token' => hash('sha256', self::TOKEN)])->save();

        return $user;
    }

    public function test_tautan_verifikasi_menandai_email_terverifikasi(): void
    {
        $user = $this->unverifiedUser();

        $response = $this->get($this->verificationUrl($user));

        $response->assertOk();
        $response->assertJson(['message' => 'Email berhasil diverifikasi, silakan login']);

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->email_verification_token);
    }

    public function test_database_menyimpan_hash_bukan_token_asli(): void
    {
        $user = $this->unverifiedUser();

        $this->assertSame(hash('sha256', self::TOKEN), $user->email_verification_token);
        $this->assertNotSame(self::TOKEN, $user->email_verification_token);

        $this->get($this->verificationUrl($user))->assertOk();
    }

    public function test_tautan_kedaluwarsa_ditolak_dengan_pesan_khusus(): void
    {
        $user = $this->unverifiedUser();

        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
            'token' => self::TOKEN,
        ]);

        $response = $this->get($url);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Tautan verifikasi sudah kedaluwarsa',
            'can_resend' => true,
        ]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_signature_yang_diubah_ditolak(): void
    {
        $user = $this->unverifiedUser();

        $url = $this->verificationUrl($user);
        $url = preg_replace('/signature=[^&]+/', 'signature=palsu', $url);

        $response = $this->get($url);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Tautan verifikasi tidak valid']);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_token_yang_salah_ditolak(): void
    {
        $user = $this->unverifiedUser();

        $response = $this->get($this->verificationUrl($user, str_repeat('b', 64)));

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Tautan verifikasi tidak valid']);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_hash_email_yang_salah_ditolak(): void
    {
        $user = $this->unverifiedUser();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(1440), [
            'id' => $user->id,
            'hash' => sha1('bukan-email-user@example.test'),
            'token' => self::TOKEN,
        ]);

        $response = $this->get($url);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Tautan verifikasi tidak valid']);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_user_yang_tidak_dikenal_ditolak(): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(1440), [
            'id' => 999999,
            'hash' => sha1('tidak-ada@example.test'),
            'token' => str_repeat('c', 64),
        ]);

        $response = $this->get($url);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Tautan verifikasi tidak valid']);
    }

    public function test_tautan_hanya_dapat_dipakai_sekali(): void
    {
        $user = $this->unverifiedUser();
        $url = $this->verificationUrl($user);

        $this->get($url)->assertOk();

        $this->get($url)
            ->assertOk()
            ->assertJson(['message' => 'Email sudah diverifikasi, silakan login']);

        $this->assertNull($user->fresh()->email_verification_token);
    }
}
