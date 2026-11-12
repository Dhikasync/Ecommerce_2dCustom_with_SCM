<?php

namespace Tests\Feature;

use App\Mail\Auth\EmailChangeNoticeMail;
use App\Mail\Auth\VerifyNewEmailMail;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * FR-AUTH-06 #7, FR-PROF-01 #8. Tautan ke email baru, email lama tetap berlaku,
 * pemberitahuan ke email lama; konfirmasi tanpa login karena tautan dari email.
 */
class ChangeEmailTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(array $atribut = []): User
    {
        return User::factory()->create($atribut);
    }

    private function ajukan(User $user, string $email): void
    {
        $this->actingAs($user)
            ->putJson('/profil/email', ['email' => $email])
            ->assertOk()
            ->assertJson(['pending_email' => strtolower($email)]);
    }

    /**
     * Token asli hanya ada di email; test mengambilnya dari Mailable.
     */
    private function tokenTerkirim(User $user): string
    {
        $token = null;

        Mail::assertSent(VerifyNewEmailMail::class, function (VerifyNewEmailMail $mail) use ($user, &$token) {
            $token = $mail->token;

            return $mail->hasTo((string) $user->fresh()->pending_email);
        });

        $this->assertNotNull($token);

        return $token;
    }

    private function tautan(User $user, string $token): string
    {
        return VerifyNewEmailMail::urlFor($user->fresh(), $token);
    }

    public function test_guest_tidak_boleh_mengajukan_perubahan_email(): void
    {
        Mail::fake();

        $this->putJson('/profil/email', ['email' => 'baru@example.test'])
            ->assertStatus(401);

        Mail::assertNothingSent();
    }

    public function test_akun_nonaktif_tidak_boleh_mengajukan_perubahan_email(): void
    {
        Mail::fake();

        $this->actingAs($this->pengguna(['is_aktif' => false]))
            ->putJson('/profil/email', ['email' => 'baru@example.test'])
            ->assertStatus(403)
            ->assertJson(['message' => 'Akun tidak aktif']);

        Mail::assertNothingSent();
    }

    public function test_pengajuan_tidak_langsung_mengubah_email(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);

        $this->ajukan($user, 'baru@example.test');

        $user->refresh();

        // Inti FR-AUTH-06 #7: email lama tetap berlaku sampai konfirmasi.
        $this->assertSame('lama@example.test', $user->email);
        $this->assertSame('baru@example.test', $user->pending_email);
        $this->assertSame(64, strlen($user->pending_email_token));
    }

    public function test_dua_email_dikirim_ke_alamat_yang_tepat(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);

        $this->ajukan($user, 'baru@example.test');

        // Tautan konfirmasi ke email baru, pemberitahuan ke email lama.
        Mail::assertSent(VerifyNewEmailMail::class, fn (VerifyNewEmailMail $mail) => $mail->hasTo('baru@example.test'));
        Mail::assertSent(EmailChangeNoticeMail::class, function (EmailChangeNoticeMail $mail) {
            return $mail->hasTo('lama@example.test') && $mail->emailBaru === 'baru@example.test';
        });
    }

    public function test_token_disimpan_sebagai_hash(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);

        $this->ajukan($user, 'baru@example.test');
        $token = $this->tokenTerkirim($user);

        $this->assertSame(hash('sha256', $token), $user->fresh()->pending_email_token);
        $this->assertNotSame($token, $user->fresh()->pending_email_token);
    }

    public function test_email_baru_disimpan_huruf_kecil(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);

        $this->ajukan($user, 'Budi@Example.Test');

        $this->assertSame('budi@example.test', $user->fresh()->pending_email);
    }

    public function test_email_yang_sudah_dipakai_akun_lain_ditolak(): void
    {
        Mail::fake();

        $this->pengguna(['email' => 'budi@example.test']);
        $user = $this->pengguna(['email' => 'lama@example.test']);

        $this->actingAs($user)
            ->putJson('/profil/email', ['email' => 'BUDI@example.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'Email sudah digunakan']);

        $this->assertNull($user->fresh()->pending_email);
        Mail::assertNothingSent();
    }

    public function test_format_email_tidak_valid_ditolak(): void
    {
        Mail::fake();

        $this->actingAs($this->pengguna())
            ->putJson('/profil/email', ['email' => 'user@'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'Format email tidak valid']);

        Mail::assertNothingSent();
    }

    public function test_email_yang_sama_dengan_email_saat_ini_ditolak(): void
    {
        Mail::fake();

        $this->actingAs($this->pengguna(['email' => 'lama@example.test']))
            ->putJson('/profil/email', ['email' => 'lama@example.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'Email baru sama dengan email saat ini']);

        Mail::assertNothingSent();
    }

    public function test_email_kosong_ditolak(): void
    {
        Mail::fake();

        $this->actingAs($this->pengguna())
            ->putJson('/profil/email', ['email' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'Email wajib diisi']);

        Mail::assertNothingSent();
    }

    public function test_profil_menampilkan_email_yang_menunggu_konfirmasi(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);
        $this->ajukan($user, 'baru@example.test');

        $this->actingAs($user)
            ->getJson('/profil')
            ->assertOk()
            ->assertJsonPath('data.email', 'lama@example.test')
            ->assertJsonPath('data.pending_email', 'baru@example.test');
    }

    public function test_tautan_konfirmasi_mengubah_email_tanpa_perlu_login(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);
        $this->ajukan($user, 'baru@example.test');

        $tautan = $this->tautan($user, $this->tokenTerkirim($user));

        // Tanpa actingAs: tautan bisa dibuka dari aplikasi email.
        $this->getJson($tautan)
            ->assertOk()
            ->assertJson([
                'message' => 'Email berhasil diubah',
                'email' => 'baru@example.test',
            ]);

        $user->refresh();

        $this->assertSame('baru@example.test', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_token);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_setelah_konfirmasi_login_memakai_email_baru(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test', 'password' => 'rahasia123']);
        $this->ajukan($user, 'baru@example.test');

        $this->getJson($this->tautan($user, $this->tokenTerkirim($user)))->assertOk();

        // Sesi test masih login dari pengajuan tadi; akhiri dulu.
        $this->postJson('/logout')->assertOk();

        $this->postJson('/login', [
            'email' => 'baru@example.test',
            'password' => 'rahasia123',
        ])->assertOk();

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_email_lama_masih_dapat_dipakai_sebelum_konfirmasi(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test', 'password' => 'rahasia123']);
        $this->ajukan($user, 'baru@example.test');

        // Akhiri sesi supaya login berikutnya diuji dari keadaan guest.
        $this->postJson('/logout')->assertOk();

        // Sebelum tautan diklik: email lama masih berlaku...
        $this->postJson('/login', [
            'email' => 'lama@example.test',
            'password' => 'rahasia123',
        ])->assertOk();

        $this->postJson('/logout')->assertOk();

        // ...dan email baru belum bisa dipakai.
        $this->postJson('/login', [
            'email' => 'baru@example.test',
            'password' => 'rahasia123',
        ])->assertStatus(422);
    }

    public function test_token_salah_ditolak(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);
        $this->ajukan($user, 'baru@example.test');
        $this->tokenTerkirim($user);

        $tautan = $this->tautan($user, 'token-palsu');

        $this->getJson($tautan)
            ->assertStatus(403)
            ->assertJson(['message' => 'Tautan konfirmasi tidak valid']);

        $this->assertSame('lama@example.test', $user->fresh()->email);
    }

    public function test_tautan_tidak_dapat_dipakai_dua_kali(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);
        $this->ajukan($user, 'baru@example.test');
        $tautan = $this->tautan($user, $this->tokenTerkirim($user));

        $this->getJson($tautan)->assertOk();

        $this->getJson($tautan)
            ->assertStatus(403)
            ->assertJson(['message' => 'Tautan konfirmasi tidak valid']);
    }

    public function test_tautan_kedaluwarsa_ditolak(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);
        $this->ajukan($user, 'baru@example.test');
        $token = $this->tokenTerkirim($user);

        $tautan = URL::temporarySignedRoute(
            'profil.email.verify',
            now()->subMinute(),
            [
                'id' => $user->getKey(),
                'hash' => sha1('baru@example.test'),
                'token' => $token,
            ],
        );

        $this->getJson($tautan)->assertStatus(403);

        $this->assertSame('lama@example.test', $user->fresh()->email);
    }

    public function test_tautan_tanpa_pengajuan_ditolak(): void
    {
        $user = $this->pengguna(['email' => 'lama@example.test']);

        $tautan = URL::temporarySignedRoute(
            'profil.email.verify',
            now()->addMinutes(30),
            [
                'id' => $user->getKey(),
                'hash' => sha1('baru@example.test'),
                'token' => 'apa-saja',
            ],
        );

        $this->getJson($tautan)->assertStatus(403);
    }

    public function test_pengajuan_ulang_membuat_tautan_lama_tidak_berlaku(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);

        $this->ajukan($user, 'pertama@example.test');
        $tokenPertama = $this->tokenTerkirim($user);
        $tautanPertama = $this->tautan($user, $tokenPertama);

        Mail::fake();

        $this->ajukan($user, 'kedua@example.test');
        $tokenKedua = $this->tokenTerkirim($user);

        $this->getJson($tautanPertama)
            ->assertStatus(403)
            ->assertJson(['message' => 'Tautan konfirmasi tidak valid']);

        $this->getJson($this->tautan($user, $tokenKedua))->assertOk();

        $this->assertSame('kedua@example.test', $user->fresh()->email);
    }

    public function test_email_yang_direbut_akun_lain_sebelum_konfirmasi_ditolak(): void
    {
        Mail::fake();

        $user = $this->pengguna(['email' => 'lama@example.test']);
        $this->ajukan($user, 'baru@example.test');
        $tautan = $this->tautan($user, $this->tokenTerkirim($user));

        // Akun lain mendaftar dengan alamat itu setelah permintaan dibuat.
        User::factory()->create(['email' => 'baru@example.test']);

        $this->getJson($tautan)
            ->assertStatus(409)
            ->assertJson(['message' => 'Email sudah digunakan']);

        $this->assertSame('lama@example.test', $user->fresh()->email);
    }
}
