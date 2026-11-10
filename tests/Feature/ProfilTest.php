<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-PROF-01 #1, #2, #3, #10, UC-06: GET /profil dan PUT /profil.
 *
 * Aturan nomor telepon (+62 lalu 9-12 angka) adalah keputusan pemilik
 * proyek, bukan kutipan dokumen; dokumen hanya menetapkan pesannya
 * ("Format nomor telepon tidak valid") dan bahwa field ini opsional.
 */
class ProfilTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(array $atribut = []): User
    {
        return User::factory()->create($atribut);
    }

    public function test_guest_tidak_boleh_melihat_profil(): void
    {
        $this->getJson('/profil')->assertStatus(401);
    }

    public function test_guest_tidak_boleh_mengubah_profil(): void
    {
        $this->putJson('/profil', ['name' => 'Penyusup'])->assertStatus(401);
    }

    public function test_profil_menampilkan_nama_email_nomor_telepon_dan_foto(): void
    {
        $user = $this->pengguna([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'no_hp' => '+628123456789',
            'foto_profil' => 'profil/budi.jpg',
        ]);

        $this->actingAs($user)
            ->getJson('/profil')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'name' => 'Budi Santoso',
                    'email' => 'budi@example.test',
                    'no_hp' => '+628123456789',
                    'foto_profil' => 'profil/budi.jpg',
                    'pending_email' => null,
                    'alamat' => [],
                ],
            ]);
    }

    public function test_foto_profil_bernilai_null_kalau_belum_ada(): void
    {
        $this->actingAs($this->pengguna())
            ->getJson('/profil')
            ->assertOk()
            ->assertJsonPath('data.foto_profil', null);
    }

    public function test_profil_tidak_membocorkan_password_atau_token(): void
    {
        $this->actingAs($this->pengguna())
            ->getJson('/profil')
            ->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.email_verification_token')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_nama_dan_nomor_telepon_tersimpan(): void
    {
        $user = $this->pengguna(['name' => 'Nama Lama', 'no_hp' => null]);

        $this->actingAs($user)
            ->putJson('/profil', [
                'name' => 'Budi Santoso',
                'no_hp' => '+628123456789',
            ])
            ->assertOk()
            ->assertExactJson([
                'message' => 'Profil berhasil diperbarui',
                'data' => [
                    'name' => 'Budi Santoso',
                    'email' => $user->email,
                    'no_hp' => '+628123456789',
                    'foto_profil' => null,
                    'pending_email' => null,
                    'alamat' => [],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Budi Santoso',
            'no_hp' => '+628123456789',
        ]);
    }

    public function test_spasi_di_ujung_nama_dibuang(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->putJson('/profil', ['name' => '  Budi Santoso  '])
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi Santoso');
    }

    public function test_nomor_telepon_tidak_dikirim_berarti_tidak_diubah(): void
    {
        $user = $this->pengguna(['no_hp' => '+628111111111']);

        $this->actingAs($user)
            ->putJson('/profil', ['name' => 'Nama Baru'])
            ->assertOk()
            ->assertJsonPath('data.no_hp', '+628111111111');
    }

    public function test_nomor_telepon_dapat_dikosongkan_dengan_null(): void
    {
        $user = $this->pengguna(['no_hp' => '+628111111111']);

        $this->actingAs($user)
            ->putJson('/profil', ['name' => 'Nama Baru', 'no_hp' => null])
            ->assertOk()
            ->assertJsonPath('data.no_hp', null);
    }

    public function test_nama_kosong_ditolak_dan_tidak_menyimpan_perubahan(): void
    {
        $user = $this->pengguna(['name' => 'Nama Lama', 'no_hp' => '+628111111111']);

        $this->actingAs($user)
            ->putJson('/profil', ['name' => '', 'no_hp' => '+628222222222'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Nama wajib diisi']);

        $user->refresh();

        // FR-PROF-01 #10: "tidak menyimpan perubahan" — nomor telepon pun
        // tidak boleh ikut tersimpan sebagian.
        $this->assertSame('Nama Lama', $user->name);
        $this->assertSame('+628111111111', $user->no_hp);
    }

    public function test_nama_hanya_spasi_dianggap_kosong(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->putJson('/profil', ['name' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Nama wajib diisi']);
    }

    public function test_nama_terlalu_panjang_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->putJson('/profil', ['name' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Nama maksimal 255 karakter']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nomorTidakValid(): array
    {
        return [
            'gaya 08 ditolak' => ['08123456789'],
            'tanpa awalan' => ['8123456789'],
            'mengandung huruf' => ['+62812abc789'],
            'terlalu pendek' => ['+6281234'],
            'terlalu panjang' => ['+628123456789012'],
            'awalan negara lain' => ['+658123456789'],
            'tanda hubung' => ['+62-812-3456-789'],
            'spasi' => ['+62 812 3456 789'],
        ];
    }

    #[DataProvider('nomorTidakValid')]
    public function test_format_nomor_telepon_tidak_valid_ditolak(string $nomor): void
    {
        $user = $this->pengguna(['no_hp' => '+628111111111']);

        $this->actingAs($user)
            ->putJson('/profil', ['name' => 'Budi', 'no_hp' => $nomor])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['no_hp' => 'Format nomor telepon tidak valid']);

        $this->assertSame('+628111111111', $user->fresh()->no_hp);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nomorValid(): array
    {
        return [
            'batas bawah 9 angka' => ['+62812345678'],
            'batas atas 12 angka' => ['+62812345678901'],
        ];
    }

    #[DataProvider('nomorValid')]
    public function test_format_nomor_telepon_valid_diterima(string $nomor): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->putJson('/profil', ['name' => 'Budi', 'no_hp' => $nomor])
            ->assertOk()
            ->assertJsonPath('data.no_hp', $nomor);
    }

    public function test_email_role_dan_foto_tidak_dapat_diubah_lewat_profil(): void
    {
        $user = $this->pengguna([
            'email' => 'asli@example.test',
            'role' => 'user',
            'foto_profil' => null,
        ]);

        $this->actingAs($user)
            ->putJson('/profil', [
                'name' => 'Budi',
                'email' => 'penyusup@example.test',
                'role' => 'admin_scm',
                'foto_profil' => 'profil/palsu.jpg',
                'is_aktif' => false,
            ])
            ->assertOk();

        $user->refresh();

        $this->assertSame('asli@example.test', $user->email);
        $this->assertSame('user', $user->role);
        $this->assertNull($user->foto_profil);
        $this->assertTrue($user->is_aktif);
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $user = $this->pengguna(['is_aktif' => false]);

        $this->actingAs($user)
            ->getJson('/profil')
            ->assertStatus(403)
            ->assertJson(['message' => 'Akun tidak aktif']);

        $this->actingAs($user)
            ->putJson('/profil', ['name' => 'Penyusup'])
            ->assertStatus(403)
            ->assertJson(['message' => 'Akun tidak aktif']);
    }

    public function test_hanya_profil_milik_sendiri_yang_terlihat(): void
    {
        $budi = $this->pengguna(['name' => 'Budi']);
        $siti = $this->pengguna(['name' => 'Siti']);

        $this->actingAs($budi)
            ->getJson('/profil')
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi');

        $this->actingAs($siti)
            ->getJson('/profil')
            ->assertOk()
            ->assertJsonPath('data.name', 'Siti');
    }
}
