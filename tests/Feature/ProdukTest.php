<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FR-PROD-01, UC-09, srs.md:414. Yang dikunci: gambar JPG/PNG maks 2 MB
 * (NFR-FIL-01), custom baru Nonaktif, dan hapus produk dengan pesanan aktif.
 */
class ProdukTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Unggah memakai multipart; tanpa header JSON, kegagalan jadi redirect 302.
        $this->withHeaders(['Accept' => 'application/json']);
    }

    private function adminEcommerce(): User
    {
        return User::factory()->create(['role' => 'admin_ecommerce']);
    }

    private function kategori(): Category
    {
        return Category::firstOrCreate(['nama' => 'Tas']);
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function dataProduk(array $ganti = []): array
    {
        return array_merge([
            'category_id' => $this->kategori()->id,
            'nama' => 'Tas Rotan',
            'deskripsi' => 'Tas anyaman rotan',
            'tipe' => 'jadi',
            'harga_dasar' => 150000,
        ], $ganti);
    }

    private function gambar(string $nama = 'foto.jpg', int $kb = 100): UploadedFile
    {
        // GD tidak terpasang; berkas palsu hanya melaporkan ukuran dan MIME.
        return UploadedFile::fake()->create($nama, $kb, 'image/jpeg');
    }

    /** Membuat pesanan nyata supaya penjagaan hapus teruji terhadap data. */
    private function pesananUntuk(Product $produk, string $status): OrderItem
    {
        $varian = Variant::create([
            'product_id' => $produk->id,
            'sku' => 'TAS-'.$produk->id.'-'.$status,
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
        ]);

        $order = Order::create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'nomor_pesanan' => 'INV-'.$produk->id.'-'.$status,
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
            'nama_produk' => $produk->nama,
            'nama_varian' => 'Hitam',
            'sku' => $varian->sku,
            'quantity' => 1,
            'harga_satuan' => 150000,
        ]);
    }

    public function test_admin_melihat_daftar_produk_urut_nama_dengan_harga_termurah(): void
    {
        $murah = Product::create($this->dataProduk(['nama' => 'Aksesoris']));
        Variant::create([
            'product_id' => $murah->id,
            'sku' => 'AKS-1',
            'harga' => 20000,
            'berat' => 100,
            'moq' => 1,
        ]);
        Variant::create([
            'product_id' => $murah->id,
            'sku' => 'AKS-2',
            'harga' => 15000,
            'berat' => 100,
            'moq' => 1,
        ]);
        Product::create($this->dataProduk(['nama' => 'Tas Rotan', 'category_id' => $murah->category_id]));

        $this->actingAs($this->adminEcommerce())
            ->getJson('/admin/produk')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Aksesoris')
            ->assertJsonPath('data.0.harga_mulai', 15000)
            ->assertJsonPath('data.0.jumlah_varian', 2)
            ->assertJsonPath('data.1.nama', 'Tas Rotan')
            ->assertJsonPath('data.1.harga_mulai', 150000);
    }

    public function test_admin_menambah_produk_jadi(): void
    {
        $data = $this->dataProduk();

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk', $data)
            ->assertCreated()
            ->assertJsonPath('message', 'Produk berhasil ditambahkan')
            ->assertJsonPath('data.nama', 'Tas Rotan')
            ->assertJsonPath('data.tipe', 'jadi')
            ->assertJsonPath('data.is_aktif', true);

        $this->assertDatabaseHas('products', [
            'nama' => 'Tas Rotan',
            'tipe' => 'jadi',
            'is_aktif' => true,
        ]);
    }

    public function test_produk_jadi_boleh_langsung_dinonaktifkan_saat_dibuat(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk', $this->dataProduk(['is_aktif' => false]))
            ->assertCreated()
            ->assertJsonPath('data.is_aktif', false);
    }

    public function test_produk_custom_selalu_tersimpan_nonaktif(): void
    {
        $data = $this->dataProduk(['tipe' => 'custom', 'is_aktif' => true]);

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk', $data)
            ->assertCreated()
            ->assertJsonPath('data.tipe', 'custom')
            ->assertJsonPath('data.is_aktif', false);

        $this->assertDatabaseHas('products', ['tipe' => 'custom', 'is_aktif' => false]);
    }

    public function test_gambar_pertama_menjadi_foto_utama_dan_sisanya_galeri(): void
    {
        Storage::fake('public');

        $id = $this->actingAs($this->adminEcommerce())
            ->post('/admin/produk', array_merge($this->dataProduk(), [
                'gambar' => [
                    $this->gambar('utama.jpg'),
                    $this->gambar('galeri-1.jpg'),
                    $this->gambar('galeri-2.jpg'),
                ],
            ]))
            ->assertCreated()
            ->json('data.id');

        $produk = Product::with('images')->findOrFail($id);

        $this->assertNotNull($produk->gambar);
        Storage::disk('public')->assertExists($produk->gambar);

        $this->assertSame([1, 2], $produk->images->pluck('urutan')->all());
        $this->assertCount(2, $produk->images);
        foreach ($produk->images as $gambar) {
            Storage::disk('public')->assertExists($gambar->gambar);
        }
    }

    public function test_unggah_pdf_ditolak(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->adminEcommerce())
            ->post('/admin/produk', array_merge($this->dataProduk(), [
                'gambar' => [UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf')],
            ]));

        $response->assertStatus(422);

        // Pesan dibaca dari larik `errors`; kunci bertitik (`gambar.0`) akan salah pecah.
        $this->assertSame('Format gambar harus JPG atau PNG', $response->json('errors')['gambar.0'][0]);

        $this->assertSame(0, Product::count());
    }

    public function test_unggah_lebih_dari_dua_megabita_ditolak(): void
    {
        Storage::fake('public');

        $berkas = $this->gambar('besar.jpg', 2049);

        $response = $this->actingAs($this->adminEcommerce())
            ->post('/admin/produk', array_merge($this->dataProduk(), [
                'gambar' => [$berkas],
            ]));

        $response->assertStatus(422);

        $this->assertSame('Ukuran gambar maksimal 2 MB', $response->json('errors')['gambar.0'][0]);

        $this->assertSame(0, Product::count());
    }

    public function test_field_wajib_produk_ditolak_kalau_kosong(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'nama', 'deskripsi', 'tipe', 'harga_dasar']);

        $this->assertSame(0, Product::count());
    }

    public function test_kategori_yang_tidak_ada_ditolak(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk', $this->dataProduk(['category_id' => 999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_harga_dasar_negatif_ditolak(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/produk', $this->dataProduk(['harga_dasar' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('harga_dasar');
    }

    public function test_admin_mengubah_data_produk(): void
    {
        $produk = Product::create($this->dataProduk());

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produk->id, [
                'category_id' => $produk->category_id,
                'nama' => 'Tas Rotan Besar',
                'deskripsi' => 'Ukuran besar',
                'tipe' => 'jadi',
                'harga_dasar' => 175000,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Produk berhasil diubah')
            ->assertJsonPath('data.nama', 'Tas Rotan Besar')
            ->assertJsonPath('data.harga_dasar', 175000);
    }

    public function test_ubah_tanpa_mengirim_status_tidak_menghidupkan_produk_nonaktif(): void
    {
        $produk = Product::create($this->dataProduk(['is_aktif' => false]));

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produk->id, [
                'category_id' => $produk->category_id,
                'nama' => 'Tas Rotan',
                'deskripsi' => 'Deskripsi baru',
                'tipe' => 'jadi',
                'harga_dasar' => 150000,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_aktif', false);

        $this->assertDatabaseHas('products', ['id' => $produk->id, 'is_aktif' => false]);
    }

    public function test_produk_custom_yang_sudah_aktif_tetap_aktif_saat_diubah(): void
    {
        // `products_custom_aktif_check` menolak custom aktif tanpa sla_hari.
        $produk = Product::create($this->dataProduk([
            'tipe' => 'custom',
            'is_aktif' => true,
            'sla_hari' => 7,
        ]));

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produk->id, [
                'category_id' => $produk->category_id,
                'nama' => 'Tas Rotan Custom Baru',
                'deskripsi' => 'Deskripsi baru',
                'tipe' => 'custom',
                'harga_dasar' => 150000,
            ])
            ->assertOk()
            ->assertJsonPath('data.nama', 'Tas Rotan Custom Baru')
            ->assertJsonPath('data.is_aktif', true);

        $this->assertDatabaseHas('products', ['id' => $produk->id, 'is_aktif' => true]);
    }

    public function test_produk_jadi_yang_diubah_menjadi_custom_tetap_nonaktif(): void
    {
        $produk = Product::create($this->dataProduk());

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produk->id, [
                'category_id' => $produk->category_id,
                'nama' => $produk->nama,
                'deskripsi' => $produk->deskripsi,
                'tipe' => 'custom',
                'harga_dasar' => $produk->harga_dasar,
                'is_aktif' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.tipe', 'custom')
            ->assertJsonPath('data.is_aktif', false);

        $this->assertDatabaseHas('products', ['id' => $produk->id, 'is_aktif' => false]);
    }

    public function test_produk_custom_aktif_boleh_dinonaktifkan(): void
    {
        $produk = Product::create($this->dataProduk([
            'tipe' => 'custom',
            'is_aktif' => true,
            'sla_hari' => 7,
        ]));

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produk->id, [
                'category_id' => $produk->category_id,
                'nama' => $produk->nama,
                'deskripsi' => $produk->deskripsi,
                'tipe' => 'custom',
                'harga_dasar' => $produk->harga_dasar,
                'is_aktif' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_aktif', false);

        $this->assertDatabaseHas('products', ['id' => $produk->id, 'is_aktif' => false]);
    }

    public function test_admin_menonaktifkan_produk(): void
    {
        $produk = Product::create($this->dataProduk());

        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/'.$produk->id, [
                'category_id' => $produk->category_id,
                'nama' => $produk->nama,
                'deskripsi' => $produk->deskripsi,
                'tipe' => 'jadi',
                'harga_dasar' => $produk->harga_dasar,
                'is_aktif' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_aktif', false);
    }

    public function test_mengganti_foto_utama_menghapus_berkas_lama(): void
    {
        Storage::fake('public');

        $produk = Product::create($this->dataProduk());

        $this->actingAs($this->adminEcommerce())
            ->post('/admin/produk/'.$produk->id, [
                '_method' => 'PUT',
                'category_id' => $produk->category_id,
                'nama' => $produk->nama,
                'deskripsi' => $produk->deskripsi,
                'tipe' => 'jadi',
                'harga_dasar' => $produk->harga_dasar,
                'gambar' => [$this->gambar('lama.jpg')],
            ])
            ->assertOk();

        $lama = $produk->refresh()->gambar;
        Storage::disk('public')->assertExists($lama);

        $this->actingAs($this->adminEcommerce())
            ->post('/admin/produk/'.$produk->id, [
                '_method' => 'PUT',
                'category_id' => $produk->category_id,
                'nama' => $produk->nama,
                'deskripsi' => $produk->deskripsi,
                'tipe' => 'jadi',
                'harga_dasar' => $produk->harga_dasar,
                'gambar' => [$this->gambar('baru.jpg')],
            ])
            ->assertOk();

        $baru = $produk->refresh()->gambar;

        $this->assertNotSame($lama, $baru);
        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($baru);
    }

    public function test_admin_menghapus_gambar_galeri(): void
    {
        Storage::fake('public');

        $produk = Product::create($this->dataProduk());
        $gambar = $produk->images()->create([
            'gambar' => 'produk/galeri.jpg',
            'urutan' => 1,
        ]);
        Storage::disk('public')->put('produk/galeri.jpg', 'isi');

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id.'/gambar/'.$gambar->id)
            ->assertOk()
            ->assertJsonPath('message', 'Gambar berhasil dihapus');

        $this->assertDatabaseMissing('product_images', ['id' => $gambar->id]);
        Storage::disk('public')->assertMissing('produk/galeri.jpg');
    }

    public function test_gambar_galeri_yang_tidak_ada_dijawab_404(): void
    {
        $produk = Product::create($this->dataProduk());

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id.'/gambar/999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Gambar tidak ditemukan');
    }

    public function test_admin_menghapus_produk_tanpa_pesanan(): void
    {
        Storage::fake('public');

        $produk = Product::create($this->dataProduk());
        $gambar = 'produk/foto.jpg';
        Storage::disk('public')->put($gambar, 'isi');
        $produk->update(['gambar' => $gambar]);
        $produk->variants()->create([
            'sku' => 'TAS-1',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
        ]);

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id)
            ->assertOk()
            ->assertJsonPath('message', 'Produk berhasil dihapus');

        $this->assertDatabaseMissing('products', ['id' => $produk->id]);
        $this->assertDatabaseMissing('variants', ['product_id' => $produk->id]);
        Storage::disk('public')->assertMissing($gambar);
    }

    public function test_produk_dengan_pesanan_aktif_tidak_bisa_dihapus(): void
    {
        $produk = Product::create($this->dataProduk());
        $this->pesananUntuk($produk, 'diproses');

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Produk masih memiliki pesanan aktif dan tidak dapat dihapus');

        $this->assertDatabaseHas('products', ['id' => $produk->id]);
    }

    public function test_produk_dengan_varian_berstok_tidak_bisa_dihapus(): void
    {
        $produk = Product::create($this->dataProduk());
        $produk->variants()->create([
            'sku' => 'TAS-1',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
            'stok_fisik' => 5,
        ]);

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Produk masih memiliki stok pada salah satu varian, kosongkan stok terlebih dahulu');

        $this->assertDatabaseHas('products', ['id' => $produk->id]);
        $this->assertDatabaseHas('variants', ['product_id' => $produk->id, 'stok_fisik' => 5]);
    }

    public function test_produk_dengan_stok_reservasi_tidak_bisa_dihapus(): void
    {
        $produk = Product::create($this->dataProduk());
        $produk->variants()->create([
            'sku' => 'TAS-1',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
            'stok_direservasi' => 2,
        ]);

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Produk masih memiliki stok pada salah satu varian, kosongkan stok terlebih dahulu');

        $this->assertDatabaseHas('products', ['id' => $produk->id]);
        $this->assertDatabaseHas('variants', ['product_id' => $produk->id, 'stok_direservasi' => 2]);
    }

    public function test_produk_dengan_pesanan_selesai_boleh_dihapus_dan_riwayat_tetap_utuh(): void
    {
        $produk = Product::create($this->dataProduk());
        $item = $this->pesananUntuk($produk, 'selesai');

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/'.$produk->id)
            ->assertOk();

        $item->refresh();

        $this->assertDatabaseMissing('products', ['id' => $produk->id]);
        $this->assertNull($item->variant_id);
        $this->assertSame('Tas Rotan', $item->nama_produk);
        $this->assertSame(150000, $item->harga_satuan);
    }

    public function test_produk_tidak_ditemukan_dijawab_404(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->putJson('/admin/produk/999', $this->dataProduk())
            ->assertStatus(404)
            ->assertJsonPath('message', 'Produk tidak ditemukan');

        $this->actingAs($this->adminEcommerce())
            ->deleteJson('/admin/produk/999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Produk tidak ditemukan');
    }

    public function test_relasi_produk_dan_varian_menunjuk_kelas_yang_benar(): void
    {
        // Penjaga import model: tanpa `use`, relasi mencari kelas di App\Models\Produk.
        $produk = Product::create($this->dataProduk());
        $varian = $produk->variants()->create([
            'sku' => 'TAS-RELASI',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
        ]);

        $this->assertSame(0, $produk->templates()->count());
        $this->assertSame(0, $produk->reviews()->count());
        $this->assertSame(0, $varian->stockMovements()->count());
        $this->assertSame(0, $varian->cartItems()->count());
        $this->assertSame(0, $varian->orderItems()->count());
        $this->assertSame(0, $varian->designs()->count());
        $this->assertSame(0, $varian->purchaseOrderItems()->count());
        $this->assertSame(0, $varian->distributors()->count());
    }

    public function test_user_biasa_tidak_boleh_mengelola_produk(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/admin/produk')->assertStatus(403);
        $this->actingAs($user)->postJson('/admin/produk', $this->dataProduk())->assertStatus(403);
    }

    public function test_admin_scm_tidak_boleh_mengelola_produk(): void
    {
        $adminScm = User::factory()->create(['role' => 'admin_scm']);

        $this->actingAs($adminScm)->getJson('/admin/produk')->assertStatus(403);
    }

    public function test_guest_harus_login_dulu(): void
    {
        $this->getJson('/admin/produk')->assertStatus(401);
    }
}
