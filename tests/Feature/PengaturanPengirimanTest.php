<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Courier;
use App\Models\ShopSetting;
use App\Services\Profil\LokasiDitawarkan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-CKOT-04, UC-25, srs.md:520: Admin Ecommerce mengatur layanan kurir yang
 * aktif dan alamat asal toko; minimal satu layanan harus tetap aktif.
 */
class PengaturanPengirimanTest extends TestCase
{
    use RefreshDatabase;

    /** Pesan persis FR-CKOT-04 #4. */
    private const PESAN_MINIMAL_SATU = 'Minimal satu layanan kurir harus aktif';

    /** Pesan persis FR-PROF-01 #11, dirujuk FR-CKOT-04 #1 untuk lokasi asal. */
    private const PESAN_LOKASI_TIDAK_DIKENAL = 'Lokasi tidak ditemukan, coba kata kunci lain';

    private function adminEcommerce(): User
    {
        return User::factory()->create(['role' => 'admin_ecommerce']);
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function kurir(array $ganti = []): Courier
    {
        return Courier::create(array_merge([
            'kode' => 'jne',
            'nama' => 'JNE',
            'layanan' => 'reguler',
            'is_aktif' => false,
        ], $ganti));
    }

    /** Lokasi asal hanya sah bila pernah muncul di hasil pencarian (srs.md:174). */
    private function tawarkanLokasi(string $kode = '4816', string $label = 'CIBEUNYING, BANDUNG, JAWA BARAT, 40191'): void
    {
        app(LokasiDitawarkan::class)->catat([
            ['kode_lokasi' => $kode, 'label_lokasi' => $label],
        ]);
    }

    public function test_daftar_kurir_menampilkan_layanan_aktif_dan_nonaktif(): void
    {
        $this->kurir(['kode' => 'jne', 'layanan' => 'reguler', 'is_aktif' => true]);
        $this->kurir(['kode' => 'jne', 'layanan' => 'ekspres', 'is_aktif' => false]);

        $this->actingAs($this->adminEcommerce())
            ->getJson('/admin/kurir')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Urutan tidak ditetapkan dokumen; yang penting keduanya tampil.
            ->assertJsonFragment(['kode' => 'jne', 'layanan' => 'reguler', 'is_aktif' => true])
            ->assertJsonFragment(['kode' => 'jne', 'layanan' => 'ekspres', 'is_aktif' => false]);
    }

    public function test_admin_mengaktifkan_layanan_kurir(): void
    {
        $kurir = $this->kurir(['is_aktif' => false]);

        $this->actingAs($this->adminEcommerce())
            ->putJson("/admin/kurir/{$kurir->id}/aktif")
            ->assertOk()
            ->assertJsonPath('data.is_aktif', true);

        $this->assertTrue($kurir->refresh()->is_aktif);
    }

    public function test_admin_menonaktifkan_layanan_kurir(): void
    {
        $tetapAktif = $this->kurir(['kode' => 'pos', 'layanan' => 'reguler', 'is_aktif' => true]);
        $dimatikan = $this->kurir(['kode' => 'jne', 'layanan' => 'ekspres', 'is_aktif' => true]);

        $this->actingAs($this->adminEcommerce())
            ->putJson("/admin/kurir/{$dimatikan->id}/nonaktif")
            ->assertOk()
            ->assertJsonPath('data.is_aktif', false);

        $this->assertFalse($dimatikan->refresh()->is_aktif);
        $this->assertTrue($tetapAktif->refresh()->is_aktif);
    }

    public function test_layanan_aktif_terakhir_tidak_bisa_dinonaktifkan(): void
    {
        $terakhir = $this->kurir(['is_aktif' => true]);

        $this->actingAs($this->adminEcommerce())
            ->putJson("/admin/kurir/{$terakhir->id}/nonaktif")
            ->assertStatus(422)
            ->assertJsonPath('message', self::PESAN_MINIMAL_SATU);

        $this->assertTrue($terakhir->refresh()->is_aktif);
    }

    public function test_admin_menyimpan_alamat_asal_dari_lokasi_hasil_pencarian(): void
    {
        $this->tawarkanLokasi();

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/pengaturan-pengiriman', [
                'alamat_jalan' => 'Jl. Cibeunying No. 12',
                'kode_lokasi' => '4816',
                // Label dari klien harus diabaikan; server memakai label tersimpan.
                'label_lokasi' => 'Palsu',
            ])
            ->assertOk()
            ->assertJsonPath('data.alamat_jalan', 'Jl. Cibeunying No. 12')
            ->assertJsonPath('data.kode_lokasi', '4816')
            ->assertJsonPath('data.label_lokasi', 'CIBEUNYING, BANDUNG, JAWA BARAT, 40191');
    }

    public function test_alamat_asal_tanpa_kode_lokasi_ditolak(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/pengaturan-pengiriman', ['alamat_jalan' => 'Jl. Cibeunying No. 12'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('kode_lokasi');

        $this->assertSame(0, ShopSetting::count());
    }

    public function test_kode_lokasi_yang_tidak_pernah_ditawarkan_ditolak(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/pengaturan-pengiriman', [
                'alamat_jalan' => 'Jl. Cibeunying No. 12',
                'kode_lokasi' => '9999',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', self::PESAN_LOKASI_TIDAK_DIKENAL);
    }

    public function test_pengaturan_pengiriman_kosong_sebelum_diisi(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->getJson('/admin/pengaturan-pengiriman')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_alamat_asal_hanya_tersimpan_sebagai_satu_baris(): void
    {
        $this->tawarkanLokasi('4816', 'CIBEUNYING, BANDUNG, JAWA BARAT, 40191');
        $this->tawarkanLokasi('4817', 'SUMUR BANDUNG, BANDUNG, JAWA BARAT, 40111');

        $admin = $this->adminEcommerce();

        $this->actingAs($admin)
            ->putJson('/admin/pengaturan-pengiriman', [
                'alamat_jalan' => 'Jl. Cibeunying No. 12',
                'kode_lokasi' => '4816',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->putJson('/admin/pengaturan-pengiriman', [
                'alamat_jalan' => 'Jl. Sumur Bandung No. 1',
                'kode_lokasi' => '4817',
            ])
            ->assertOk()
            ->assertJsonPath('data.alamat_jalan', 'Jl. Sumur Bandung No. 1')
            ->assertJsonPath('data.kode_lokasi', '4817');

        $this->assertSame(1, ShopSetting::count());
    }

    public function test_kurir_tidak_dikenal_dibalas_404(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/kurir/999/aktif')
            ->assertNotFound()
            ->assertJsonPath('message', 'Kurir tidak ditemukan');
    }

    public function test_admin_scm_tidak_bisa_mengatur_pengiriman(): void
    {
        $scm = User::factory()->create(['role' => 'admin_scm']);

        $this->actingAs($scm)->getJson('/admin/kurir')->assertForbidden();
        $this->actingAs($scm)->getJson('/admin/pengaturan-pengiriman')->assertForbidden();
    }

    public function test_user_biasa_tidak_bisa_mengatur_pengiriman(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/admin/kurir')->assertForbidden();
    }

    public function test_guest_tidak_bisa_mengatur_pengiriman(): void
    {
        $this->getJson('/admin/kurir')->assertUnauthorized();
    }
}
