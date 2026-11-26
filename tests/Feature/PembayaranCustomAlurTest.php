<?php

namespace Tests\Feature;

use App\Mail\Pesanan\PembayaranMail;
use App\Models\Auth\User;
use App\Models\Design;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FR-PAYM-02 #2/#3/#4: bukti bahwa alur bayar custom memakai jalur gateway
 * yang sama dengan produk jadi setelah desain disetujui, dan desain yang sudah
 * Lunas tidak bisa diubah sendiri oleh User.
 */
class PembayaranCustomAlurTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'SB-Mid-server-kunci-uji';

    private const SNAP = 'https://app.sandbox.midtrans.com/snap/v1/transactions';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => self::KUNCI,
            'services.midtrans.client_key' => 'SB-Mid-client-kunci-uji',
            'services.midtrans.is_production' => false,
        ]);

        Mail::fake();

        $this->user = User::factory()->create();
    }

    /** @return array{pesanan: Order, varian: Variant, desain: Design} */
    private function custom(array $ganti = []): array
    {
        $produk = Product::create([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tas Rotan Custom',
            'deskripsi' => 'Tas anyaman rotan',
            'tipe' => 'custom',
            'harga_dasar' => 150000,
            'is_aktif' => true,
            'sla_hari' => 7,
        ]);

        $varian = $produk->variants()->create([
            'sku' => 'TAS-BAYAR',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
            'stok_fisik' => 10,
            'stok_direservasi' => 2,
        ]);

        $desain = Design::create([
            'user_id' => $this->user->id,
            'variant_id' => $varian->id,
            'data_desain' => ['elemen' => []],
            'status' => 'disetujui',
            'versi' => 2,
        ]);

        $pesanan = Order::create(array_merge([
            'user_id' => $this->user->id,
            'nomor_pesanan' => 'INV-CUSTOM-1',
            'status' => 'menunggu_pembayaran',
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
            'kadaluwarsa_pada' => now()->addHours(24),
        ], $ganti));

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'design_id' => $desain->id,
            'nama_produk' => 'Tas Rotan Custom',
            'nama_varian' => '30x20',
            'sku' => 'TAS-BAYAR',
            'quantity' => 2,
            'harga_satuan' => 150000,
            'sla_hari' => 7,
        ]);

        return compact('pesanan', 'varian', 'desain');
    }

    /** FR-PAYM-02 #2: desain Disetujui, User bayar lewat gateway. */
    public function test_pembayaran_custom_setelah_desain_disetujui_melunasi_pesanan(): void
    {
        ['pesanan' => $pesanan, 'varian' => $varian] = $this->custom();

        Http::fake([self::SNAP => Http::response([
            'token' => 'token-snap-custom',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/token-snap-custom',
        ], 201)]);

        $this->actingAs($this->user)
            ->postJson('/pesanan/'.$pesanan->nomor_pesanan.'/pembayaran')
            ->assertCreated()
            ->assertJsonPath('data.snap_token', 'token-snap-custom');

        $badan = [
            'order_id' => $pesanan->nomor_pesanan,
            'status_code' => '200',
            'gross_amount' => '150000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'trx-custom-1',
            'payment_type' => 'bank_transfer',
            'fraud_status' => 'accept',
        ];
        $badan['signature_key'] = hash(
            'sha512',
            $badan['order_id'].$badan['status_code'].$badan['gross_amount'].self::KUNCI,
        );

        $this->postJson('/pembayaran/notifikasi', $badan)
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'lunas')
            ->assertJsonPath('data.status_pesanan', 'diproses');

        $this->assertSame('lunas', Payment::sole()->status);
        $this->assertSame('diproses', $pesanan->refresh()->status);

        // Lunas: Stok Direservasi dan Stok Fisik sama-sama turun.
        $varian->refresh();
        $this->assertSame(8, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);
    }

    /** FR-PAYM-02 #3: link bayar custom lewat 24 jam dibatalkan scheduler. */
    public function test_link_bayar_custom_kedaluwarsa_membatalkan_pesanan(): void
    {
        ['pesanan' => $pesanan, 'varian' => $varian] = $this->custom([
            'kadaluwarsa_pada' => now()->subMinute(),
        ]);

        $pembayaran = Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-custom',
            'jumlah' => 150000,
            'status' => 'belum_dibayar',
            'kadaluwarsa_pada' => $pesanan->kadaluwarsa_pada,
        ]);

        $this->artisan('orders:kedaluwarsa')->assertSuccessful();

        $this->assertSame('dibatalkan', $pesanan->refresh()->status);
        $this->assertSame('kedaluwarsa', $pembayaran->refresh()->status);

        // Belum Lunas: hanya reservasi yang dilepas.
        $varian->refresh();
        $this->assertSame(10, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);

        $this->assertSame(1, Notification::where('user_id', $this->user->id)->count());
        Mail::assertSent(PembayaranMail::class, 1);
    }

    /** FR-PAYM-02 #4: setelah Lunas, User tidak mengubah desain sendiri. */
    public function test_desain_custom_tidak_bisa_diubah_setelah_pembayaran_lunas(): void
    {
        ['pesanan' => $pesanan, 'desain' => $desain] = $this->custom([
            'status' => 'diproses',
        ]);

        Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-custom',
            'jumlah' => 150000,
            'status' => 'lunas',
            'dibayar_pada' => now()->subHour(),
        ]);

        $sebelum = $desain->data_desain;

        $this->actingAs($this->user)
            ->putJson('/desain/'.$desain->id, [
                'data_desain' => ['elemen' => []],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Desain ini tidak dapat diubah lagi');

        $this->assertEquals($sebelum, $desain->refresh()->data_desain);
        $this->assertSame('disetujui', $desain->status);
    }
}
