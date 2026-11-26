<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Design;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tes penembus FR-CDSN-05 #6: status order saja tidak boleh dijadikan bukti
 * bahwa desain sudah Disetujui. Keadaan inkonsisten harus ditolak.
 */
class GuardStatusDesainTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{pesanan: Order, varian: Variant, desain: Design} */
    private function custom(string $statusDesain, array $gantiPesanan = []): array
    {
        $user = User::factory()->create();

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
            'sku' => 'TAS-GUARD',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
            'stok_fisik' => 10,
            'stok_direservasi' => 0,
        ]);

        $desain = Design::create([
            'user_id' => $user->id,
            'variant_id' => $varian->id,
            'data_desain' => ['elemen' => []],
            'status' => $statusDesain,
            'versi' => 1,
        ]);

        $pesanan = Order::create(array_merge([
            'user_id' => $user->id,
            'nomor_pesanan' => 'INV-GUARD-1',
            'status' => 'menunggu_pembayaran',
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
            'kadaluwarsa_pada' => now()->addHours(24),
        ], $gantiPesanan));

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'design_id' => $desain->id,
            'nama_produk' => 'Tas Rotan Custom',
            'nama_varian' => '30x20',
            'sku' => 'TAS-GUARD',
            'quantity' => 1,
            'harga_satuan' => 150000,
            'sla_hari' => 7,
        ]);

        return compact('pesanan', 'varian', 'desain');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Mail::fake();
    }

    /** FR-CDSN-05 #6: order menunggu bayar + desain belum Disetujui harus ditolak. */
    public function test_pembayaran_ditolak_jika_desain_belum_disetujui(): void
    {
        ['pesanan' => $pesanan] = $this->custom('menunggu_validasi');

        $this->actingAs($pesanan->user)
            ->postJson('/pesanan/'.$pesanan->nomor_pesanan.'/pembayaran')
            ->assertStatus(422);

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    /** FR-ORDR-03 #5: order Diproses + desain Perlu Revisi tidak boleh dicetak. */
    public function test_mulai_cetak_ditolak_jika_desain_perlu_revisi(): void
    {
        ['pesanan' => $pesanan] = $this->custom('perlu_revisi', ['status' => 'diproses']);
        Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-guard',
            'jumlah' => 150000,
            'status' => 'lunas',
            'dibayar_pada' => now()->subHour(),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin_ecommerce']))
            ->postJson('/admin/pesanan/'.$pesanan->nomor_pesanan.'/mulai-cetak')
            ->assertStatus(422);

        $this->assertSame('diproses', $pesanan->refresh()->status);
        $this->assertSame(0, OrderStatusHistory::count());
        $this->assertSame(0, Notification::count());
    }
}
