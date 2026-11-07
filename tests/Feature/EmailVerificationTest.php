<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user, ?string $token = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(1440), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
            'token' => $token ?? $user->email_verification_token,
        ]);
    }

    private function unverifiedUser(string $token = 'abcdefghijklmnopqrstuvwxyz0123456789abcdefghijklmnopqrstuvwxyz01'): User
    {
        $user = User::factory()->unverified()->create();

        $user->forceFill(['email_verification_token' => $token])->save();

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
}
