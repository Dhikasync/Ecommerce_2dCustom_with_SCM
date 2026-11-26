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
 * FR-ORDR-03 #6/#7, UC-38: Admin menandai Siap Kirim. Custom harus sudah
 * Sedang Dicetak; produk jadi cukup Diproses. User dikabari perubahan status.
 */
class SiapKirimTest extends TestCase
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

    /** @return array{pesanan: Order, varian: Variant} */
    private function pesanan(string $tipe, string $status): array
    {
        $user = User::factory()->create();

        $produk = Product::create([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => $tipe === 'custom' ? 'Tas Rotan Custom' : 'Tote Bag',
            'deskripsi' => 'Tasanyaman',
            'tipe' => $tipe,
            'harga_dasar' => 150000,
            'is_aktif' => true,
            'sla_hari' => $tipe === 'custom' ? 7 : null,
        ]);

        $varian = $produk->variants()->create([
            'sku' => 'TAS-KIRIM-'.$tipe,
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
            'stok_fisik' => 10,
            'stok_direservasi' => 0,
        ]);

        $desain = $tipe === 'custom' ? Design::create([
            'user_id' => $user->id,
            'variant_id' => $varian->id,
            'data_desain' => ['elemen' => []],
            'status' => 'disetujui',
            'versi' => 2,
        ]) : null;

        $pesanan = Order::create([
            'user_id' => $user->id,
            'nomor_pesanan' => 'INV-KIRIM-'.$tipe,
            'status' => $status,
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
        ]);

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'design_id' => $desain?->id,
            'nama_produk' => $produk->nama,
            'nama_varian' => '30x20',
            'sku' => $varian->sku,
            'quantity' => 2,
            'harga_satuan' => 150000,
            'sla_hari' => $tipe === 'custom' ? 7 : null,
        ]);

        Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-kirim',
            'jumlah' => 150000,
            'status' => 'lunas',
            'dibayar_pada' => now()->subHour(),
        ]);

        return compact('pesanan', 'varian');
    }

    private function tandaiSiapKirim(string $nomor, ?User $admin = null)
    {
        return $this->actingAs($admin ?? $this->admin())
            ->postJson('/admin/pesanan/'.$nomor.'/siap-kirim');
    }

    public function test_admin_menandai_pesanan_custom_siap_kirim(): void
    {
        ['pesanan' => $pesanan] = $this->pesanan('custom', 'sedang_dicetak');
        $admin = $this->admin();

        $this->tandaiSiapKirim('INV-KIRIM-custom', $admin)
            ->assertOk()
            ->assertJsonPath('data.status', 'siap_kirim');

        $this->assertSame('siap_kirim', $pesanan->refresh()->status);

        $riwayat = OrderStatusHistory::where('order_id', $pesanan->id)->sole();
        $this->assertSame('sedang_dicetak', $riwayat->status_lama);
        $this->assertSame('siap_kirim', $riwayat->status_baru);
        $this->assertSame($admin->id, $riwayat->diubah_oleh);

        $this->assertSame(1, Notification::where('user_id', $pesanan->user_id)->count());
        Mail::assertSent(PembayaranMail::class, 1);
    }

    public function test_admin_menandai_produk_jadi_siap_kirim(): void
    {
        ['pesanan' => $pesanan] = $this->pesanan('jadi', 'diproses');

        $this->tandaiSiapKirim('INV-KIRIM-jadi')
            ->assertOk()
            ->assertJsonPath('data.status', 'siap_kirim');

        $this->assertSame('siap_kirim', $pesanan->refresh()->status);
    }

    public function test_siap_kirim_menolak_status_custom_yang_belum_dicetak(): void
    {
        ['pesanan' => $pesanan] = $this->pesanan('custom', 'diproses');

        $this->tandaiSiapKirim('INV-KIRIM-custom')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Perubahan status tidak valid');

        $this->assertSame('diproses', $pesanan->refresh()->status);
        $this->assertSame(0, OrderStatusHistory::count());
        $this->assertSame(0, Notification::count());
    }

    public function test_siap_kirim_hanya_untuk_admin_ecommerce(): void
    {
        $this->pesanan('jadi', 'diproses');

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->postJson('/admin/pesanan/INV-KIRIM-jadi/siap-kirim')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin_scm']))
            ->postJson('/admin/pesanan/INV-KIRIM-jadi/siap-kirim')
            ->assertForbidden();
    }
}
