<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * FR-PROF-01 #9. Pesan "Password lama tidak sesuai" dan "Password berhasil
 * diubah" tidak diatur dokumen; disetujui pemilik proyek dan dicatat di README.
 */
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_LAMA_SALAH = 'Password lama tidak sesuai';

    private function pengguna(array $atribut = []): User
    {
        return User::factory()->create($atribut + [
            'email' => 'budi@example.test',
            'password' => 'rahasiaLama123',
        ]);
    }

    private function payload(array $ganti = []): array
    {
        return $ganti + [
            'password_lama' => 'rahasiaLama123',
            'password' => 'rahasiaBaru456',
            'password_confirmation' => 'rahasiaBaru456',
        ];
    }

    public function test_guest_tidak_boleh_mengubah_password(): void
    {
        $this->putJson('/profil/password', $this->payload())
            ->assertStatus(401);
    }

    public function test_akun_nonaktif_tidak_boleh_mengubah_password(): void
    {
        $this->actingAs($this->pengguna(['is_aktif' => false]))
            ->putJson('/profil/password', $this->payload())
            ->assertStatus(403)
            ->assertJson(['message' => 'Akun tidak aktif']);
    }

    public function test_password_berhasil_diubah(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->putJson('/profil/password', $this->payload())
            ->assertOk()
            ->assertJson(['message' => 'Password berhasil diubah']);

        $user->refresh();

        $this->assertTrue(Hash::check('rahasiaBaru456', $user->password));
        $this->assertFalse(Hash::check('rahasiaLama123', $user->password));
    }

    public function test_password_disimpan_sebagai_hash_bukan_teks_biasa(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->putJson('/profil/password', $this->payload())
            ->assertOk();

        $this->assertNotSame('rahasiaBaru456', $user->fresh()->password);
    }

    public function test_password_lama_salah_ditolak(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->putJson('/profil/password', $this->payload(['password_lama' => 'salahBanget123']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password_lama' => self::PESAN_LAMA_SALAH]);

        // Password tidak boleh berubah kalau pembuktian gagal.
        $this->assertTrue(Hash::check('rahasiaLama123', $user->fresh()->password));
    }

    public function test_password_baru_kurang_dari_8_karakter_ditolak(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->putJson('/profil/password', [
                'password_lama' => 'rahasiaLama123',
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password' => 'Password minimal 8 karakter']);

        $this->assertTrue(Hash::check('rahasiaLama123', $user->fresh()->password));
    }

    public function test_konfirmasi_password_tidak_cocok_ditolak(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->putJson('/profil/password', $this->payload([
                'password_confirmation' => 'rahasiaLain789',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password' => 'Konfirmasi password tidak sesuai']);

        $this->assertTrue(Hash::check('rahasiaLama123', $user->fresh()->password));
    }

    public function test_password_lama_kosong_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->putJson('/profil/password', $this->payload(['password_lama' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password_lama' => 'Password lama wajib diisi']);
    }

    public function test_password_baru_kosong_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->putJson('/profil/password', [
                'password_lama' => 'rahasiaLama123',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password' => 'Password baru wajib diisi']);
    }

    public function test_sesi_yang_sedang_dipakai_tetap_berjalan(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->putJson('/profil/password', $this->payload())
            ->assertOk();

        // Sesi sendiri tidak boleh ikut putus setelah ganti password (#9).
        $this->getJson('/profil')
            ->assertOk()
            ->assertJsonPath('data.email', 'budi@example.test');
    }

    public function test_sesi_lain_diputus_setelah_password_diubah(): void
    {
        $this->pengguna();

        $this->postJson('/login', [
            'email' => 'budi@example.test',
            'password' => 'rahasiaLama123',
        ])->assertOk();

        // Sesi A: perangkat lain yang masih memegang hash password lama.
        $sesiLama = session()->all();

        $this->postJson('/logout')->assertOk();

        // Sesi B: perangkat ini, tempat password diganti.
        $this->postJson('/login', [
            'email' => 'budi@example.test',
            'password' => 'rahasiaLama123',
        ])->assertOk();

        $this->putJson('/profil/password', $this->payload())->assertOk();

        $this->withSession($sesiLama)
            ->getJson('/profil')
            ->assertStatus(401);
    }

    public function test_remember_token_diganti(): void
    {
        $user = $this->pengguna();

        $user->forceFill(['remember_token' => 'token-lama'])->save();

        $this->actingAs($user)
            ->putJson('/profil/password', $this->payload())
            ->assertOk();

        $this->assertNotSame('token-lama', $user->fresh()->remember_token);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_login_memakai_password_baru_setelah_diubah(): void
    {
        $this->pengguna();

        $this->postJson('/login', [
            'email' => 'budi@example.test',
            'password' => 'rahasiaLama123',
        ])->assertOk();

        $this->putJson('/profil/password', $this->payload())->assertOk();

        $this->postJson('/logout')->assertOk();

        // Password lama tidak berlaku lagi...
        $this->postJson('/login', [
            'email' => 'budi@example.test',
            'password' => 'rahasiaLama123',
        ])->assertStatus(422);

        // ...dan password baru bisa dipakai.
        $this->postJson('/login', [
            'email' => 'budi@example.test',
            'password' => 'rahasiaBaru456',
        ])->assertOk();
    }
}
