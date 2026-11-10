<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ResendVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_GENERIK = 'Jika email terdaftar dan belum diverifikasi, tautan verifikasi sudah dikirim';

    public function test_halaman_cek_email_dapat_diakses(): void
    {
        $this->get('/email/verify')
            ->assertOk()
            ->assertJson(['message' => 'Cek email Anda']);

        $this->assertSame(url('/email/verify'), route('verification.notice'));
    }

    public function test_kirim_ulang_mengganti_token_dan_mematikan_tautan_lama(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'budi@example.test',
        ]);
        $user->forceFill(['email_verification_token' => hash('sha256', str_repeat('a', 64))])->save();

        $urlLama = URL::temporarySignedRoute('verification.verify', now()->addMinutes(1440), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
            'token' => str_repeat('a', 64),
        ]);

        $this->postJson('/email/verification-notification', ['email' => 'budi@example.test'])
            ->assertOk()
            ->assertJson(['message' => self::PESAN_GENERIK]);

        Mail::assertSent(VerifyEmailMail::class);

        $this->assertNotSame(hash('sha256', str_repeat('a', 64)), $user->fresh()->email_verification_token);

        $this->get($urlLama)
            ->assertStatus(403)
            ->assertJson(['message' => 'Tautan verifikasi tidak valid']);
    }

    public function test_email_tidak_terdaftar_tetap_dibalas_pesan_generik(): void
    {
        Mail::fake();

        $this->postJson('/email/verification-notification', ['email' => 'tidak-ada@example.test'])
            ->assertOk()
            ->assertJson(['message' => self::PESAN_GENERIK]);

        Mail::assertNothingSent();
    }

    public function test_akun_yang_sudah_terverifikasi_tidak_dikirimi_email(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'aktif@example.test']);

        $this->postJson('/email/verification-notification', ['email' => 'aktif@example.test'])
            ->assertOk()
            ->assertJson(['message' => self::PESAN_GENERIK]);

        Mail::assertNothingSent();
    }

    public function test_batas_kirim_ulang_lima_kali_per_lima_belas_menit(): void
    {
        Mail::fake();

        User::factory()->unverified()->create(['email' => 'budi@example.test']);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/email/verification-notification', ['email' => 'budi@example.test'])
                ->assertOk();
        }

        $this->postJson('/email/verification-notification', ['email' => 'budi@example.test'])
            ->assertStatus(429)
            ->assertJson(['message' => 'Terlalu banyak permintaan, coba lagi beberapa saat lagi']);
    }

    public function test_email_wajib_valid(): void
    {
        Mail::fake();

        $this->postJson('/email/verification-notification', ['email' => 'bukan-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }
}
