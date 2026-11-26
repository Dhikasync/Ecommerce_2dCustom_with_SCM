<?php

namespace Tests\Feature;

use App\Mail\Pesanan\PembayaranMail;
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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FR-ORDR-03 #5/#7, UC-38: Admin Ecommerce memulai produksi cetak hanya untuk
 * pesanan custom yang sudah Diproses, dan setiap perubahan tercatat di riwayat.
 */
class ProduksiPesananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin_ecommerce']);
    }

    /** @return array{pesanan: Order, varian: Variant, desain: Design} */
    private function customDiproses(array $ganti = []): array
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
            'sku' => 'TAS-CETAK',
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
            'status' => 'disetujui',
            'versi' => 2,
        ]);

        $pesanan = Order::create(array_merge([
            'user_id' => $user->id,
            'nomor_pesanan' => 'INV-CETAK-1',
            'status' => 'diproses',
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
        ], $ganti));

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'design_id' => $desain->id,
            'nama_produk' => 'Tas Rotan Custom',
            'nama_varian' => '30x20',
            'sku' => 'TAS-CETAK',
            'quantity' => 2,
            'harga_satuan' => 150000,
            'sla_hari' => 7,
        ]);

        Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-cetak',
            'jumlah' => 150000,
            'status' => 'lunas',
            'dibayar_pada' => now()->subHour(),
        ]);

        return compact('pesanan', 'varian', 'desain');
    }

    private function mulaiCetak(string $nomor = 'INV-CETAK-1', ?User $admin = null)
    {
        return $this->actingAs($admin ?? $this->admin())
            ->postJson('/admin/pesanan/'.$nomor.'/mulai-cetak');
    }

    public function test_admin_memulai_cetak_pesanan_custom_yang_diproses(): void
    {
        ['pesanan' => $pesanan] = $this->customDiproses();
        $admin = $this->admin();

        $this->mulaiCetak('INV-CETAK-1', $admin)
            ->assertOk()
            ->assertJsonPath('data.status', 'sedang_dicetak');

        $this->assertSame('sedang_dicetak', $pesanan->refresh()->status);

        $riwayat = OrderStatusHistory::where('order_id', $pesanan->id)->sole();
        $this->assertSame('diproses', $riwayat->status_lama);
        $this->assertSame('sedang_dicetak', $riwayat->status_baru);
        $this->assertSame($admin->id, $riwayat->diubah_oleh);

        $this->assertSame(1, Notification::where('user_id', $pesanan->user_id)->count());
        Mail::assertSent(PembayaranMail::class, 1);
    }

    public function test_mulai_cetak_menolak_pesanan_yang_belum_diproses(): void
    {
        ['pesanan' => $pesanan] = $this->customDiproses([
            'status' => 'menunggu_pembayaran',
        ]);

        $this->mulaiCetak()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Perubahan status tidak valid');

        $this->assertSame('menunggu_pembayaran', $pesanan->refresh()->status);
        $this->assertSame(0, OrderStatusHistory::count());
        $this->assertSame(0, Notification::count());
    }

    public function test_mulai_cetak_menolak_pesanan_produk_jadi(): void
    {
        ['pesanan' => $pesanan] = $this->customDiproses();
        $pesanan->orderItems()->first()->update(['design_id' => null]);

        $this->mulaiCetak()->assertStatus(422);

        $this->assertSame('diproses', $pesanan->refresh()->status);
    }

    public function test_mulai_cetak_hanya_untuk_admin_ecommerce(): void
    {
        $this->customDiproses();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->postJson('/admin/pesanan/INV-CETAK-1/mulai-cetak')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin_scm']))
            ->postJson('/admin/pesanan/INV-CETAK-1/mulai-cetak')
            ->assertForbidden();
    }
}
