<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Design;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FR-ORDR-01 #1/#2, NFR-SEC-03: User melihat status pesanannya sendiri.
 * Sembilan status mengikuti Lampiran B, dan tiga domain status dipisah.
 */
class PesananTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function varian(string $tipe = 'jadi'): Variant
    {
        $produk = Product::create([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tote Bag',
            'deskripsi' => 'Tas kanvas',
            'tipe' => $tipe,
            'harga_dasar' => 20000,
            // Produk custom yang aktif wajib punya SLA (products_custom_aktif_check).
            'sla_hari' => $tipe === 'custom' ? 3 : null,
            'gambar' => 'produk/tote.jpg',
            'is_aktif' => true,
        ]);

        return $produk->variants()->create([
            'sku' => 'TOTE-'.strtoupper((string) Str::ulid()),
            'harga' => 20000,
            'berat' => 250,
            'moq' => 1,
            'stok_fisik' => 10,
            'stok_direservasi' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function pesanan(array $ganti = [], ?User $pemilik = null, ?Variant $varian = null): Order
    {
        $pemilik ??= $this->user;
        $varian ??= $this->varian();

        $pesanan = Order::create(array_merge([
            'user_id' => $pemilik->id,
            'nomor_pesanan' => 'INV-'.strtoupper((string) Str::ulid()),
            'status' => 'menunggu_pembayaran',
            'subtotal_barang' => 40000,
            'ongkir' => 8000,
            'diskon_voucher' => 0,
            'total' => 48000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
            'kadaluwarsa_pada' => now()->addDay(),
        ], $ganti));

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'nama_produk' => 'Tote Bag',
            'nama_varian' => 'TOTE-1',
            'sku' => $varian->sku,
            'quantity' => 2,
            'harga_satuan' => 20000,
        ]);

        return $pesanan;
    }

    private function tagihan(Order $pesanan, string $status): Payment
    {
        return Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-snap-uji',
            'jumlah' => 48000,
            'status' => $status,
            'kadaluwarsa_pada' => $pesanan->kadaluwarsa_pada,
        ]);
    }

    private function desain(Order $pesanan, string $status): void
    {
        $item = $pesanan->orderItems()->firstOrFail();

        $desain = Design::create([
            'user_id' => $pesanan->user_id,
            'variant_id' => $item->variant_id,
            'data_desain' => ['elemen' => []],
            'preview_gambar' => 'desain/preview.png',
            'status' => $status,
        ]);

        $item->update(['design_id' => $desain->id]);
    }

    public function test_daftar_hanya_berisi_pesanan_milik_user_yang_login(): void
    {
        $milikSendiri = $this->pesanan();
        $milikOrangLain = $this->pesanan([], User::factory()->create());

        $this->actingAs($this->user)
            ->getJson('/pesanan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nomor_pesanan', $milikSendiri->nomor_pesanan);

        $this->assertNotSame($milikSendiri->nomor_pesanan, $milikOrangLain->nomor_pesanan);
    }

    public function test_daftar_menampilkan_status_pesanan_pembayaran_dan_desain(): void
    {
        $pesanan = $this->pesanan(['status' => 'diproses']);
        $this->tagihan($pesanan, 'lunas');

        $this->actingAs($this->user)
            ->getJson('/pesanan')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'diproses')
            ->assertJsonPath('data.0.status_pembayaran', 'lunas')
            ->assertJsonPath('data.0.status_desain', 'tidak_ada')
            ->assertJsonPath('data.0.total', 48000)
            ->assertJsonPath('data.0.jumlah_item', 2);
    }

    /** Lampiran A: Belum Dibayar selama belum ada baris payments. */
    public function test_pesanan_tanpa_tagihan_berstatus_belum_dibayar(): void
    {
        $this->pesanan();

        $this->actingAs($this->user)
            ->getJson('/pesanan')
            ->assertOk()
            ->assertJsonPath('data.0.status_pembayaran', 'belum_dibayar');
    }

    public function test_pesanan_custom_menampilkan_status_desain(): void
    {
        $pesanan = $this->pesanan(['status' => 'menunggu_validasi'], null, $this->varian('custom'));
        $this->desain($pesanan, 'menunggu_validasi');

        $this->actingAs($this->user)
            ->getJson('/pesanan')
            ->assertOk()
            ->assertJsonPath('data.0.status_desain', 'menunggu_validasi');
    }

    public function test_filter_status_menyaring_daftar(): void
    {
        $menunggu = $this->pesanan(['status' => 'menunggu_pembayaran']);
        $this->pesanan(['status' => 'dikirim', 'nomor_resi' => 'JNE-1']);

        $this->actingAs($this->user)
            ->getJson('/pesanan?status=menunggu_pembayaran')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nomor_pesanan', $menunggu->nomor_pesanan);
    }

    public function test_filter_status_tidak_dikenal_dijawab_422(): void
    {
        $this->pesanan();

        $this->actingAs($this->user)
            ->getJson('/pesanan?status=selesai_sekali')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_daftar_urut_dari_pesanan_terbaru(): void
    {
        $lama = $this->pesanan(['status' => 'diproses']);
        $baru = $this->pesanan(['status' => 'diproses']);

        $this->actingAs($this->user)
            ->getJson('/pesanan')
            ->assertOk()
            ->assertJsonPath('data.0.nomor_pesanan', $baru->nomor_pesanan)
            ->assertJsonPath('data.1.nomor_pesanan', $lama->nomor_pesanan);
    }

    public function test_daftar_kosong_dijawab_200_tanpa_galat(): void
    {
        $this->actingAs($this->user)
            ->getJson('/pesanan')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_daftar_menolak_tamu(): void
    {
        $this->getJson('/pesanan')->assertStatus(401);
    }
}
