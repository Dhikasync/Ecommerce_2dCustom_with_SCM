<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-PROD-02, UC-10: hanya Admin Ecommerce yang mengelola kategori
 * (srs.md:398, srs.md:415). Pesan "Kategori sudah ada" dan "Kategori masih
 * digunakan oleh produk, pindahkan produk terlebih dahulu" dikunci apa
 * adanya karena ditetapkan dokumen (srs.md:406-407).
 */
class KategoriTest extends TestCase
{
    use RefreshDatabase;

    private function adminEcommerce(): User
    {
        return User::factory()->create(['role' => 'admin_ecommerce']);
    }

    private function produk(Category $kategori): Product
    {
        return Product::create([
            'category_id' => $kategori->id,
            'nama' => 'Tas Rotan',
            'deskripsi' => 'Tas anyaman rotan',
            'tipe' => 'jadi',
            'harga_dasar' => 150000,
        ]);
    }

    public function test_admin_melihat_daftar_kategori_urut_nama(): void
    {
        Category::create(['nama' => 'Tas']);
        Category::create(['nama' => 'Aksesoris']);

        $this->actingAs($this->adminEcommerce())
            ->getJson('/admin/kategori')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Aksesoris')
            ->assertJsonPath('data.1.nama', 'Tas');
    }

    public function test_admin_menambah_kategori(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/kategori', [
                'nama' => 'Tas',
                'deskripsi' => 'Tas anyaman',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Kategori berhasil ditambahkan')
            ->assertJsonPath('data.nama', 'Tas');

        $this->assertDatabaseHas('categories', [
            'nama' => 'Tas',
            'deskripsi' => 'Tas anyaman',
        ]);
    }

    public function test_deskripsi_boleh_tidak_diisi(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/kategori', ['nama' => 'Tas'])
            ->assertCreated()
            ->assertJsonPath('data.deskripsi', null);
    }

    public function test_nama_kategori_duplikat_ditolak(): void
    {
        Category::create(['nama' => 'Tas']);

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/kategori', ['nama' => 'Tas'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kategori sudah ada');

        $this->assertSame(1, Category::count());
    }

    public function test_duplikat_tanpa_membedakan_huruf_besar_kecil_ditolak(): void
    {
        Category::create(['nama' => 'Tas']);

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/kategori', ['nama' => 'TAS'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kategori sudah ada');

        $this->assertSame(1, Category::count());
    }

    public function test_nama_hanya_spasi_ditolak(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/kategori', ['nama' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama');

        $this->assertSame(0, Category::count());
    }

    public function test_spasi_di_tepi_nama_dirapikan(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/kategori', ['nama' => '  Tas  '])
            ->assertCreated()
            ->assertJsonPath('data.nama', 'Tas');
    }

    public function test_admin_mengubah_kategori(): void
    {
        $kategori = Category::create(['nama' => 'Tas']);

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/kategori/'.$kategori->id, [
                'nama' => 'Tas Anyaman',
                'deskripsi' => 'Diperbarui',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Kategori berhasil diubah')
            ->assertJsonPath('data.nama', 'Tas Anyaman');

        $this->assertDatabaseHas('categories', [
            'id' => $kategori->id,
            'nama' => 'Tas Anyaman',
            'deskripsi' => 'Diperbarui',
        ]);
    }

    public function test_ubah_dengan_nama_sendiri_tetap_boleh(): void
    {
        $kategori = Category::create(['nama' => 'Tas']);

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/kategori/'.$kategori->id, ['nama' => 'Tas'])
            ->assertOk();
    }

    public function test_ubah_ke_nama_kategori_lain_ditolak(): void
    {
        Category::create(['nama' => 'Tas']);
        $kategori = Category::create(['nama' => 'Aksesoris']);

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/kategori/'.$kategori->id, ['nama' => 'tas'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kategori sudah ada');
    }

    public function test_kategori_tidak_ditemukan_dijawab_404(): void
    {
        $admin = $this->adminEcommerce();

        $this->actingAs($admin)
            ->putJson('/admin/kategori/999', ['nama' => 'Tas'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Kategori tidak ditemukan');

        $this->actingAs($admin)
            ->deleteJson('/admin/kategori/999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Kategori tidak ditemukan');
    }

    public function test_admin_menghapus_kategori_yang_tidak_dipakai(): void
    {
        $kategori = Category::create(['nama' => 'Tas']);

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/kategori/'.$kategori->id)
            ->assertOk()
            ->assertJsonPath('message', 'Kategori berhasil dihapus');

        $this->assertDatabaseMissing('categories', ['id' => $kategori->id]);
    }

    public function test_kategori_yang_dipakai_produk_tidak_bisa_dihapus(): void
    {
        $kategori = Category::create(['nama' => 'Tas']);
        $this->produk($kategori);

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/kategori/'.$kategori->id)
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Kategori masih digunakan oleh produk, pindahkan produk terlebih dahulu'
            );

        $this->assertDatabaseHas('categories', ['id' => $kategori->id]);
    }

    public function test_user_biasa_tidak_boleh_mengelola_kategori(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/admin/kategori')->assertStatus(403);
        $this->actingAs($user)
            ->postJson('/admin/kategori', ['nama' => 'Tas'])
            ->assertStatus(403);

        $this->assertSame(0, Category::count());
    }

    public function test_admin_scm_tidak_boleh_mengelola_kategori(): void
    {
        $adminScm = User::factory()->create(['role' => 'admin_scm']);

        $this->actingAs($adminScm)->getJson('/admin/kategori')->assertStatus(403);
    }

    public function test_guest_harus_login_dulu(): void
    {
        $this->getJson('/admin/kategori')->assertStatus(401);
    }

    public function test_akun_nonaktif_ditolak_walau_sudah_login(): void
    {
        $admin = $this->adminEcommerce();
        $admin->update(['is_aktif' => false]);

        $this->actingAs($admin)->getJson('/admin/kategori')->assertStatus(403);
    }
}
