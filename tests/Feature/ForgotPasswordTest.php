<?php

namespace Tests\Feature;

use App\Mail\Auth\ResetPasswordMail;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_GENERIK = 'Jika email terdaftar, link reset telah dikirim';

    public function test_email_terdaftar_dikirimi_tautan_reset(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'budi@example.test']);

        $this->postJson('/forgot-password', ['email' => 'budi@example.test'])
            ->assertOk()
            ->assertJson(['message' => self::PESAN_GENERIK]);

        $record = DB::table('password_reset_tokens')->where('email', 'budi@example.test')->first();

        $this->assertNotNull($record);
        $this->assertNotNull($record->created_at);

        Mail::assertSent(
            ResetPasswordMail::class,
            fn (ResetPasswordMail $mail) => $mail->hasTo('budi@example.test')
                && $mail->user->is($user)
                && str_contains(ResetPasswordMail::urlFor($mail->user, $mail->token), $mail->token),
        );
    }

    public function test_email_tidak_terdaftar_dibalas_pesan_yang_sama_tanpa_mengirim_email(): void
    {
        Mail::fake();

        $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.test'])
            ->assertOk()
            ->assertJson(['message' => self::PESAN_GENERIK]);

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_pesan_untuk_email_terdaftar_dan_tidak_terdaftar_identik(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'budi@example.test']);

        $terdaftar = $this->postJson('/forgot-password', ['email' => 'budi@example.test'])->json('message');
        $tidak = $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.test'])->json('message');

        $this->assertSame($terdaftar, $tidak);
    }

    public function test_email_huruf_besar_tetap_menemukan_akun(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'budi@example.test']);

        $this->postJson('/forgot-password', ['email' => 'BUDI@Example.Test'])
            ->assertOk()
            ->assertJson(['message' => self::PESAN_GENERIK]);

        Mail::assertSent(
            ResetPasswordMail::class,
            fn (ResetPasswordMail $mail) => $mail->hasTo('budi@example.test'),
        );
    }

    public function test_email_wajib_valid(): void
    {
        Mail::fake();

        $this->postJson('/forgot-password', ['email' => 'bukan-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_permintaan_dibatasi_enam_kali_per_menit(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 6; $i++) {
            $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.test'])
                ->assertOk();
        }

        $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.test'])
            ->assertStatus(429);
    }

    public function test_halaman_reset_membawa_email_dari_tautan(): void
    {
        $this->get('/reset-password/token-uji?email=budi%40example.test')
            ->assertOk()
            ->assertJson([
                'message' => 'Silakan masukkan password baru',
                'token' => 'token-uji',
                'email' => 'budi@example.test',
            ]);
    }
}
