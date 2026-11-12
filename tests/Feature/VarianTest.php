<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * FR-PROD-01 #3/#8, FR-PROD-03 #1, UC-09/UC-11. Pesan "Kode SKU sudah digunakan"
 * (user-story:223), stok awal 0, dan notifikasi Admin SCM dikunci dari dokumen.
 */
class VarianTest extends TestCase
{
    use RefreshDatabase;

    private function adminEcommerce(): User
    {
        return User::factory()->create(['role' => 'admin_ecommerce']);
    }

    private function produk(): Product
    {
        return Product::create([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tas Rotan',
            'deskripsi' => 'Tas anyaman rotan',
            'tipe' => 'jadi',
            'harga_dasar' => 150000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function dataVarian(array $ganti = []): array
    {
        return array_merge([
            'sku' => 'TAS-HTM-1',
            'ukuran' => '30x40',
            'warna' => 'Hitam',
            'ketebalan' => '2 mm',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 2,
        ], $ganti);
    }

    private function varian(Product $produk, array $ganti = []): Variant
    {
        return $produk->variants()->create(array_merge([
            'sku' => 'TAS-HTM-1',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
        ], $ganti));
    }

    private function pesananUntuk(Variant $varian, string $status): OrderItem
    {
        $order = Order::create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'nomor_pesanan' => 'INV-'.$varian->id.'-'.$status,
            'status' => $status,
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'diskon_voucher' => 0,
            'total' => 150000,
            'nama_penerima' => 'Pembeli',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Contoh 1',
        ]);

        return OrderItem::create([
            'order_id' => $order->id,
            'variant_id' => $varian->id,
            'nama_produk' => 'Tas Rotan',
            'nama_varian' => 'Hitam',
            'sku' => $varian->sku,
            'quantity' => 1,
            'harga_satuan' => 150000,
        ]);
    }

    public function test_admin_melihat_daftar_varian_urut_sku(): void
    {
        $produk = $this->produk();
        $this->varian($produk, ['sku' => 'TAS-B']);
        $this->varian($produk, ['sku' => 'TAS-A']);

        $this->actingAs($this->adminEcommerce())
            ->getJson('/admin/produk/'.$produk->id.'/varian')
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'TAS-A')
            ->assertJsonPath('data.1.sku', 'TAS-B');
    }

    public function test_admin_menambah_varian_dengan_stok_nol(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $this->dataVarian())
            ->assertCreated()
            ->assertJsonPath('message', 'Varian berhasil ditambahkan')
            ->assertJsonPath('data.sku', 'TAS-HTM-1')
            ->assertJsonPath('data.moq', 2)
            ->assertJsonPath('data.stok_fisik', 0)
            ->assertJsonPath('data.stok_tersedia', 0);

        $this->assertDatabaseHas('variants', [
            'product_id' => $produk->id,
            'sku' => 'TAS-HTM-1',
            'stok_fisik' => 0,
            'stok_direservasi' => 0,
        ]);
    }

    public function test_moq_tidak_diisi_berarti_satu(): void
    {
        $produk = $this->produk();
        $data = $this->dataVarian();
        unset($data['moq']);

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $data)
            ->assertCreated()
            ->assertJsonPath('data.moq', 1);
    }

    public function test_stok_yang_dikirim_di_request_diabaikan(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $this->dataVarian([
                'stok_fisik' => 100,
                'stok_direservasi' => 5,
                'stok_minimum' => 10,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.stok_fisik', 0)
            ->assertJsonPath('data.stok_direservasi', 0)
            ->assertJsonPath('data.stok_minimum', 0);
    }

    public function test_admin_scm_aktif_menerima_notifikasi_stok_awal(): void
    {
        $produk = $this->produk();
        $aktif = User::factory()->create(['role' => 'admin_scm']);
        $nonaktif = User::factory()->create(['role' => 'admin_scm', 'is_aktif' => false]);
        // Admin Ecommerce dan User biasa tidak boleh menerima notifikasi ini.
        $this->adminEcommerce();
        User::factory()->create(['role' => 'user']);

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $this->dataVarian())
            ->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $aktif->id,
            'tipe' => 'stok_awal',
            'judul' => 'Varian baru menunggu stok awal',
        ]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $nonaktif->id]);
        $this->assertSame(1, Notification::count());
    }

    public function test_tanpa_admin_scm_tetap_tersimpan(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $this->dataVarian())
            ->assertCreated();

        $this->assertSame(0, Notification::count());
    }

    public function test_sku_duplikat_ditolak_dengan_pesan_dokumen(): void
    {
        $produk = $this->produk();
        $this->varian($produk);

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $this->dataVarian(['sku' => 'TAS-HTM-1']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kode SKU sudah digunakan');

        $this->assertSame(1, Variant::count());
    }

    public function test_sku_duplikat_beda_huruf_juga_ditolak(): void
    {
        $produk = $this->produk();
        $this->varian($produk, ['sku' => 'TAS-HTM-1']);

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $this->dataVarian(['sku' => 'tas-htm-1']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kode SKU sudah digunakan');
    }

    public function test_sku_duplikat_lintas_produk_juga_ditolak(): void
    {
        $produkA = $this->produk();
        $this->varian($produkA, ['sku' => 'TAS-HTM-1']);

        $produkB = Product::create([
            'category_id' => $produkA->category_id,
            'nama' => 'Tas Plastik',
            'deskripsi' => 'Tas plastik',
            'tipe' => 'jadi',
            'harga_dasar' => 50000,
        ]);

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produkB->id.'/varian', $this->dataVarian(['sku' => 'TAS-HTM-1']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kode SKU sudah digunakan');
    }

    public function test_berat_wajib_diisi(): void
    {
        $produk = $this->produk();
        $data = $this->dataVarian();
        unset($data['berat']);

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors('berat');
    }

    public function test_berat_nol_ditolak(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $this->dataVarian(['berat' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('berat');
    }

    public function test_harga_negatif_ditolak(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $this->dataVarian(['harga' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('harga');
    }

    public function test_moq_nol_ditolak(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/'.$produk->id.'/varian', $this->dataVarian(['moq' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('moq');
    }

    public function test_admin_mengubah_varian_dan_moq(): void
    {
        $produk = $this->produk();
        $varian = $this->varian($produk);

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produk->id.'/varian/'.$varian->id, [
                'sku' => 'TAS-HTM-1',
                'ukuran' => '40x50',
                'warna' => 'Cokelat',
                'ketebalan' => '3 mm',
                'harga' => 175000,
                'berat' => 600,
                'moq' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Varian berhasil diubah')
            ->assertJsonPath('data.ukuran', '40x50')
            ->assertJsonPath('data.harga', 175000)
            ->assertJsonPath('data.moq', 5);

        $this->assertDatabaseHas('variants', ['id' => $varian->id, 'moq' => 5, 'harga' => 175000]);
    }

    public function test_ubah_varian_tidak_mengubah_stok(): void
    {
        $produk = $this->produk();
        $varian = $this->varian($produk, ['stok_fisik' => 7, 'stok_direservasi' => 2]);

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produk->id.'/varian/'.$varian->id, [
                'sku' => $varian->sku,
                'harga' => 160000,
                'berat' => 500,
                'moq' => 3,
                'stok_fisik' => 99,
                'stok_direservasi' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.stok_fisik', 7)
            ->assertJsonPath('data.stok_direservasi', 2);
    }

    public function test_ubah_dengan_sku_sendiri_tetap_boleh(): void
    {
        $produk = $this->produk();
        $varian = $this->varian($produk, ['sku' => 'TAS-HTM-1']);

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produk->id.'/varian/'.$varian->id, $this->dataVarian([
                'sku' => 'tas-htm-1',
            ]))
            ->assertOk();
    }

    public function test_ubah_ke_sku_varian_lain_ditolak(): void
    {
        $produk = $this->produk();
        $this->varian($produk, ['sku' => 'TAS-HTM-1']);
        $kedua = $this->varian($produk, ['sku' => 'TAS-HTM-2']);

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produk->id.'/varian/'.$kedua->id, $this->dataVarian([
                'sku' => 'TAS-HTM-1',
            ]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kode SKU sudah digunakan');
    }

    public function test_stok_tersedia_dihitung_bukan_disimpan(): void
    {
        $produk = $this->produk();
        $this->varian($produk, ['stok_fisik' => 10, 'stok_direservasi' => 3]);

        $this->actingAs($this->adminEcommerce())
            ->getJson('/admin/produk/'.$produk->id.'/varian')
            ->assertOk()
            ->assertJsonPath('data.0.stok_fisik', 10)
            ->assertJsonPath('data.0.stok_direservasi', 3)
            ->assertJsonPath('data.0.stok_tersedia', 7);

        $this->assertFalse(
            Schema::hasColumn('variants', 'stok_tersedia')
        );
    }

    public function test_admin_menghapus_varian_tanpa_stok_dan_pesanan(): void
    {
        $produk = $this->produk();
        $varian = $this->varian($produk);

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id.'/varian/'.$varian->id)
            ->assertOk()
            ->assertJsonPath('message', 'Varian berhasil dihapus');

        $this->assertDatabaseMissing('variants', ['id' => $varian->id]);
    }

    public function test_varian_yang_masih_berstok_tidak_bisa_dihapus(): void
    {
        $produk = $this->produk();
        $varian = $this->varian($produk, ['stok_fisik' => 5]);

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id.'/varian/'.$varian->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Varian masih memiliki stok, kosongkan stok terlebih dahulu');

        $this->assertDatabaseHas('variants', ['id' => $varian->id]);
    }

    public function test_varian_dengan_stok_reservasi_tidak_bisa_dihapus(): void
    {
        $produk = $this->produk();
        $varian = $this->varian($produk, ['stok_direservasi' => 2]);

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id.'/varian/'.$varian->id)
            ->assertStatus(422);
    }

    public function test_varian_dengan_pesanan_aktif_tidak_bisa_dihapus(): void
    {
        $produk = $this->produk();
        $varian = $this->varian($produk);
        $this->pesananUntuk($varian, 'diproses');

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id.'/varian/'.$varian->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Varian masih memiliki pesanan aktif dan tidak dapat dihapus');

        $this->assertDatabaseHas('variants', ['id' => $varian->id]);
    }

    public function test_varian_dengan_pesanan_selesai_boleh_dihapus(): void
    {
        $produk = $this->produk();
        $varian = $this->varian($produk);
        $item = $this->pesananUntuk($varian, 'selesai');

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id.'/varian/'.$varian->id)
            ->assertOk();

        $item->refresh();
        $this->assertNull($item->variant_id);
        $this->assertSame('TAS-HTM-1', $item->sku);
    }

    public function test_varian_produk_lain_tidak_bisa_disentuh_dari_produk_ini(): void
    {
        $produkA = $this->produk();
        $produkB = Product::create([
            'category_id' => $produkA->category_id,
            'nama' => 'Tas Plastik',
            'deskripsi' => 'Tas plastik',
            'tipe' => 'jadi',
            'harga_dasar' => 50000,
        ]);
        $varianB = $this->varian($produkB, ['sku' => 'TAS-B-1']);

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produkA->id.'/varian/'.$varianB->id, $this->dataVarian())
            ->assertStatus(404)
            ->assertJsonPath('message', 'Varian tidak ditemukan');

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produkA->id.'/varian/'.$varianB->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('variants', ['id' => $varianB->id]);
    }

    public function test_produk_tidak_ditemukan_dijawab_404(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->getJson('/admin/produk/999/varian')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Produk tidak ditemukan');

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk/999/varian', $this->dataVarian())
            ->assertStatus(404);
    }

    public function test_user_biasa_dan_admin_scm_tidak_boleh_mengelola_varian(): void
    {
        $produk = $this->produk();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->getJson('/admin/produk/'.$produk->id.'/varian')
            ->assertStatus(403);

        $this->actingAs(User::factory()->create(['role' => 'admin_scm']))
            ->postJson('/admin/produk/'.$produk->id.'/varian', $this->dataVarian())
            ->assertStatus(403);
    }

    public function test_guest_harus_login_dulu(): void
    {
        $produk = $this->produk();

        $this->getJson('/admin/produk/'.$produk->id.'/varian')->assertStatus(401);
    }
}
