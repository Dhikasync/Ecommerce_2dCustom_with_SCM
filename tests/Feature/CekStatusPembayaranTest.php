<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FR-PAYM-01 #5, UC-33, user-story.md:527 dan srs.md:553: notifikasi Midtrans
 * yang terlambat ditutup dengan bertanya langsung ke gateway, lalu status
 * pembayaran dan pesanan diperbarui secara sinkron.
 */
class CekStatusPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS = 'https://api.sandbox.midtrans.com/*';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'SB-Mid-server-kunci-uji',
            'services.midtrans.client_key' => 'SB-Mid-client-kunci-uji',
            'services.midtrans.is_production' => false,
        ]);

        $this->user = User::factory()->create();
    }

    private function varian(int $stokFisik = 10, int $direservasi = 2): Variant
    {
        $produk = Product::create([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tote Bag',
            'deskripsi' => 'Tas kanvas',
            'tipe' => 'jadi',
            'harga_dasar' => 20000,
            'gambar' => 'produk/tote.jpg',
            'is_aktif' => true,
        ]);

        return $produk->variants()->create([
            'sku' => 'TOTE-1',
            'harga' => 20000,
            'berat' => 250,
            'moq' => 1,
            'stok_fisik' => $stokFisik,
            'stok_direservasi' => $direservasi,
        ]);
    }

    /**
     * Pesanan yang sudah checkout: stok 2 unit direservasi, tagihan dibuat
     * lewat FR-PAYM-01 #1.
     *
     * @param  array<string, mixed>  $gantiPesanan
     * @return array{pesanan: Order, pembayaran: Payment, varian: Variant}
     */
    private function pesanan(array $gantiPesanan = [], int $jumlahItem = 2): array
    {
        $varian = $this->varian(direservasi: $jumlahItem);

        $pesanan = Order::create(array_merge([
            'user_id' => $this->user->id,
            'nomor_pesanan' => 'INV-UJI-1',
            'status' => 'menunggu_pembayaran',
            'subtotal_barang' => 40000,
            'ongkir' => 8000,
            'diskon_voucher' => 0,
            'total' => 48000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
            'kadaluwarsa_pada' => now()->addHours(24),
        ], $gantiPesanan));

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'nama_produk' => 'Tote Bag',
            'nama_varian' => 'TOTE-1',
            'sku' => 'TOTE-1',
            'quantity' => $jumlahItem,
            'harga_satuan' => 20000,
        ]);

        $pembayaran = Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-snap-uji',
            'jumlah' => 48000,
            'status' => 'belum_dibayar',
            'kadaluwarsa_pada' => $pesanan->kadaluwarsa_pada,
        ]);

        return ['pesanan' => $pesanan, 'pembayaran' => $pembayaran, 'varian' => $varian];
    }

    /** @param  array<string, mixed>  $ganti */
    private function badanStatus(array $ganti = []): array
    {
        return array_merge([
            'order_id' => 'INV-UJI-1',
            'transaction_id' => 'trx-uji-1',
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'gross_amount' => '48000.00',
            'status_code' => '200',
            'fraud_status' => 'accept',
        ], $ganti);
    }

    /** @param  array<string, mixed>  $ganti */
    private function palsukanStatus(array $ganti = []): void
    {
        Http::fake([self::STATUS => Http::response($this->badanStatus($ganti))]);
    }

    private function cek(string $nomor = 'INV-UJI-1')
    {
        return $this->actingAs($this->user)->postJson('/pesanan/'.$nomor.'/pembayaran/cek');
    }

    // ---------- Pembayaran berhasil (efek sama dengan skenario #2) ----------

    public function test_settlement_menjadikan_pembayaran_lunas_dan_pesanan_diproses(): void
    {
        ['pesanan' => $pesanan, 'pembayaran' => $pembayaran, 'varian' => $varian] = $this->pesanan();
        $this->palsukanStatus();

        $this->cek()
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'lunas')
            ->assertJsonPath('data.status_pesanan', 'diproses');

        $pembayaran->refresh();
        $this->assertSame('lunas', $pembayaran->status);
        $this->assertSame('trx-uji-1', $pembayaran->transaction_id);
        $this->assertSame('bank_transfer', $pembayaran->metode);
        $this->assertNotNull($pembayaran->dibayar_pada);

        $this->assertSame('diproses', $pesanan->refresh()->status);

        // FR-PAYM-01 #2: Stok Direservasi dikonversi menjadi pengurangan Stok Fisik.
        $varian->refresh();
        $this->assertSame(8, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);

        $riwayat = OrderStatusHistory::where('order_id', $pesanan->id)->latest('id')->first();
        $this->assertSame('diproses', $riwayat->status_baru);
        $this->assertNull($riwayat->diubah_oleh);
    }

    /** `capture` baru lunas kalau fraud_status-nya `accept`. */
    public function test_capture_dengan_fraud_accept_dianggap_lunas(): void
    {
        ['pembayaran' => $pembayaran] = $this->pesanan();
        $this->palsukanStatus(['transaction_status' => 'capture', 'fraud_status' => 'accept']);

        $this->cek()->assertOk()->assertJsonPath('data.status_pembayaran', 'lunas');

        $this->assertSame('lunas', $pembayaran->refresh()->status);
    }

    public function test_capture_dengan_fraud_challenge_belum_lunas(): void
    {
        ['pembayaran' => $pembayaran, 'varian' => $varian] = $this->pesanan();
        $this->palsukanStatus(['transaction_status' => 'capture', 'fraud_status' => 'challenge']);

        $this->cek()
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'belum_dibayar')
            ->assertJsonPath('data.status_pesanan', 'menunggu_pembayaran');

        $this->assertSame('belum_dibayar', $pembayaran->refresh()->status);
        $this->assertSame(10, $varian->refresh()->stok_fisik);
    }

    // ---------- Belum dibayar (skenario #5: notifikasi belum sampai) ----------

    public function test_pending_tidak_mengubah_status_apa_pun(): void
    {
        ['pesanan' => $pesanan, 'pembayaran' => $pembayaran] = $this->pesanan();
        $this->palsukanStatus(['transaction_status' => 'pending']);

        $this->cek()
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'belum_dibayar')
            ->assertJsonPath('data.status_pesanan', 'menunggu_pembayaran');

        $this->assertSame('belum_dibayar', $pembayaran->refresh()->status);
        $this->assertSame('menunggu_pembayaran', $pesanan->refresh()->status);
        $this->assertNull($pembayaran->dibayar_pada);
    }

    // ---------- Pembayaran gagal (efek sama dengan skenario #3) ----------

    public function test_deny_menjadikan_pembayaran_gagal_dan_pesanan_tetap_menunggu(): void
    {
        ['pesanan' => $pesanan, 'pembayaran' => $pembayaran, 'varian' => $varian] = $this->pesanan();
        $this->palsukanStatus(['transaction_status' => 'deny']);

        $this->cek()
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'gagal')
            ->assertJsonPath('data.status_pesanan', 'menunggu_pembayaran');

        $this->assertSame('gagal', $pembayaran->refresh()->status);
        $this->assertSame('menunggu_pembayaran', $pesanan->refresh()->status);
        // Reservasi tetap dipegang: User masih boleh mencoba metode lain.
        $this->assertSame(2, $varian->refresh()->stok_direservasi);
    }

    public function test_cancel_menjadikan_pembayaran_gagal(): void
    {
        ['pembayaran' => $pembayaran] = $this->pesanan();
        $this->palsukanStatus(['transaction_status' => 'cancel']);

        $this->cek()->assertOk()->assertJsonPath('data.status_pembayaran', 'gagal');

        $this->assertSame('gagal', $pembayaran->refresh()->status);
    }

    // ---------- Pembayaran kedaluwarsa (efek sama dengan skenario #4) ----------

    public function test_expire_membatalkan_pesanan_dan_melepas_reservasi(): void
    {
        ['pesanan' => $pesanan, 'pembayaran' => $pembayaran, 'varian' => $varian] = $this->pesanan();
        $this->palsukanStatus(['transaction_status' => 'expire']);

        $this->cek()
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'kedaluwarsa')
            ->assertJsonPath('data.status_pesanan', 'dibatalkan');

        $this->assertSame('kedaluwarsa', $pembayaran->refresh()->status);
        $this->assertSame('dibatalkan', $pesanan->refresh()->status);

        // Stok Fisik tidak berubah, hanya reservasinya yang dilepas.
        $varian->refresh();
        $this->assertSame(10, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);
    }

    // ---------- Penjagaan ----------

    /** Menekan tombol dua kali tidak boleh mengurangi stok dua kali. */
    public function test_cek_dua_kali_tidak_menerapkan_efek_ganda(): void
    {
        ['pesanan' => $pesanan, 'varian' => $varian] = $this->pesanan();
        $this->palsukanStatus();

        $this->cek()->assertOk()->assertJsonPath('data.status_pembayaran', 'lunas');
        $this->cek()->assertOk()->assertJsonPath('data.status_pembayaran', 'lunas');

        $varian->refresh();
        $this->assertSame(8, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $pesanan->id)->count());
    }

    /** Setelah lunas, cek berikutnya tidak perlu memanggil gateway lagi. */
    public function test_setelah_lunas_tidak_memanggil_gateway_lagi(): void
    {
        $this->pesanan();
        $this->palsukanStatus();

        $this->cek()->assertOk();
        $this->cek()->assertOk();

        Http::assertSentCount(1);
    }

    public function test_pesanan_milik_user_lain_dijawab_404(): void
    {
        $this->pesanan(['user_id' => User::factory()->create()->id]);
        Http::fake();

        $this->cek()
            ->assertStatus(404)
            ->assertJsonPath('message', 'Pesanan tidak ditemukan');

        Http::assertNothingSent();
    }

    public function test_tamu_tidak_dapat_memeriksa_status(): void
    {
        $this->pesanan();
        Http::fake();

        $this->postJson('/pesanan/INV-UJI-1/pembayaran/cek')->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_pesanan_yang_sudah_dibatalkan_tidak_dapat_diperiksa(): void
    {
        $this->pesanan(['status' => 'dibatalkan']);
        Http::fake();

        $this->cek()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan ini tidak dapat dibayar');

        Http::assertNothingSent();
    }

    public function test_pesanan_tanpa_tagihan_dijawab_422(): void
    {
        ['pembayaran' => $pembayaran] = $this->pesanan();
        $pembayaran->delete();
        Http::fake();

        $this->cek()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pembayaran untuk pesanan ini belum dibuat');

        Http::assertNothingSent();
    }

    public function test_gangguan_midtrans_dijawab_422_tanpa_mengubah_status(): void
    {
        ['pembayaran' => $pembayaran] = $this->pesanan();
        Http::fake([self::STATUS => Http::response(['status_message' => 'Internal server error'], 500)]);

        $this->cek()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pembayaran belum dapat diproses, silakan coba lagi');

        $this->assertSame('belum_dibayar', $pembayaran->refresh()->status);
    }

    /** Angka dari pihak ketiga tidak boleh langsung dipercaya. */
    public function test_nominal_gateway_yang_tidak_cocok_tidak_mengubah_status(): void
    {
        ['pesanan' => $pesanan, 'pembayaran' => $pembayaran] = $this->pesanan();
        $this->palsukanStatus(['gross_amount' => '1000.00']);

        $this->cek()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nominal pembayaran tidak cocok dengan pesanan');

        $this->assertSame('belum_dibayar', $pembayaran->refresh()->status);
        $this->assertSame('menunggu_pembayaran', $pesanan->refresh()->status);
    }

    public function test_respons_tidak_disimpan_cache(): void
    {
        $this->pesanan();
        $this->palsukanStatus();

        $respons = $this->cek();
        $respons->assertOk();
        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));
    }
}
