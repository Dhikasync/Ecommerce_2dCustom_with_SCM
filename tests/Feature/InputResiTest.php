<?php

namespace Tests\Feature;

use App\Mail\Pesanan\PembayaranMail;
use App\Models\Auth\User;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FR-ORDR-04 #1/#2, UC-39: Admin menginput nomor resi untuk pesanan Siap Kirim.
 * User diberi kabar berisi nomor resi dan tautan detail pesanan.
 */
class InputResiTest extends TestCase
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

    private function pesananSiapKirim(array $ganti = []): Order
    {
        $user = User::factory()->create();

        $produk = Product::create([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tote Bag',
            'deskripsi' => 'Tas kanvas',
            'tipe' => 'jadi',
            'harga_dasar' => 20000,
            'gambar' => 'produk/tote.jpg',
            'is_aktif' => true,
        ]);

        $varian = $produk->variants()->create([
            'sku' => 'TOTE-RESI',
            'harga' => 20000,
            'berat' => 250,
            'moq' => 1,
            'stok_fisik' => 10,
            'stok_direservasi' => 0,
        ]);

        $pesanan = Order::create(array_merge([
            'user_id' => $user->id,
            'nomor_pesanan' => 'INV-RESI-1',
            'status' => 'siap_kirim',
            'subtotal_barang' => 40000,
            'ongkir' => 8000,
            'total' => 48000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
        ], $ganti));

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'nama_produk' => 'Tote Bag',
            'nama_varian' => 'Reguler',
            'sku' => 'TOTE-RESI',
            'quantity' => 2,
            'harga_satuan' => 20000,
        ]);

        Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-resi',
            'jumlah' => 48000,
            'status' => 'lunas',
            'dibayar_pada' => now()->subHour(),
        ]);

        return $pesanan;
    }

    private function kirim(string $nomor = 'INV-RESI-1', ?User $admin = null, array $data = ['nomor_resi' => 'JP123456789'])
    {
        return $this->actingAs($admin ?? $this->admin())
            ->postJson('/admin/pesanan/'.$nomor.'/kirim', $data);
    }

    public function test_admin_menginput_resi_mengubah_status_menjadi_dikirim(): void
    {
        $pesanan = $this->pesananSiapKirim();
        $admin = $this->admin();
        $tautan = route('pesanan.show', $pesanan->nomor_pesanan);

        $this->kirim('INV-RESI-1', $admin)
            ->assertOk()
            ->assertJsonPath('data.status', 'dikirim')
            ->assertJsonPath('data.nomor_resi', 'JP123456789');

        $pesanan->refresh();
        $this->assertSame('dikirim', $pesanan->status);
        $this->assertSame('JP123456789', $pesanan->nomor_resi);
        $this->assertNotNull($pesanan->dikirim_pada);

        $riwayat = OrderStatusHistory::where('order_id', $pesanan->id)->sole();
        $this->assertSame('siap_kirim', $riwayat->status_lama);
        $this->assertSame('dikirim', $riwayat->status_baru);
        $this->assertSame($admin->id, $riwayat->diubah_oleh);

        $kabar = Notification::where('user_id', $pesanan->user_id)->sole();
        $this->assertStringContainsString('JP123456789', $kabar->pesan);
        $this->assertStringContainsString($tautan, $kabar->pesan);
        Mail::assertSent(PembayaranMail::class, 1);
    }

    public function test_nomor_resi_wajib_diisi(): void
    {
        $pesanan = $this->pesananSiapKirim();

        $this->kirim('INV-RESI-1', null, ['nomor_resi' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nomor resi wajib diisi');

        $this->assertSame('siap_kirim', $pesanan->refresh()->status);
        $this->assertNull($pesanan->nomor_resi);
        $this->assertSame(0, Notification::count());
    }

    public function test_input_resi_menolak_pesanan_yang_belum_siap_kirim(): void
    {
        $pesanan = $this->pesananSiapKirim(['status' => 'diproses']);

        $this->kirim()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Perubahan status tidak valid');

        $this->assertSame('diproses', $pesanan->refresh()->status);
    }

    public function test_input_resi_hanya_untuk_admin_ecommerce(): void
    {
        $this->pesananSiapKirim();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->postJson('/admin/pesanan/INV-RESI-1/kirim', ['nomor_resi' => 'JP1'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin_scm']))
            ->postJson('/admin/pesanan/INV-RESI-1/kirim', ['nomor_resi' => 'JP1'])
            ->assertForbidden();
    }
}
