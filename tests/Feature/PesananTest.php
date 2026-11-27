<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Courier;
use App\Models\Design;
use App\Models\Order;
use App\Models\OrderStatusHistory;
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

    private function kurir(): Courier
    {
        return Courier::create([
            'kode' => 'JNE',
            'nama' => 'JNE',
            'layanan' => 'REG',
            'is_aktif' => true,
        ]);
    }

    /** FR-ORDR-01 #2: status pesanan, pembayaran, desain, item, alamat, kurir, riwayat. */
    public function test_detail_menampilkan_status_item_alamat_kurir_dan_riwayat(): void
    {
        $kurir = $this->kurir();
        $pesanan = $this->pesanan([
            'status' => 'diproses',
            'courier_id' => $kurir->id,
            'label_lokasi' => 'Rumah',
        ]);
        $this->tagihan($pesanan, 'lunas');

        OrderStatusHistory::create([
            'order_id' => $pesanan->id,
            'status_lama' => null,
            'status_baru' => 'menunggu_pembayaran',
            'diubah_oleh' => $this->user->id,
        ]);
        OrderStatusHistory::create([
            'order_id' => $pesanan->id,
            'status_lama' => 'menunggu_pembayaran',
            'status_baru' => 'diproses',
            'diubah_oleh' => null,
            'catatan' => 'Pembayaran diterima',
        ]);

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.nomor_pesanan', $pesanan->nomor_pesanan)
            ->assertJsonPath('data.status', 'diproses')
            ->assertJsonPath('data.status_pembayaran', 'lunas')
            ->assertJsonPath('data.status_desain', 'tidak_ada')
            ->assertJsonPath('data.subtotal_barang', 40000)
            ->assertJsonPath('data.ongkir', 8000)
            ->assertJsonPath('data.total', 48000)
            ->assertJsonPath('data.item.0.nama_produk', 'Tote Bag')
            ->assertJsonPath('data.item.0.quantity', 2)
            ->assertJsonPath('data.item.0.harga_satuan', 20000)
            ->assertJsonPath('data.item.0.subtotal', 40000)
            ->assertJsonPath('data.item.0.is_custom', false)
            ->assertJsonPath('data.item.0.desain', null)
            ->assertJsonPath('data.alamat.nama_penerima', 'Andhi')
            ->assertJsonPath('data.alamat.alamat_pengiriman', 'Jl. Sumur Bandung No. 1')
            ->assertJsonPath('data.alamat.label_lokasi', 'Rumah')
            ->assertJsonPath('data.kurir.nama', 'JNE')
            ->assertJsonPath('data.kurir.layanan', 'REG')
            ->assertJsonCount(2, 'data.riwayat')
            ->assertJsonPath('data.riwayat.0.status_lama', null)
            ->assertJsonPath('data.riwayat.0.status_baru', 'menunggu_pembayaran')
            ->assertJsonPath('data.riwayat.0.oleh', $this->user->name)
            ->assertJsonPath('data.riwayat.1.status_baru', 'diproses')
            ->assertJsonPath('data.riwayat.1.oleh', 'Sistem')
            ->assertJsonPath('data.riwayat.1.catatan', 'Pembayaran diterima');
    }

    /** FR-PROD-01 #5: harga dan nama item adalah salinan saat pesanan dibentuk. */
    public function test_detail_memakai_harga_saat_pesanan_dibentuk(): void
    {
        $pesanan = $this->pesanan();
        $pesanan->orderItems()->firstOrFail()->variant->update(['harga' => 99000]);

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.item.0.harga_satuan', 20000)
            ->assertJsonPath('data.item.0.subtotal', 40000);
    }

    public function test_detail_pesanan_custom_membawa_status_desain_per_item(): void
    {
        $pesanan = $this->pesanan(['status' => 'menunggu_validasi'], null, $this->varian('custom'));
        $this->desain($pesanan, 'menunggu_validasi');

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.status_desain', 'menunggu_validasi')
            ->assertJsonPath('data.item.0.is_custom', true)
            ->assertJsonPath('data.item.0.desain.status', 'menunggu_validasi')
            ->assertJsonPath('data.item.0.desain.preview', url('/storage/desain/preview.png'));
    }

    public function test_detail_pesanan_dibatalkan_membawa_alasan(): void
    {
        $pesanan = $this->pesanan([
            'status' => 'dibatalkan',
            'alasan_pembatalan' => 'Batas pembayaran 24 jam terlewat',
        ]);

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.status', 'dibatalkan')
            ->assertJsonPath('data.alasan_pembatalan', 'Batas pembayaran 24 jam terlewat');
    }

    /** Pesanan lama bisa saja belum punya riwayat; itu bukan galat. */
    public function test_detail_tanpa_riwayat_dijawab_200_dengan_larik_kosong(): void
    {
        $pesanan = $this->pesanan();

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonCount(0, 'data.riwayat');
    }

    /** NFR-SEC-03: pesanan User lain dijawab sama seperti pesanan yang tidak ada. */
    /** FR-ORDR-04 #3: pesanan Dikirim memuat tautan lacak eksternal. */
    public function test_detail_pesanan_dikirim_memuat_tautan_lacak(): void
    {
        $pesanan = $this->pesanan([
            'status' => 'dikirim',
            'nomor_resi' => 'JP123456789',
            'dikirim_pada' => now()->subDay(),
        ]);

        $tautan = rtrim((string) config('services.tracking.base_url'), '/').'/JP123456789';

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.nomor_resi', 'JP123456789')
            ->assertJsonPath('data.tautan_lacak', $tautan);
    }

    public function test_detail_pesanan_belum_dikirim_tidak_punya_tautan_lacak(): void
    {
        $pesanan = $this->pesanan(['status' => 'siap_kirim']);

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.tautan_lacak', null);
    }

    public function test_detail_pesanan_user_lain_dijawab_404(): void
    {
        $milikOrangLain = $this->pesanan([], User::factory()->create());

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$milikOrangLain->nomor_pesanan)
            ->assertStatus(404)
            ->assertJson(['message' => 'Pesanan tidak ditemukan']);
    }

    public function test_detail_nomor_tidak_dikenal_dijawab_404(): void
    {
        $this->actingAs($this->user)
            ->getJson('/pesanan/INV-TIDAK-ADA')
            ->assertStatus(404)
            ->assertJson(['message' => 'Pesanan tidak ditemukan']);
    }

    public function test_detail_menolak_tamu(): void
    {
        $pesanan = $this->pesanan();

        $this->getJson('/pesanan/'.$pesanan->nomor_pesanan)->assertStatus(401);
    }
}
