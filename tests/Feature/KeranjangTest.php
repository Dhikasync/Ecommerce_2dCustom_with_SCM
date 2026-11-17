<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Design;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-CART-01, UC-24: tambah, ubah jumlah, hapus item, keranjang kosong, dan
 * harga yang dikunci saat item masuk. Item custom masih dummy (`design_id`)
 * sampai FR-CDSN-04 membangun preview desain.
 */
class KeranjangTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** Skenario #1 + #6: item tersimpan dengan harga saat itu, bukan harga katalog. */
    public function test_tambah_produk_jadi_menyimpan_item_dengan_harga_saat_itu(): void
    {
        $varian = $this->varian(['harga' => 20000, 'moq' => 2, 'stok_fisik' => 10]);

        $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.harga_satuan', 20000)
            ->assertJsonPath('data.subtotal', 60000);

        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $varian->id,
            'quantity' => 3,
            'harga_satuan' => 20000,
        ]);
    }

    /** Skenario #6: kenaikan harga katalog tidak mengubah item di keranjang. */
    public function test_harga_item_tidak_berubah_saat_harga_katalog_naik(): void
    {
        $varian = $this->varian(['harga' => 20000]);

        $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 2])
            ->assertCreated();

        $varian->update(['harga' => 25000]);

        $this->getJson('/keranjang')
            ->assertOk()
            ->assertJsonPath('data.jadi.0.harga_satuan', 20000)
            ->assertJsonPath('data.jadi.0.subtotal', 40000);
    }

    /** Index unik `cart_items_produk_jadi_unique` memaksa varian sama menyatu. */
    public function test_menambah_varian_yang_sama_menggabungkan_quantity(): void
    {
        $varian = $this->varian(['harga' => 20000, 'stok_fisik' => 10]);

        foreach ([2, 3] as $jumlah) {
            $this->actingAs($this->user)
                ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => $jumlah])
                ->assertCreated();
        }

        $this->getJson('/keranjang')
            ->assertOk()
            ->assertJsonCount(1, 'data.jadi')
            ->assertJsonPath('data.jadi.0.quantity', 5)
            ->assertJsonPath('data.jadi.0.harga_satuan', 20000);
    }

    /** FR-PROD-03 #2: pesan persis dokumen. */
    public function test_quantity_di_bawah_moq_ditolak(): void
    {
        $varian = $this->varian(['moq' => 3, 'stok_fisik' => 10]);

        $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 2])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Minimal pemesanan untuk varian ini adalah 3 pcs');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_quantity_melebihi_stok_ditolak(): void
    {
        $varian = $this->varian(['stok_fisik' => 5]);

        $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 6])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stok tidak mencukupi');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_varian_stok_habis_tidak_bisa_ditambahkan(): void
    {
        $varian = $this->varian(['stok_fisik' => 2, 'stok_direservasi' => 2]);

        $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stok tidak mencukupi');
    }

    /** Skenario #1: produk yang dinonaktifkan Admin tidak bisa ditambahkan. */
    public function test_produk_nonaktif_tidak_bisa_ditambahkan(): void
    {
        $varian = $this->varian([], ['is_aktif' => false]);

        $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Produk tidak tersedia');
    }

    /** Skenario #3: subtotal dihitung ulang dengan harga yang dikunci. */
    public function test_ubah_quantity_menghitung_ulang_subtotal(): void
    {
        $varian = $this->varian(['harga' => 20000, 'moq' => 2, 'stok_fisik' => 10]);

        $itemId = $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 2])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->user)
            ->patchJson("/keranjang/item/{$itemId}", ['quantity' => 5])
            ->assertOk()
            ->assertJsonPath('data.quantity', 5)
            ->assertJsonPath('data.subtotal', 100000);
    }

    public function test_ubah_quantity_di_bawah_moq_ditolak(): void
    {
        $varian = $this->varian(['moq' => 2, 'stok_fisik' => 10]);

        $itemId = $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 4])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->user)
            ->patchJson("/keranjang/item/{$itemId}", ['quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Minimal pemesanan untuk varian ini adalah 2 pcs');

        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 4]);
    }

    public function test_ubah_quantity_melebihi_stok_ditolak(): void
    {
        $varian = $this->varian(['stok_fisik' => 5]);

        $itemId = $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 2])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->user)
            ->patchJson("/keranjang/item/{$itemId}", ['quantity' => 6])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stok tidak mencukupi');
    }

    /** Skenario #4: item dihapus dan keranjang dihitung ulang. */
    public function test_hapus_item_menghapus_dari_keranjang(): void
    {
        $varian = $this->varian();

        $itemId = $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 1])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->user)
            ->deleteJson("/keranjang/item/{$itemId}")
            ->assertOk();

        $this->assertDatabaseCount('cart_items', 0);

        $this->actingAs($this->user)
            ->getJson('/keranjang')
            ->assertOk()
            ->assertJsonCount(0, 'data.jadi');
    }

    /** Keranjang milik User lain tidak boleh tersentuh lewat id item. */
    public function test_item_keranjang_user_lain_tidak_bisa_diubah(): void
    {
        $varian = $this->varian();

        $itemId = $this->actingAs(User::factory()->create())
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 1])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->user)
            ->patchJson("/keranjang/item/{$itemId}", ['quantity' => 2])
            ->assertNotFound()
            ->assertJsonPath('message', 'Item keranjang tidak ditemukan');

        $this->actingAs($this->user)
            ->deleteJson("/keranjang/item/{$itemId}")
            ->assertNotFound();

        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 1]);
    }

    /** Skenario #5: pesan persis dokumen. */
    public function test_keranjang_kosong_mengirim_pesan_dokumen(): void
    {
        $this->actingAs($this->user)
            ->getJson('/keranjang')
            ->assertOk()
            ->assertJsonPath('message', 'Keranjang Anda kosong')
            ->assertJsonCount(0, 'data.jadi')
            ->assertJsonCount(0, 'data.custom');
    }

    /** Skenario #2 (dummy): item custom masuk ke bagian terpisah. */
    public function test_item_custom_masuk_ke_bagian_terpisah(): void
    {
        $varian = $this->varian([], ['tipe' => 'custom', 'sla_hari' => 3]);
        $desain = $this->desain($varian);

        $this->actingAs($this->user)
            ->postJson('/keranjang/item', [
                'variant_id' => $varian->id,
                'quantity' => 2,
                'design_id' => $desain->id,
            ])
            ->assertCreated();

        $this->getJson('/keranjang')
            ->assertOk()
            ->assertJsonCount(0, 'data.jadi')
            ->assertJsonCount(1, 'data.custom')
            ->assertJsonPath('data.custom.0.design_id', $desain->id);
    }

    /** FR-CART-01 #2: produk custom tidak boleh masuk tanpa desain. */
    public function test_produk_custom_tanpa_desain_ditolak(): void
    {
        $varian = $this->varian([], ['tipe' => 'custom', 'sla_hari' => 3]);

        $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Produk custom harus ditambahkan dari preview desain');
    }

    public function test_desain_milik_user_lain_ditolak(): void
    {
        $varian = $this->varian([], ['tipe' => 'custom', 'sla_hari' => 3]);
        $desain = $this->desain($varian, User::factory()->create());

        $this->actingAs($this->user)
            ->postJson('/keranjang/item', [
                'variant_id' => $varian->id,
                'quantity' => 1,
                'design_id' => $desain->id,
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Desain tidak ditemukan');
    }

    public function test_item_menampilkan_nama_gambar_dan_varian(): void
    {
        $varian = $this->varian([
            'ukuran' => 'M',
            'warna' => 'Hitam',
            'ketebalan' => '12 oz',
        ]);

        $this->actingAs($this->user)
            ->postJson('/keranjang/item', ['variant_id' => $varian->id, 'quantity' => 1])
            ->assertCreated();

        $item = $this->getJson('/keranjang')->assertOk()->json('data.jadi.0');

        $this->assertSame('Tote Bag', $item['nama']);
        $this->assertSame('M', $item['ukuran']);
        $this->assertSame('Hitam', $item['warna']);
        $this->assertSame('12 oz', $item['ketebalan']);
        $this->assertStringContainsString('/storage/produk/tote.jpg', $item['gambar_url']);
    }

    /** SRS 4.4.1/srs.md:430: keranjang memerlukan login. */
    public function test_guest_tidak_bisa_memakai_keranjang(): void
    {
        $this->getJson('/keranjang')->assertUnauthorized();

        $this->postJson('/keranjang/item', ['variant_id' => 1, 'quantity' => 1])
            ->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $gantiVarian
     * @param  array<string, mixed>  $gantiProduk
     */
    private function varian(array $gantiVarian = [], array $gantiProduk = []): Variant
    {
        $produk = Product::create(array_merge([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tote Bag',
            'deskripsi' => 'Tas kanvas',
            'tipe' => 'jadi',
            'harga_dasar' => 20000,
            'gambar' => 'produk/tote.jpg',
            'is_aktif' => true,
        ], $gantiProduk));

        return $produk->variants()->create(array_merge([
            'sku' => 'TOTE-1',
            'harga' => 20000,
            'berat' => 250,
            'moq' => 1,
            'stok_fisik' => 10,
        ], $gantiVarian));
    }

    private function desain(Variant $varian, ?User $user = null): Design
    {
        return Design::create([
            'user_id' => ($user ?? $this->user)->id,
            'variant_id' => $varian->id,
            'data_desain' => ['elemen' => []],
            'preview_gambar' => 'desain/preview.png',
            'status' => 'draft',
        ]);
    }
}
