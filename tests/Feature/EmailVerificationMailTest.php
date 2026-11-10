<?php

namespace Tests\Feature;

use App\Mail\Auth\VerifyEmailMail;
use App\Models\Auth\User;
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
            $url = VerifyEmailMail::urlFor($mail->user, $mail->token);

            // Token asli hanya ada di email; database menyimpan hash-nya.
            return $mail->token !== $user->email_verification_token
                && $user->email_verification_token === hash('sha256', $mail->token)
                && str_contains($url, 'token='.$mail->token)
                && str_contains($url, 'signature=')
                && str_contains($url, 'expires=');
        });
    }

    public function test_tautan_dari_email_bisa_dipakai_verifikasi(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        $url = '';

        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use (&$url) {
            $url = VerifyEmailMail::urlFor($mail->user, $mail->token);

            return true;
        });

        $this->get($url)->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_kirim_ulang_membatalkan_tautan_sebelumnya(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $tokenLama = str_repeat('a', 64);
        $user->forceFill(['email_verification_token' => hash('sha256', $tokenLama)])->save();

        $urlLama = URL::temporarySignedRoute('verification.verify', now()->addMinutes(1440), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
            'token' => $tokenLama,
        ]);

        $user->sendEmailVerificationNotification();

        $this->assertNotSame(hash('sha256', $tokenLama), $user->fresh()->email_verification_token);

        $this->get($urlLama)
            ->assertStatus(403)
            ->assertJson(['message' => 'Tautan verifikasi tidak valid']);
    }
}
