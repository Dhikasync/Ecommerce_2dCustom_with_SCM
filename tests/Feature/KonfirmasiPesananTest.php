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
 * FR-ORDR-04 #4: User mengonfirmasi paket diterima. Konfirmasi otomatis 3 hari
 * (FR-SYS-01 #5) dan ulasan (FR-ORDR-05) adalah OPSIONAL, jadi tidak diuji di sini.
 */
class KonfirmasiPesananTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->user = User::factory()->create();
    }

    private function pesanan(array $ganti = []): Order
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

        $varian = $produk->variants()->create([
            'sku' => 'TOTE-TERIMA',
            'harga' => 20000,
            'berat' => 250,
            'moq' => 1,
            'stok_fisik' => 10,
            'stok_direservasi' => 0,
        ]);

        $pesanan = Order::create(array_merge([
            'user_id' => $this->user->id,
            'nomor_pesanan' => 'INV-TERIMA-1',
            'status' => 'dikirim',
            'subtotal_barang' => 40000,
            'ongkir' => 8000,
            'total' => 48000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
            'nomor_resi' => 'JP987654321',
            'dikirim_pada' => now()->subDay(),
        ], $ganti));

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'nama_produk' => 'Tote Bag',
            'nama_varian' => 'Reguler',
            'sku' => 'TOTE-TERIMA',
            'quantity' => 2,
            'harga_satuan' => 20000,
        ]);

        Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-terima',
            'jumlah' => 48000,
            'status' => 'lunas',
            'dibayar_pada' => now()->subDays(2),
        ]);

        return $pesanan;
    }

    public function test_user_mengonfirmasi_pesanan_diterima(): void
    {
        $pesanan = $this->pesanan();

        $this->actingAs($this->user)
            ->postJson('/pesanan/'.$pesanan->nomor_pesanan.'/terima')
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai');

        $pesanan->refresh();
        $this->assertSame('selesai', $pesanan->status);
        $this->assertNotNull($pesanan->selesai_pada);

        $riwayat = OrderStatusHistory::where('order_id', $pesanan->id)->sole();
        $this->assertSame('dikirim', $riwayat->status_lama);
        $this->assertSame('selesai', $riwayat->status_baru);
        $this->assertSame($this->user->id, $riwayat->diubah_oleh);

        $this->assertSame(1, Notification::where('user_id', $this->user->id)->count());
        Mail::assertSent(PembayaranMail::class, 1);
    }

    public function test_konfirmasi_menolak_pesanan_yang_belum_dikirim(): void
    {
        $pesanan = $this->pesanan(['status' => 'siap_kirim']);

        $this->actingAs($this->user)
            ->postJson('/pesanan/'.$pesanan->nomor_pesanan.'/terima')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Perubahan status tidak valid');

        $this->assertSame('siap_kirim', $pesanan->refresh()->status);
        $this->assertSame(0, Notification::count());
    }

    public function test_konfirmasi_pesanan_user_lain_dijawab_404(): void
    {
        $pesanan = $this->pesanan(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)
            ->postJson('/pesanan/'.$pesanan->nomor_pesanan.'/terima')
            ->assertNotFound()
            ->assertJsonPath('message', 'Pesanan tidak ditemukan');
    }

    public function test_guest_tidak_bisa_mengonfirmasi(): void
    {
        $this->pesanan();

        $this->postJson('/pesanan/INV-TERIMA-1/terima')->assertUnauthorized();
    }
}
