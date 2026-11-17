<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Courier;
use App\Models\Design;
use App\Models\Order;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use App\Models\Profil\Address;
use App\Models\ShopSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FR-CKOT-01, UC-26/UC-27, user-story.md:448-464: menyusun pesanan dari keranjang
 * (alamat, kurir, ongkir) lalu membentuk pesanan dengan reservasi stok.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const URL_DASAR = 'https://rajaongkir.komerce.id/api/v1';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rajaongkir.key' => 'kunci-uji',
            'services.rajaongkir.base_url' => self::URL_DASAR,
        ]);

        $this->user = User::factory()->create();
    }

    private function alamatAsal(): ShopSetting
    {
        return ShopSetting::forceCreate([
            'id' => 1,
            'alamat_jalan' => 'Jl. Cibeunying No. 12',
            'kode_lokasi' => '4816',
            'label_lokasi' => 'CIBEUNYING, BANDUNG, JAWA BARAT, 40191',
        ]);
    }

    /** @param  array<string, mixed>  $ganti */
    private function alamat(array $ganti = []): Address
    {
        return Address::create(array_merge([
            'user_id' => $this->user->id,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_lengkap' => 'Jl. Sumur Bandung No. 1',
            'kode_lokasi' => '4817',
            'label_lokasi' => 'SUMUR BANDUNG, BANDUNG, JAWA BARAT, 40111',
            'is_utama' => true,
        ], $ganti));
    }

    /**
     * Satu baris per pasangan kode+layanan (unique `couriers_kode_layanan_unique`),
     * jadi helper ini mengembalikan baris yang sama saat dipanggil berulang.
     *
     * @param  array<string, mixed>  $ganti
     */
    private function kurir(array $ganti = []): Courier
    {
        $ganti = array_merge([
            'kode' => 'jne',
            'nama' => 'JNE',
            'layanan' => 'REG',
            'is_aktif' => true,
        ], $ganti);

        return Courier::firstOrCreate(
            ['kode' => $ganti['kode'], 'layanan' => $ganti['layanan']],
            ['nama' => $ganti['nama'], 'is_aktif' => $ganti['is_aktif']],
        );
    }

    /** Satu baris tarif dengan bentuk respons RajaOngkir. */
    private function tarif(string $layanan = 'REG', int $biaya = 8000, string $kode = 'jne'): array
    {
        return [
            'name' => strtoupper($kode),
            'code' => $kode,
            'service' => $layanan,
            'description' => 'Layanan '.$layanan,
            'cost' => $biaya,
            'etd' => '2-3 day',
        ];
    }

    /** @param  list<array<string, mixed>>  $baris */
    private function palsukanTarif(array $baris): void
    {
        Http::fake(['*' => Http::response([
            'meta' => ['message' => 'Success Calculate Domestic Shipping cost', 'code' => 200, 'status' => 'success'],
            'data' => $baris,
        ])]);
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

    private function desain(Variant $varian): Design
    {
        return Design::create([
            'user_id' => $this->user->id,
            'variant_id' => $varian->id,
            'data_desain' => ['elemen' => []],
            'preview_gambar' => 'desain/preview.png',
            'status' => 'draft',
        ]);
    }

    private function tambah(Variant $varian, int $jumlah, ?int $designId = null): CartItem
    {
        $this->actingAs($this->user)
            ->postJson('/keranjang/item', array_filter([
                'variant_id' => $varian->id,
                'quantity' => $jumlah,
                'design_id' => $designId,
            ]))
            ->assertCreated();

        return CartItem::where('variant_id', $varian->id)
            ->when($designId, fn ($q) => $q->where('design_id', $designId))
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * @param  list<int>  $itemIds
     * @param  array<string, mixed>  $ganti
     */
    private function ringkasan(array $itemIds, array $ganti = [])
    {
        return $this->actingAs($this->user)
            ->getJson('/checkout?'.http_build_query(array_merge(['item_ids' => $itemIds], $ganti)));
    }

    // ---------- Pemilihan item (FR-CART #4/#5 yang ditunda ke sini) ----------

    public function test_ringkasan_menolak_pilihan_kosong(): void
    {
        $this->ringkasan([])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pilih minimal satu item untuk di-checkout');
    }

    public function test_ringkasan_menolak_item_milik_user_lain(): void
    {
        $milikOrangLain = User::factory()->create();
        $item = CartItem::create([
            'cart_id' => Cart::create(['user_id' => $milikOrangLain->id])->id,
            'variant_id' => $this->varian()->id,
            'quantity' => 1,
            'harga_satuan' => 20000,
        ]);

        $this->ringkasan([$item->id])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Item keranjang tidak ditemukan');
    }

    public function test_ringkasan_menolak_pilihan_dari_dua_bagian(): void
    {
        $jadi = $this->tambah($this->varian(), 1);

        $varianCustom = $this->varian(['sku' => 'TMBL-1'], [
            'nama' => 'Tumbler Custom',
            'tipe' => 'custom',
            'sla_hari' => 3,
        ]);
        $custom = $this->tambah($varianCustom, 1, $this->desain($varianCustom)->id);

        $this->ringkasan([$jadi->id, $custom->id])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Produk jadi dan produk custom di-checkout terpisah, selesaikan salah satunya terlebih dahulu',
            );
    }

    // ---------- Skenario #2: alamat pengiriman ----------

    public function test_alamat_utama_yang_punya_lokasi_dipilih_otomatis(): void
    {
        $this->alamat(['is_utama' => false, 'kode_lokasi' => '4900']);
        $utama = $this->alamat();

        $item = $this->tambah($this->varian(), 1);

        $this->ringkasan([$item->id])
            ->assertOk()
            ->assertJsonPath('data.bagian', 'jadi')
            ->assertJsonPath('data.alamat.dipilih', $utama->id)
            // Alamat utama tampil lebih dulu, sisanya urut id.
            ->assertJsonPath('data.alamat.daftar.0.id', $utama->id);
    }

    public function test_alamat_tanpa_lokasi_ditandai_lengkapi_lokasi_dan_tidak_dipilih(): void
    {
        $tanpaLokasi = $this->alamat(['kode_lokasi' => null, 'label_lokasi' => null, 'is_utama' => true]);

        $item = $this->tambah($this->varian(), 1);

        $this->ringkasan([$item->id])
            ->assertOk()
            ->assertJsonPath('data.alamat.dipilih', null)
            ->assertJsonPath('data.alamat.daftar.0.id', $tanpaLokasi->id)
            ->assertJsonPath('data.alamat.daftar.0.bisa_dipakai', false)
            ->assertJsonPath('data.alamat.daftar.0.catatan', 'Lengkapi lokasi');
    }

    public function test_ringkasan_hanya_menampilkan_kurir_aktif(): void
    {
        $this->kurir(['layanan' => 'REG']);
        $this->kurir(['kode' => 'pos', 'nama' => 'POS Indonesia', 'layanan' => 'Pos Reguler', 'is_aktif' => false]);

        $item = $this->tambah($this->varian(), 1);

        $this->ringkasan([$item->id])
            ->assertOk()
            ->assertJsonCount(1, 'data.kurir')
            ->assertJsonPath('data.kurir.0.layanan', 'REG');
    }

    // ---------- Skenario #3 dan #8: ongkir ----------

    public function test_ongkir_dihitung_dari_berat_total_dan_total_menjumlahkan_subtotal(): void
    {
        $this->alamatAsal();
        $this->kurir();
        $this->palsukanTarif([$this->tarif('REG', 8000)]);

        $tebal = $this->varian(['sku' => 'TOTE-2', 'berat' => 500, 'harga' => 30000]);
        $tipis = $this->varian();
        $itemA = $this->tambah($tipis, 2);
        $itemB = $this->tambah($tebal, 3);

        $this->actingAs($this->user)
            ->postJson('/checkout/ongkir', [
                'item_ids' => [$itemA->id, $itemB->id],
                'address_id' => $this->alamat()->id,
                'courier_id' => $this->kurir(['layanan' => 'REG'])->id,
            ])
            ->assertOk()
            // 2 x 250 g + 3 x 500 g = 2000 g
            ->assertJsonPath('data.berat_total', 2000)
            ->assertJsonPath('data.subtotal_barang', 130000)
            ->assertJsonPath('data.ongkir', 8000)
            ->assertJsonPath('data.total', 138000);

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === self::URL_DASAR.'/calculate/domestic-cost'
                && $request['origin'] === '4816'
                && $request['destination'] === '4817'
                && (int) $request['weight'] === 2000
                && $request['courier'] === 'jne';
        });
    }

    public function test_ongkir_menolak_tanpa_alamat_atau_kurir(): void
    {
        $this->alamatAsal();
        Http::fake();

        $item = $this->tambah($this->varian(), 1);

        $this->actingAs($this->user)
            ->postJson('/checkout/ongkir', ['item_ids' => [$item->id], 'address_id' => $this->alamat()->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Alamat dan kurir wajib dipilih');

        Http::assertNothingSent();
    }

    public function test_ongkir_menolak_alamat_yang_belum_punya_lokasi(): void
    {
        $this->alamatAsal();
        $this->kurir();
        $tanpaLokasi = $this->alamat(['kode_lokasi' => null, 'label_lokasi' => null]);
        Http::fake();

        $item = $this->tambah($this->varian(), 1);

        $this->actingAs($this->user)
            ->postJson('/checkout/ongkir', [
                'item_ids' => [$item->id],
                'address_id' => $tanpaLokasi->id,
                'courier_id' => $this->kurir()->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Alamat dan kurir wajib dipilih');

        Http::assertNothingSent();
    }

    public function test_ongkir_gagal_dihubungi_memberi_pesan_coba_lagi(): void
    {
        $this->alamatAsal();
        $this->kurir();
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $item = $this->tambah($this->varian(), 1);

        $this->actingAs($this->user)
            ->postJson('/checkout/ongkir', [
                'item_ids' => [$item->id],
                'address_id' => $this->alamat()->id,
                'courier_id' => $this->kurir()->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ongkir belum dapat dihitung, silakan coba lagi');
    }

    public function test_ongkir_kosong_untuk_layanan_yang_dipilih_memberi_pesan_coba_lagi(): void
    {
        $this->alamatAsal();
        $this->kurir(['layanan' => 'YES']);
        // API membalas layanan lain: REG, bukan YES.
        $this->palsukanTarif([$this->tarif('REG', 8000)]);

        $item = $this->tambah($this->varian(), 1);

        $this->actingAs($this->user)
            ->postJson('/checkout/ongkir', [
                'item_ids' => [$item->id],
                'address_id' => $this->alamat()->id,
                'courier_id' => $this->kurir(['layanan' => 'YES'])->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ongkir belum dapat dihitung, silakan coba lagi');
    }

    public function test_ongkir_tidak_tersedia_bila_alamat_asal_toko_belum_diisi(): void
    {
        $this->kurir();
        Http::fake();

        $item = $this->tambah($this->varian(), 1);

        $this->actingAs($this->user)
            ->postJson('/checkout/ongkir', [
                'item_ids' => [$item->id],
                'address_id' => $this->alamat()->id,
                'courier_id' => $this->kurir()->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ongkir belum dapat dihitung, silakan coba lagi');

        Http::assertNothingSent();
    }

    // ---------- Skenario #1/#4/#7: membuat pesanan produk jadi ----------

    public function test_checkout_menolak_tanpa_alamat_dan_kurir(): void
    {
        $this->alamatAsal();
        Http::fake();

        $item = $this->tambah($this->varian(), 1);

        $this->actingAs($this->user)
            ->postJson('/checkout', ['item_ids' => [$item->id]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Alamat dan kurir wajib dipilih');

        $this->assertSame(0, Order::count());
        Http::assertNothingSent();
    }

    public function test_checkout_jadi_membuat_menunggu_pembayaran_dan_mereservasi_stok(): void
    {
        $this->alamatAsal();
        $kurir = $this->kurir();
        $this->palsukanTarif([$this->tarif('REG', 8000)]);

        $varian = $this->varian(['stok_fisik' => 10]);
        $item = $this->tambah($varian, 2);

        $respons = $this->actingAs($this->user)->postJson('/checkout', [
            'item_ids' => [$item->id],
            'address_id' => $this->alamat()->id,
            'courier_id' => $kurir->id,
        ])->assertCreated();

        $respons
            ->assertJsonPath('data.status', 'menunggu_pembayaran')
            ->assertJsonPath('data.status_pembayaran', 'belum_dibayar')
            ->assertJsonPath('data.status_desain', 'tidak_ada')
            ->assertJsonPath('data.subtotal_barang', 40000)
            ->assertJsonPath('data.ongkir', 8000)
            ->assertJsonPath('data.total', 48000)
            ->assertJsonPath('data.items.0.sku', 'TOTE-1')
            ->assertJsonPath('data.items.0.quantity', 2);

        $pesanan = Order::firstOrFail();

        $this->assertSame(1, Order::count());
        $this->assertNotEmpty($pesanan->nomor_pesanan);
        $this->assertSame('menunggu_pembayaran', $pesanan->status);
        $this->assertSame($kurir->id, $pesanan->courier_id);
        $this->assertSame(24, (int) round(now()->diffInHours($pesanan->kadaluwarsa_pada)));

        // Reservasi: Direservasi naik, Fisik belum berubah (baru Lunas yang memotong).
        $varian->refresh();
        $this->assertSame(2, $varian->stok_direservasi);
        $this->assertSame(10, $varian->stok_fisik);
        $this->assertSame(8, $varian->stok_tersedia);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $pesanan->id,
            'nama_produk' => 'Tote Bag',
            'sku' => 'TOTE-1',
            'quantity' => 2,
            'harga_satuan' => 20000,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $pesanan->id,
            'status_lama' => null,
            'status_baru' => 'menunggu_pembayaran',
        ]);
    }

    public function test_checkout_menyalin_alamat_dan_hanya_menghapus_item_yang_dipilih(): void
    {
        $this->alamatAsal();
        $this->kurir();
        $this->palsukanTarif([$this->tarif('REG', 8000)]);

        $alamat = $this->alamat();
        $dipilih = $this->tambah($this->varian(), 1);
        $tetap = $this->tambah($this->varian(['sku' => 'TOTE-2']), 1);

        $this->actingAs($this->user)->postJson('/checkout', [
            'item_ids' => [$dipilih->id],
            'address_id' => $alamat->id,
            'courier_id' => $this->kurir()->id,
        ])->assertCreated();

        $pesanan = Order::firstOrFail();
        $this->assertSame('Andhi', $pesanan->nama_penerima);
        $this->assertSame('+628123456789', $pesanan->no_hp);
        $this->assertSame('Jl. Sumur Bandung No. 1', $pesanan->alamat_pengiriman);
        $this->assertSame('SUMUR BANDUNG, BANDUNG, JAWA BARAT, 40111', $pesanan->label_lokasi);

        $this->assertSame([$tetap->id], CartItem::pluck('id')->all());
    }

    public function test_checkout_menolak_item_bermasalah_dan_tidak_membuat_pesanan(): void
    {
        $this->alamatAsal();
        $this->kurir();
        $this->palsukanTarif([$this->tarif('REG', 8000)]);

        $varian = $this->varian();
        $item = $this->tambah($varian, 2);
        $varian->product->update(['is_aktif' => false]);

        $this->actingAs($this->user)->postJson('/checkout', [
            'item_ids' => [$item->id],
            'address_id' => $this->alamat()->id,
            'courier_id' => $this->kurir()->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Keranjang berubah, periksa kembali item Anda')
            ->assertJsonPath('data.items.0.label', 'Produk tidak tersedia');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, $varian->refresh()->stok_direservasi);
        $this->assertSame(1, CartItem::count());
    }

    public function test_checkout_menolak_stok_yang_habis_setelah_masuk_keranjang(): void
    {
        $this->alamatAsal();
        $this->kurir();
        $this->palsukanTarif([$this->tarif('REG', 8000)]);

        $varian = $this->varian(['stok_fisik' => 5]);
        $item = $this->tambah($varian, 2);
        $varian->update(['stok_fisik' => 1]);

        $this->actingAs($this->user)->postJson('/checkout', [
            'item_ids' => [$item->id],
            'address_id' => $this->alamat()->id,
            'courier_id' => $this->kurir()->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.items.0.label', 'Stok tidak mencukupi');

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_menolak_kurir_yang_sudah_dinonaktifkan(): void
    {
        $this->alamatAsal();
        $kurir = $this->kurir(['is_aktif' => false]);
        Http::fake();

        $item = $this->tambah($this->varian(), 1);

        $this->actingAs($this->user)->postJson('/checkout', [
            'item_ids' => [$item->id],
            'address_id' => $this->alamat()->id,
            'courier_id' => $kurir->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Alamat dan kurir wajib dipilih');

        $this->assertSame(0, Order::count());
        Http::assertNothingSent();
    }

    public function test_ongkir_kiriman_klien_diabaikan(): void
    {
        $this->alamatAsal();
        $this->kurir();
        $this->palsukanTarif([$this->tarif('REG', 8000)]);

        $item = $this->tambah($this->varian(), 1);

        $this->actingAs($this->user)->postJson('/checkout', [
            'item_ids' => [$item->id],
            'address_id' => $this->alamat()->id,
            'courier_id' => $this->kurir()->id,
            'ongkir' => 1,
            'total' => 1,
        ])->assertCreated();

        $pesanan = Order::firstOrFail();
        $this->assertSame(8000, $pesanan->ongkir);
        $this->assertSame(28000, $pesanan->total);
    }

    // ---------- Skenario #5/#6: pesanan custom ----------

    public function test_checkout_custom_menunggu_validasi_dan_menyimpan_sla(): void
    {
        $this->alamatAsal();
        $this->kurir();
        $this->palsukanTarif([$this->tarif('REG', 8000)]);

        $varian = $this->varian(['sku' => 'TMBL-2'], [
            'nama' => 'Tumbler Custom',
            'tipe' => 'custom',
            'sla_hari' => 3,
        ]);
        $desain = $this->desain($varian);
        $item = $this->tambah($varian, 2, $desain->id);

        $this->actingAs($this->user)->postJson('/checkout', [
            'item_ids' => [$item->id],
            'address_id' => $this->alamat()->id,
            'courier_id' => $this->kurir()->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'menunggu_validasi')
            ->assertJsonPath('data.status_pembayaran', 'belum_dibayar')
            ->assertJsonPath('data.status_desain', 'menunggu_validasi')
            ->assertJsonPath('data.sla_hari', 3)
            ->assertJsonPath('data.estimasi_sla', 'Estimasi 3 hari kerja setelah status desain Disetujui DAN status pembayaran Lunas')
            ->assertJsonPath('data.kadaluwarsa_pada', null);

        $pesanan = Order::firstOrFail();
        $this->assertNull($pesanan->kadaluwarsa_pada);
        $this->assertSame('menunggu_validasi', $desain->refresh()->status);
        $this->assertSame(2, $varian->refresh()->stok_direservasi);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $pesanan->id,
            'design_id' => $desain->id,
            'sla_hari' => 3,
        ]);
    }

    public function test_ringkasan_custom_menampilkan_estimasi_sla_terpanjang(): void
    {
        $this->alamatAsal();
        $this->kurir();

        $cepat = $this->varian(['sku' => 'C-1'], ['nama' => 'Tumbler Custom', 'tipe' => 'custom', 'sla_hari' => 2]);
        $lama = $this->varian(['sku' => 'C-2'], ['nama' => 'Mug Custom', 'tipe' => 'custom', 'sla_hari' => 5]);

        $a = $this->tambah($cepat, 1, $this->desain($cepat)->id);
        $b = $this->tambah($lama, 1, $this->desain($lama)->id);

        $this->ringkasan([$a->id, $b->id])
            ->assertOk()
            ->assertJsonPath('data.bagian', 'custom')
            ->assertJsonPath('data.sla_hari', 5)
            ->assertJsonPath('data.estimasi_sla', 'Estimasi 5 hari kerja setelah status desain Disetujui DAN status pembayaran Lunas');
    }
}
