<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'no_hp' => '+628123456789',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $override);
    }

    public function test_registrasi_membuat_akun_belum_verifikasi_dan_tetap_guest(): void
    {
        Mail::fake();

        $response = $this->postJson('/register', $this->payload());

        $response->assertCreated();
        $response->assertJson([
            'message' => 'Cek email Anda',
            'email' => 'budi@example.test',
        ]);

        $this->assertGuest();

        $user = User::where('email', 'budi@example.test')->firstOrFail();

        $this->assertSame('user', $user->role);
        $this->assertTrue($user->is_aktif);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(64, strlen($user->email_verification_token));
        $this->assertTrue(Hash::check('rahasia123', $user->password));

        Mail::assertSent(VerifyEmailMail::class);
    }

    public function test_role_dan_status_dari_request_diabaikan(): void
    {
        Mail::fake();

        $this->postJson('/register', $this->payload([
            'role' => 'admin_scm',
            'is_aktif' => false,
        ]))->assertCreated();

        $user = User::firstOrFail();

        $this->assertSame('user', $user->role);
        $this->assertTrue($user->is_aktif);
    }

    public function test_nomor_telepon_wajib_berawalan_plus_62_saat_registrasi(): void
    {
        Mail::fake();

        // Aturan format yang sama dipakai di profil dan registrasi, supaya
        // nomor yang tersimpan saat daftar tidak ditolak saat User menyimpan
        // profilnya nanti.
        $this->postJson('/register', $this->payload(['no_hp' => '08123456789']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['no_hp' => 'Format nomor telepon tidak valid']);

        $this->assertSame(0, User::count());
        Mail::assertNothingSent();
    }

    public function test_nomor_telepon_boleh_tidak_diisi_saat_registrasi(): void
    {
        Mail::fake();

        $this->postJson('/register', $this->payload(['no_hp' => null]))
            ->assertCreated();

        $this->assertNull(User::firstOrFail()->no_hp);
    }

    public function test_registrasi_tidak_valid_ditolak_tanpa_membuat_akun(): void
    {
        Mail::fake();

        $response = $this->postJson('/register', [
            'email' => 'bukan-email',
            'password' => 'rahasia123',
            'password_confirmation' => 'beda123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertSame(0, User::count());
        Mail::assertNothingSent();
    }

    public function test_dua_akun_dengan_email_sama_ditolak(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'budi@example.test']);

        $response = $this->postJson('/register', $this->payload());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_email_disimpan_dalam_huruf_kecil(): void
    {
        Mail::fake();

        $this->postJson('/register', $this->payload(['email' => 'Budi@Example.Test']))
            ->assertCreated()
            ->assertJson(['email' => 'budi@example.test']);

        $this->assertDatabaseHas('users', ['email' => 'budi@example.test']);
    }

    public function test_email_yang_berbeda_huruf_besar_kecil_dianggap_sama(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'budi@example.test']);

        $this->postJson('/register', $this->payload(['email' => 'BUDI@example.test']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(1, User::count());
    }

    public function test_user_yang_sudah_login_diarahkan_ke_halaman_utama(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/register', $this->payload())
            ->assertRedirect(route('home'));
    }
}
