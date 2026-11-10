<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_KREDENSIAL = 'Email atau password salah';

    private function user(array $override = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'budi@example.test',
            'password' => 'rahasia123',
        ], $override));
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'email' => 'budi@example.test',
            'password' => 'rahasia123',
        ], $override);
    }

    public function test_halaman_login_dapat_diakses_guest(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertJson(['message' => 'Silakan login']);

        $this->assertSame(url('/login'), route('login'));
        $this->assertSame(url('/login'), route('login.store'));
    }

    public function test_login_berhasil_dan_sesi_diperbarui(): void
    {
        $user = $this->user();

        $this->get('/login');
        $idSesiLama = session()->getId();

        $this->postJson('/login', $this->payload())
            ->assertOk()
            ->assertJson([
                'message' => 'Login berhasil',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => 'budi@example.test',
                    'role' => 'user',
                ],
                'redirect' => route('home'),
            ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($idSesiLama, session()->getId());
    }

    public function test_password_salah_dan_email_tidak_terdaftar_memberi_pesan_sama(): void
    {
        $this->user();

        $this->postJson('/login', $this->payload(['password' => 'salah123']))
            ->assertStatus(422)
            ->assertJson(['message' => self::PESAN_KREDENSIAL]);

        $this->assertGuest();

        $this->postJson('/login', $this->payload(['email' => 'tidak-ada@example.test']))
            ->assertStatus(422)
            ->assertJson(['message' => self::PESAN_KREDENSIAL]);

        $this->assertGuest();
    }

    public function test_akun_nonaktif_ditolak_dengan_pesan_yang_sama(): void
    {
        $this->user(['is_aktif' => false]);

        $this->postJson('/login', $this->payload())
            ->assertStatus(422)
            ->assertJson(['message' => self::PESAN_KREDENSIAL]);

        $this->assertGuest();
    }

    public function test_email_tidak_terdaftar_tetap_memeriksa_hash_password(): void
    {
        // Tanpa hash palsu, email tak terdaftar kembali tanpa memanggil bcrypt,
        // sehingga waktunya bisa dipakai menebak email mana yang punya akun.
        Hash::shouldReceive('check')->once()->andReturnFalse();

        $this->postJson('/login', [
            'email' => 'tidak-ada@example.test',
            'password' => 'rahasia123',
        ])
            ->assertStatus(422)
            ->assertJson(['message' => self::PESAN_KREDENSIAL]);
    }

    public function test_akun_belum_verifikasi_ditolak_dan_boleh_kirim_ulang(): void
    {
        $this->user(['email_verified_at' => null]);

        $this->postJson('/login', $this->payload())
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Silakan verifikasi email Anda terlebih dahulu',
                'can_resend_verification' => true,
            ]);

        $this->assertGuest();
    }

    public function test_setelah_login_diarahkan_kembali_ke_halaman_yang_diminta(): void
    {
        $this->user();

        $this->withSession(['url.intended' => url('/checkout')])
            ->postJson('/login', $this->payload())
            ->assertOk()
            ->assertJson(['redirect' => url('/checkout')]);

        $this->assertAuthenticated();
    }

    public function test_tanpa_halaman_yang_diminta_diarahkan_ke_halaman_utama(): void
    {
        $this->user();

        $this->postJson('/login', $this->payload())
            ->assertOk()
            ->assertJson(['redirect' => route('home')]);
    }

    public function test_email_huruf_besar_tetap_bisa_login(): void
    {
        $this->user();

        $this->postJson('/login', $this->payload(['email' => 'BUDI@Example.Test']))
            ->assertOk()
            ->assertJson(['message' => 'Login berhasil']);

        $this->assertAuthenticated();
    }

    public function test_tujuan_login_ke_host_lain_diabaikan(): void
    {
        $this->user();

        // url.intended dibentuk dari header Host, jadi host asing tidak boleh
        // dipakai sebagai tujuan redirect setelah login.
        $this->withSession(['url.intended' => 'https://situs-palsu.example/masuk'])
            ->postJson('/login', $this->payload())
            ->assertOk()
            ->assertJson(['redirect' => route('home')]);

        $this->assertAuthenticated();
    }

    public function test_field_kosong_ditolak_dengan_pesan_wajib_diisi(): void
    {
        $this->postJson('/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonPath('errors.email.0', 'Email dan password wajib diisi')
            ->assertJsonPath('errors.password.0', 'Email dan password wajib diisi');
    }

    public function test_lima_kegagalan_mengunci_login_lima_belas_menit(): void
    {
        $this->user();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/login', $this->payload(['password' => 'salah123']))
                ->assertStatus(422);
        }

        $this->postJson('/login', $this->payload())
            ->assertStatus(429)
            ->assertJson(['message' => 'Terlalu banyak permintaan, coba lagi beberapa saat lagi']);

        $this->assertGuest();
    }

    public function test_lima_kegagalan_mengunci_akun_walau_ip_berganti(): void
    {
        $this->user();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/login', $this->payload(['password' => 'salah123']))
                ->assertStatus(422);
        }

        // IP berbeda tidak memberi jatah baru untuk akun yang sama.
        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson('/login', $this->payload())
            ->assertStatus(429);
    }

    public function test_akun_lain_tetap_bisa_login_dari_ip_yang_sama(): void
    {
        $this->user();

        User::factory()->create([
            'email' => 'sari@example.test',
            'password' => 'rahasia123',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/login', $this->payload(['password' => 'salah123']))
                ->assertStatus(422);
        }

        $this->postJson('/login', [
            'email' => 'sari@example.test',
            'password' => 'rahasia123',
        ])->assertOk();
    }

    public function test_login_sukses_membersihkan_hitungan_akun(): void
    {
        $this->user();

        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/login', $this->payload(['password' => 'salah123']))
                ->assertStatus(422);
        }

        $this->postJson('/login', $this->payload())->assertOk();
        $this->postJson('/logout')->assertOk();

        // Jatah akun kembali penuh: 4 kegagalan berikutnya masih 422, bukan 429.
        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/login', $this->payload(['password' => 'salah123']))
                ->assertStatus(422);
        }
    }

    public function test_satu_ip_tidak_bisa_menebak_banyak_akun_tanpa_batas(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            User::factory()->create([
                'email' => "akun{$i}@example.test",
                'password' => 'rahasia123',
            ]);
        }

        for ($i = 1; $i <= 20; $i++) {
            $this->postJson('/login', [
                'email' => "akun{$i}@example.test",
                'password' => 'salah123',
            ])->assertStatus(422);
        }

        // Percobaan ke-21 dari IP yang sama diblokir walau akunnya berbeda.
        $this->postJson('/login', [
            'email' => 'akun1@example.test',
            'password' => 'rahasia123',
        ])->assertStatus(429);
    }

    public function test_user_yang_sudah_login_diarahkan_ke_halaman_utama(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect(route('home'));

        $this->actingAs($user)
            ->postJson('/login', $this->payload())
            ->assertRedirect(route('home'));
    }

    public function test_sesi_berakhir_setelah_24_jam_tanpa_aktivitas(): void
    {
        $this->assertSame(1440, config('session.lifetime'));
    }
}
