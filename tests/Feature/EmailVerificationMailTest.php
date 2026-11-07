<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_kirim_email_membuat_token_dan_tautan_bertanda_tangan(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        $user->refresh();

        $this->assertSame(64, strlen($user->email_verification_token));

        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use ($user) {
            $url = VerifyEmailMail::urlFor($mail->user);

            return str_contains($url, 'token='.$user->email_verification_token)
                && str_contains($url, 'signature=')
                && str_contains($url, 'expires=');
        });
    }

    public function test_kirim_ulang_membatalkan_tautan_sebelumnya(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $user->forceFill(['email_verification_token' => str_repeat('a', 64)])->save();

        $urlLama = URL::temporarySignedRoute('verification.verify', now()->addMinutes(1440), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
            'token' => $user->email_verification_token,
        ]);

        $user->sendEmailVerificationNotification();

        $this->assertNotSame(str_repeat('a', 64), $user->fresh()->email_verification_token);

        $this->get($urlLama)
            ->assertStatus(403)
            ->assertJson(['message' => 'Tautan verifikasi tidak valid']);
    }
}
