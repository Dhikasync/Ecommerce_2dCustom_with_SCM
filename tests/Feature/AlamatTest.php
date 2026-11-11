<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Profil\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-PROF-01 #4-#7, UC-07: kelola alamat pengiriman.
 *
 * Aturan dokumen yang diuji di sini:
 * - alamat hanya tersimpan dengan lokasi terpilih dari hasil pencarian
 *   (kode lokasi + label lengkap), bukan diketik bebas (#4, #5, #11),
 * - menghapus alamat hanya boleh kalau User punya lebih dari satu (#6),
 * - hanya ada satu alamat utama per User, ditandai lewat aksi tersendiri (#7),
 * - User hanya menyentuh alamatnya sendiri (NFR-SEC-03).
 *
 * Perilaku yang tidak diatur dokumen dan disetujui pemilik proyek:
 * - alamat pertama otomatis menjadi utama,
 * - menghapus alamat utama mempromosikan alamat lain yang paling lama,
 * - teks pesan validasi (dokumen hanya menetapkan pesan nomor telepon).
 */
class AlamatTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(array $atribut = []): User
    {
        return User::factory()->create($atribut);
    }

    private function payload(array $ganti = []): array
    {
        return $ganti + [
            'nama_penerima' => 'Budi Santoso',
            'no_hp' => '+628123456789',
            'alamat_lengkap' => 'Jl. Airlangga No. 10',
            'kode_lokasi' => '1140',
            'label_lokasi' => 'AIRLANGGA, GUBENG, SURABAYA, JAWA TIMUR, 60286',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function alamatTersimpan(): array
    {
        return [
            'nama_penerima' => 'Budi Santoso',
            'no_hp' => '+628123456789',
            'alamat_lengkap' => 'Jl. Airlangga No. 10',
            'kode_lokasi' => '1140',
            'label_lokasi' => 'AIRLANGGA, GUBENG, SURABAYA, JAWA TIMUR, 60286',
            'is_utama' => true,
        ];
    }

    public function test_guest_tidak_boleh_melihat_daftar_alamat(): void
    {
        $this->getJson('/profil/alamat')->assertStatus(401);
    }

    public function test_guest_tidak_boleh_menambah_alamat(): void
    {
        $this->postJson('/profil/alamat', $this->payload())->assertStatus(401);

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_akun_nonaktif_tidak_boleh_mengelola_alamat(): void
    {
        $this->actingAs($this->pengguna(['is_aktif' => false]))
            ->postJson('/profil/alamat', $this->payload())
            ->assertStatus(403)
            ->assertJson(['message' => 'Akun tidak aktif']);
    }

    public function test_daftar_alamat_kosong(): void
    {
        $this->actingAs($this->pengguna())
            ->getJson('/profil/alamat')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_alamat_pertama_berhasil_ditambahkan_dan_menjadi_utama(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->postJson('/profil/alamat', $this->payload())
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Alamat berhasil ditambahkan',
                'data' => $this->alamatTersimpan(),
            ]);

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->getKey(),
            'kode_lokasi' => '1140',
            'is_utama' => true,
        ]);
    }

    public function test_alamat_kedua_tidak_otomatis_menjadi_utama(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)->postJson('/profil/alamat', $this->payload())->assertStatus(201);

        $this->actingAs($user)
            ->postJson('/profil/alamat', $this->payload([
                'nama_penerima' => 'Siti Aminah',
                'kode_lokasi' => '2274',
                'label_lokasi' => 'KETABANG, GENTENG, SURABAYA, JAWA TIMUR, 60272',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.is_utama', false);

        $this->assertSame(1, Address::where('user_id', $user->getKey())->where('is_utama', true)->count());
    }

    public function test_nama_penerima_wajib_diisi(): void
    {
        $this->actingAs($this->pengguna())
            ->postJson('/profil/alamat', $this->payload(['nama_penerima' => '   ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nama_penerima' => 'Nama penerima wajib diisi']);
    }

    public function test_nomor_telepon_wajib_diisi(): void
    {
        $this->actingAs($this->pengguna())
            ->postJson('/profil/alamat', $this->payload(['no_hp' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['no_hp' => 'Nomor telepon wajib diisi']);
    }

    public function test_format_nomor_telepon_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->postJson('/profil/alamat', $this->payload(['no_hp' => '08123456789']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['no_hp' => 'Format nomor telepon tidak valid']);
    }

    public function test_alamat_lengkap_wajib_diisi(): void
    {
        $this->actingAs($this->pengguna())
            ->postJson('/profil/alamat', $this->payload(['alamat_lengkap' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alamat_lengkap' => 'Alamat lengkap wajib diisi']);
    }

    public function test_lokasi_wajib_dipilih(): void
    {
        $this->actingAs($this->pengguna())
            ->postJson('/profil/alamat', $this->payload(['kode_lokasi' => '', 'label_lokasi' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_lokasi' => 'Lokasi wajib dipilih']);

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_daftar_alamat_hanya_milik_sendiri(): void
    {
        $user = $this->pengguna();
        $lain = $this->pengguna();

        Address::create($this->payload() + ['user_id' => $user->getKey(), 'is_utama' => true]);
        Address::create($this->payload(['nama_penerima' => 'Orang Lain']) + ['user_id' => $lain->getKey(), 'is_utama' => true]);

        $this->actingAs($user)
            ->getJson('/profil/alamat')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_penerima', 'Budi Santoso');
    }

    public function test_profil_menampilkan_daftar_alamat(): void
    {
        $user = $this->pengguna(['name' => 'Budi']);

        Address::create($this->payload() + ['user_id' => $user->getKey(), 'is_utama' => true]);

        // srs.md:375 — membuka profil menampilkan nama, email, dan daftar alamat.
        $this->actingAs($user)
            ->getJson('/profil')
            ->assertOk()
            ->assertJsonCount(1, 'data.alamat')
            ->assertJsonPath('data.alamat.0.kode_lokasi', '1140');
    }

    public function test_ubah_alamat_berhasil(): void
    {
        $user = $this->pengguna();
        $alamat = Address::create($this->payload() + ['user_id' => $user->getKey(), 'is_utama' => true]);

        $this->actingAs($user)
            ->putJson("/profil/alamat/{$alamat->getKey()}", $this->payload([
                'nama_penerima' => 'Budi Santoso Baru',
                'alamat_lengkap' => 'Jl. Dharmawangsa No. 5',
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Alamat berhasil diperbarui')
            ->assertJsonPath('data.nama_penerima', 'Budi Santoso Baru')
            ->assertJsonPath('data.alamat_lengkap', 'Jl. Dharmawangsa No. 5');

        $this->assertDatabaseHas('addresses', [
            'id' => $alamat->getKey(),
            'nama_penerima' => 'Budi Santoso Baru',
        ]);
    }

    public function test_alamat_lama_tanpa_lokasi_harus_memilih_lokasi_saat_disimpan(): void
    {
        $user = $this->pengguna();
        $alamat = Address::create([
            'user_id' => $user->getKey(),
            'nama_penerima' => 'Budi Santoso',
            'no_hp' => '+628123456789',
            'alamat_lengkap' => 'Jl. Airlangga No. 10',
            'is_utama' => true,
        ]);

        // Skenario #5: tanpa lokasi terpilih, perubahan tidak boleh tersimpan.
        $this->actingAs($user)
            ->putJson("/profil/alamat/{$alamat->getKey()}", $this->payload([
                'alamat_lengkap' => 'Jl. Baru No. 1',
                'kode_lokasi' => '',
                'label_lokasi' => '',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_lokasi' => 'Lokasi wajib dipilih']);

        $this->assertDatabaseHas('addresses', [
            'id' => $alamat->getKey(),
            'alamat_lengkap' => 'Jl. Airlangga No. 10',
            'kode_lokasi' => null,
        ]);
    }

    public function test_ubah_alamat_milik_user_lain_ditolak(): void
    {
        $user = $this->pengguna();
        $lain = $this->pengguna();

        $alamat = Address::create($this->payload() + ['user_id' => $lain->getKey(), 'is_utama' => true]);

        $this->actingAs($user)
            ->putJson("/profil/alamat/{$alamat->getKey()}", $this->payload(['nama_penerima' => 'Diambil Alih']))
            ->assertStatus(404)
            ->assertJson(['message' => 'Alamat tidak ditemukan']);

        $this->assertDatabaseHas('addresses', [
            'id' => $alamat->getKey(),
            'nama_penerima' => 'Budi Santoso',
        ]);
    }

    public function test_menetapkan_alamat_utama(): void
    {
        $user = $this->pengguna();

        $utama = Address::create($this->payload() + ['user_id' => $user->getKey(), 'is_utama' => true]);
        $kedua = Address::create($this->payload(['nama_penerima' => 'Siti Aminah']) + ['user_id' => $user->getKey()]);

        $this->actingAs($user)
            ->putJson("/profil/alamat/{$kedua->getKey()}/utama")
            ->assertOk()
            ->assertJson([
                'message' => 'Alamat utama berhasil ditetapkan',
                'data' => ['id' => $kedua->getKey(), 'is_utama' => true],
            ]);

        $this->assertFalse($utama->fresh()->is_utama);
        $this->assertTrue($kedua->fresh()->is_utama);
        $this->assertSame(1, Address::where('user_id', $user->getKey())->where('is_utama', true)->count());
    }

    public function test_menetapkan_alamat_utama_milik_user_lain_ditolak(): void
    {
        $user = $this->pengguna();
        $lain = $this->pengguna();

        $alamat = Address::create($this->payload() + ['user_id' => $lain->getKey(), 'is_utama' => true]);

        $this->actingAs($user)
            ->putJson("/profil/alamat/{$alamat->getKey()}/utama")
            ->assertStatus(404);

        $this->assertTrue($alamat->fresh()->is_utama);
    }

    public function test_hapus_alamat_berhasil(): void
    {
        $user = $this->pengguna();

        Address::create($this->payload() + ['user_id' => $user->getKey(), 'is_utama' => true]);
        $kedua = Address::create($this->payload(['nama_penerima' => 'Siti Aminah']) + ['user_id' => $user->getKey()]);

        $this->actingAs($user)
            ->deleteJson("/profil/alamat/{$kedua->getKey()}")
            ->assertOk()
            ->assertJson(['message' => 'Alamat berhasil dihapus']);

        $this->assertDatabaseMissing('addresses', ['id' => $kedua->getKey()]);
    }

    public function test_alamat_terakhir_tidak_boleh_dihapus(): void
    {
        $user = $this->pengguna();
        $alamat = Address::create($this->payload() + ['user_id' => $user->getKey(), 'is_utama' => true]);

        // Skenario #6 hanya berlaku kalau User punya lebih dari satu alamat.
        $this->actingAs($user)
            ->deleteJson("/profil/alamat/{$alamat->getKey()}")
            ->assertStatus(422)
            ->assertJson(['message' => 'Minimal satu alamat harus tetap tersimpan']);

        $this->assertDatabaseHas('addresses', ['id' => $alamat->getKey()]);
    }

    public function test_hapus_alamat_utama_mempromosikan_alamat_lain(): void
    {
        $user = $this->pengguna();

        $utama = Address::create($this->payload() + ['user_id' => $user->getKey(), 'is_utama' => true]);
        $kedua = Address::create($this->payload(['nama_penerima' => 'Siti Aminah']) + ['user_id' => $user->getKey()]);

        $this->actingAs($user)
            ->deleteJson("/profil/alamat/{$utama->getKey()}")
            ->assertOk();

        $this->assertTrue($kedua->fresh()->is_utama);
        $this->assertSame(1, Address::where('user_id', $user->getKey())->where('is_utama', true)->count());
    }

    public function test_hapus_alamat_milik_user_lain_ditolak(): void
    {
        $user = $this->pengguna();
        $lain = $this->pengguna();

        $alamat = Address::create($this->payload() + ['user_id' => $lain->getKey(), 'is_utama' => true]);
        Address::create($this->payload(['nama_penerima' => 'Cadangan']) + ['user_id' => $lain->getKey()]);

        $this->actingAs($user)
            ->deleteJson("/profil/alamat/{$alamat->getKey()}")
            ->assertStatus(404);

        $this->assertDatabaseHas('addresses', ['id' => $alamat->getKey()]);
    }

    public function test_is_utama_tidak_bisa_diubah_lewat_ubah_alamat(): void
    {
        $user = $this->pengguna();

        $utama = Address::create($this->payload() + ['user_id' => $user->getKey(), 'is_utama' => true]);
        $kedua = Address::create($this->payload(['nama_penerima' => 'Siti Aminah']) + ['user_id' => $user->getKey()]);

        // Penanda utama hanya lewat aksi #7; kalau ikut di sini, dua alamat
        // bisa sama-sama utama dan unique index di database akan menolak.
        $this->actingAs($user)
            ->putJson("/profil/alamat/{$kedua->getKey()}", $this->payload([
                'nama_penerima' => 'Siti Aminah',
                'is_utama' => true,
            ]))
            ->assertOk()
            ->assertJsonPath('data.is_utama', false);

        $this->assertTrue($utama->fresh()->is_utama);
    }
}
