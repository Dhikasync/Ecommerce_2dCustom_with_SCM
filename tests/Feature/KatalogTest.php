<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-KTLG-01, UC-12: kartu katalog (foto, nama, badge Custom, harga mulai, MOQ,
 * status stok, rating, paginasi). FR-KTLG-02, UC-13: detail produk (galeri,
 * deskripsi, varian + Stok Tersedia, 404).
 */
class KatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_katalog_menampilkan_produk_aktif_dengan_data_kartu(): void
    {
        $produk = $this->produk([
            'nama' => 'Tote Bag Kanvas',
            'gambar' => 'produk/tote.jpg',
        ]);

        $this->varian($produk, ['sku' => 'TOTE-M', 'harga' => 20000, 'moq' => 5]);
        $this->varian($produk, ['sku' => 'TOTE-S', 'harga' => 15000, 'moq' => 3, 'stok_fisik' => 5]);

        $respons = $this->getJson('/katalog')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Tote Bag Kanvas')
            ->assertJsonPath('data.0.tipe', 'jadi')
            ->assertJsonPath('data.0.is_custom', false)
            ->assertJsonPath('data.0.harga_mulai', 15000)
            ->assertJsonPath('data.0.moq', 3)
            ->assertJsonPath('data.0.status_stok', 'tersedia')
            ->assertJsonPath('data.0.rating', null)
            ->assertJsonPath('data.0.jumlah_ulasan', 0)
            ->assertJsonPath('meta.total', 1);

        $this->assertStringContainsString(
            '/storage/produk/tote.jpg',
            (string) $respons->json('data.0.gambar_url'),
        );
    }

    public function test_produk_custom_ditandai_di_kartu(): void
    {
        $this->produk([
            'nama' => 'Paper Bag Custom',
            'tipe' => 'custom',
            'is_aktif' => true,
            // Constraint products_custom_aktif_check menolak custom aktif tanpa sla_hari.
            'sla_hari' => 3,
        ]);

        $this->getJson('/katalog')
            ->assertOk()
            ->assertJsonPath('data.0.tipe', 'custom')
            ->assertJsonPath('data.0.is_custom', true);
    }

    /**
     * Skenario #4: produk dinonaktifkan Admin (FR-PROD-01 #6) tidak muncul,
     * termasuk tidak dihitung di `meta.total`.
     */
    public function test_produk_nonaktif_tidak_tampil(): void
    {
        $this->produk(['nama' => 'Produk Aktif']);
        $this->produk(['nama' => 'Produk Nonaktif', 'is_aktif' => false]);

        $this->getJson('/katalog')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nama', 'Produk Aktif');
    }

    /** Skenario #2: pesan persis dokumen, bukan halaman kosong. */
    public function test_katalog_kosong_mengirim_pesan_dokumen(): void
    {
        $this->getJson('/katalog')
            ->assertOk()
            ->assertJsonPath('message', 'Belum ada produk tersedia')
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    /** Skenario #5: "Mulai Rp 15.000" dihitung dari varian termurah. */
    public function test_harga_mulai_memakai_varian_termurah(): void
    {
        $produk = $this->produk(['harga_dasar' => 50000]);

        $this->varian($produk, ['sku' => 'A', 'harga' => 25000]);
        $this->varian($produk, ['sku' => 'B', 'harga' => 15000]);

        $this->getJson('/katalog')->assertJsonPath('data.0.harga_mulai', 15000);
    }

    public function test_harga_mulai_memakai_harga_dasar_bila_belum_ada_varian(): void
    {
        $this->produk(['harga_dasar' => 40000]);

        $this->getJson('/katalog')->assertJsonPath('data.0.harga_mulai', 40000);
    }

    public function test_moq_kosong_bila_belum_ada_varian(): void
    {
        $this->produk();

        $this->getJson('/katalog')->assertJsonPath('data.0.moq', null);
    }

    public function test_moq_kartu_adalah_yang_terkecil(): void
    {
        $produk = $this->produk();

        $this->varian($produk, ['sku' => 'A', 'moq' => 12]);
        $this->varian($produk, ['sku' => 'B', 'moq' => 6]);

        $this->getJson('/katalog')->assertJsonPath('data.0.moq', 6);
    }

    public function test_status_stok_habis_bila_semua_varian_tersedia_nol(): void
    {
        $produk = $this->produk();

        $this->varian($produk, ['sku' => 'A', 'stok_fisik' => 0]);
        // Fisik 2 dikurangi reservasi 2 berarti Tersedia 0 (FR-KTLG-02 #3).
        $this->varian($produk, ['sku' => 'B', 'stok_fisik' => 2, 'stok_direservasi' => 2]);

        $this->getJson('/katalog')->assertJsonPath('data.0.status_stok', 'habis');
    }

    public function test_status_stok_tersedia_bila_ada_varian_tersisa(): void
    {
        $produk = $this->produk();

        $this->varian($produk, ['sku' => 'A', 'stok_fisik' => 0]);
        $this->varian($produk, ['sku' => 'B', 'stok_fisik' => 2, 'stok_direservasi' => 1]);

        $this->getJson('/katalog')->assertJsonPath('data.0.status_stok', 'tersedia');
    }

    /** Skenario #3: 12 per halaman, dan `per_page` kiriman klien dibatasi 50. */
    public function test_paginasi_dua_belas_per_halaman_dan_maksimal_lima_puluh(): void
    {
        foreach (range(1, 13) as $nomor) {
            $this->produk(['nama' => sprintf('Produk %02d', $nomor)]);
        }

        $halamanSatu = $this->getJson('/katalog')->assertOk();

        $halamanSatu->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/katalog?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Produk 13');

        $this->getJson('/katalog?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_per_page_tidak_valid_kembali_ke_dua_belas(): void
    {
        $this->produk();

        $this->getJson('/katalog?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 12);

        $this->getJson('/katalog?per_page=banyak')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 12);
    }

    /** Rating "jika ada" (SRS 4.4.2) dibaca dari tabel reviews (FR-ORDR-05). */
    public function test_rating_dan_jumlah_ulasan_dihitung_dari_ulasan(): void
    {
        $produk = $this->produk(['nama' => 'Biru']);

        $this->ulasan($produk, 4);
        $this->ulasan($produk, 5);

        $this->getJson('/katalog')
            ->assertJsonPath('data.0.rating', 4.5)
            ->assertJsonPath('data.0.jumlah_ulasan', 2);
    }

    public function test_urutan_default_per_nama_agar_halaman_stabil(): void
    {
        $this->produk(['nama' => 'Zebra']);
        $this->produk(['nama' => 'Anggrek']);
        $this->produk(['nama' => 'Mawar']);

        $this->getJson('/katalog')
            ->assertJsonPath('data.0.nama', 'Anggrek')
            ->assertJsonPath('data.1.nama', 'Mawar')
            ->assertJsonPath('data.2.nama', 'Zebra');
    }

    /** SRS 4.4.1: katalog tidak memerlukan login. */
    public function test_guest_dan_user_sama_sama_bisa_membuka_katalog(): void
    {
        $this->produk();

        $this->getJson('/katalog')->assertOk();

        $this->actingAs(User::factory()->create())
            ->getJson('/katalog')
            ->assertOk();
    }

    /** Skenario #1: nama, deskripsi, kategori, harga, varian. */
    public function test_detail_menampilkan_data_produk(): void
    {
        $produk = $this->produk([
            'nama' => 'Tote Bag Kanvas',
            'deskripsi' => 'Tas kanvas tebal',
            'harga_dasar' => 50000,
        ]);

        $this->varian($produk, ['sku' => 'TOTE-M', 'harga' => 20000]);
        $this->varian($produk, ['sku' => 'TOTE-S', 'harga' => 15000]);

        $this->getJson("/katalog/{$produk->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $produk->id)
            ->assertJsonPath('data.nama', 'Tote Bag Kanvas')
            ->assertJsonPath('data.deskripsi', 'Tas kanvas tebal')
            ->assertJsonPath('data.tipe', 'jadi')
            ->assertJsonPath('data.is_custom', false)
            ->assertJsonPath('data.kategori.nama', 'Tas')
            ->assertJsonPath('data.harga_mulai', 15000)
            ->assertJsonCount(2, 'data.varian')
            // Urutan varian harus tetap, bukan urutan acak dari database.
            ->assertJsonPath('data.varian.0.sku', 'TOTE-M')
            ->assertJsonPath('data.varian.1.sku', 'TOTE-S');
    }

    /** Skenario #1: foto utama jadi item pertama, lalu galeri urut kolom `urutan`. */
    public function test_galeri_memuat_foto_utama_lebih_dulu_lalu_urut(): void
    {
        $produk = $this->produk(['gambar' => 'produk/utama.jpg']);

        $produk->images()->create(['gambar' => 'produk/kedua.jpg', 'urutan' => 2]);
        $produk->images()->create(['gambar' => 'produk/ketiga.jpg', 'urutan' => 1]);

        $galeri = $this->getJson("/katalog/{$produk->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data.galeri')
            ->json('data.galeri');

        $this->assertStringEndsWith('/storage/produk/utama.jpg', $galeri[0]);
        $this->assertStringEndsWith('/storage/produk/ketiga.jpg', $galeri[1]);
        $this->assertStringEndsWith('/storage/produk/kedua.jpg', $galeri[2]);
    }

    public function test_galeri_berisi_satu_foto_bila_belum_ada_galeri(): void
    {
        $produk = $this->produk(['gambar' => 'produk/utama.jpg']);

        $this->getJson("/katalog/{$produk->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.galeri');
    }

    public function test_galeri_kosong_bila_produk_belum_punya_foto(): void
    {
        $produk = $this->produk();

        $this->getJson("/katalog/{$produk->id}")
            ->assertOk()
            ->assertJsonPath('data.galeri', []);
    }

    public function test_detail_tanpa_varian_memakai_harga_dasar(): void
    {
        $produk = $this->produk(['harga_dasar' => 40000]);

        $this->getJson("/katalog/{$produk->id}")
            ->assertOk()
            ->assertJsonPath('data.harga_mulai', 40000)
            ->assertJsonPath('data.varian', []);
    }

    /** SRS 4.4.2: varian membawa harga, berat, MOQ, dan Stok Tersedia. */
    public function test_varian_menampilkan_harga_berat_moq_dan_stok_tersedia(): void
    {
        $produk = $this->produk();

        $this->varian($produk, [
            'sku' => 'TOTE-M-HTM',
            'ukuran' => 'M',
            'warna' => 'Hitam',
            'ketebalan' => '12 oz',
            'harga' => 25000,
            'berat' => 300,
            'moq' => 4,
            'stok_fisik' => 10,
            'stok_direservasi' => 3,
        ]);

        $this->getJson("/katalog/{$produk->id}")
            ->assertOk()
            ->assertJsonPath('data.varian.0.sku', 'TOTE-M-HTM')
            ->assertJsonPath('data.varian.0.ukuran', 'M')
            ->assertJsonPath('data.varian.0.warna', 'Hitam')
            ->assertJsonPath('data.varian.0.ketebalan', '12 oz')
            ->assertJsonPath('data.varian.0.harga', 25000)
            ->assertJsonPath('data.varian.0.berat', 300)
            ->assertJsonPath('data.varian.0.moq', 4)
            ->assertJsonPath('data.varian.0.stok_tersedia', 7);
    }

    /** Skenario #3: Stok Tersedia 0 adalah dasar label "Stok Habis". */
    public function test_varian_stok_habis_menampilkan_stok_tersedia_nol(): void
    {
        $produk = $this->produk();

        $this->varian($produk, ['sku' => 'HABIS', 'stok_fisik' => 2, 'stok_direservasi' => 2]);

        $this->getJson("/katalog/{$produk->id}")
            ->assertOk()
            ->assertJsonPath('data.varian.0.stok_tersedia', 0);
    }

    /** Skenario #4: penanda tombol "Kustomisasi Desain" pengganti keranjang. */
    public function test_produk_custom_ditandai_untuk_tombol_kustomisasi(): void
    {
        $produk = $this->produk([
            'tipe' => 'custom',
            'is_aktif' => true,
            'sla_hari' => 3,
        ]);

        $this->getJson("/katalog/{$produk->id}")
            ->assertOk()
            ->assertJsonPath('data.tipe', 'custom')
            ->assertJsonPath('data.is_custom', true);
    }

    /** Skenario #5: pesan persis dokumen. */
    public function test_produk_tidak_ada_menjawab_404_dengan_pesan_dokumen(): void
    {
        $this->getJson('/katalog/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Produk tidak ditemukan');
    }

    public function test_produk_nonaktif_dianggap_tidak_ditemukan(): void
    {
        $produk = $this->produk(['is_aktif' => false]);

        $this->getJson("/katalog/{$produk->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Produk tidak ditemukan');
    }

    /** Id non-angka harus 404, bukan error query PostgreSQL. */
    public function test_id_bukan_angka_menjawab_pesan_tidak_ditemukan(): void
    {
        $this->getJson('/katalog/bukan-angka')
            ->assertNotFound()
            ->assertJsonPath('message', 'Produk tidak ditemukan');
    }

    public function test_guest_dan_user_bisa_membuka_detail_produk(): void
    {
        $produk = $this->produk();

        $this->getJson("/katalog/{$produk->id}")->assertOk();

        $this->actingAs(User::factory()->create())
            ->getJson("/katalog/{$produk->id}")
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function produk(array $ganti = []): Product
    {
        return Product::create(array_merge([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tote Bag',
            'deskripsi' => 'Tas kanvas',
            'tipe' => 'jadi',
            'harga_dasar' => 50000,
            'is_aktif' => true,
        ], $ganti));
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function varian(Product $produk, array $ganti = []): void
    {
        $produk->variants()->create(array_merge([
            'sku' => 'TOTE-1',
            'harga' => 50000,
            'berat' => 250,
            'moq' => 1,
        ], $ganti));
    }

    /** Ulasan butuh item pesanan karena `reviews.order_item_id` unik dan wajib. */
    private function ulasan(Product $produk, int $rating): void
    {
        $pesanan = Order::create([
            'user_id' => User::factory()->create()->id,
            'nomor_pesanan' => 'INV-'.uniqid(),
            'status' => 'selesai',
            'subtotal_barang' => 50000,
            'ongkir' => 0,
            'diskon_voucher' => 0,
            'total' => 50000,
            'nama_penerima' => 'Pembeli',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Contoh 1',
        ]);

        $item = OrderItem::create([
            'order_id' => $pesanan->id,
            'nama_produk' => $produk->nama,
            'sku' => 'TOTE-1',
            'quantity' => 1,
            'harga_satuan' => 50000,
        ]);

        Review::create([
            'user_id' => $pesanan->user_id,
            'product_id' => $produk->id,
            'order_item_id' => $item->id,
            'rating' => $rating,
            'komentar' => 'Bagus',
        ]);
    }
}
