<?php

namespace Tests\Feature;

use App\Mail\Pesanan\PembayaranMail;
use App\Models\Auth\User;
use App\Models\Design;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FR-SYS-01 #2 + FR-CDSN-05 #8: lewat 3 hari kalender, scheduler membatalkan
 * pesanan, menandai desain kedaluwarsa, melepas reservasi, dan mengabari User.
 */
class MasaRevisiKedaluwarsaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->user = User::factory()->create();
    }

    /** @return array{pesanan: Order, varian: Variant, desain: Design} */
    private function siapRevisi(array $gantiDesain = [], array $gantiPesanan = []): array
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
            'sku' => 'TAS-REVISI',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
            'stok_fisik' => 10,
            'stok_direservasi' => 2,
        ]);

        $desain = Design::create(array_merge([
            'user_id' => $this->user->id,
            'variant_id' => $varian->id,
            'data_desain' => ['elemen' => []],
            'status' => 'perlu_revisi',
            'versi' => 2,
            'batas_revisi_pada' => now()->subMinute(),
        ], $gantiDesain));

        $pesanan = Order::create(array_merge([
            'user_id' => $this->user->id,
            'nomor_pesanan' => 'INV-REVISI-1',
            'status' => 'menunggu_revisi_desain',
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
        ], $gantiPesanan));

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'design_id' => $desain->id,
            'nama_produk' => 'Tas Rotan Custom',
            'nama_varian' => '30x20',
            'sku' => 'TAS-REVISI',
            'quantity' => 2,
            'harga_satuan' => 150000,
            'sla_hari' => 7,
        ]);

        return compact('pesanan', 'varian', 'desain');
    }

    private function jalankan(): void
    {
        $this->artisan('orders:revisi-kedaluwarsa')->assertSuccessful();
    }

    public function test_masa_revisi_lewat_membatalkan_pesanan_dan_desain(): void
    {
        ['pesanan' => $pesanan, 'varian' => $varian, 'desain' => $desain] = $this->siapRevisi();

        $this->jalankan();

        $this->assertSame('dibatalkan', $pesanan->refresh()->status);
        $this->assertSame('Masa revisi desain berakhir', $pesanan->alasan_pembatalan);
        $this->assertSame('kedaluwarsa', $desain->refresh()->status);

        // Stok Fisik tidak disentuh; hanya reservasi yang dilepas.
        $varian->refresh();
        $this->assertSame(10, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);

        $riwayat = OrderStatusHistory::where('order_id', $pesanan->id)->latest('id')->firstOrFail();
        $this->assertSame('menunggu_revisi_desain', $riwayat->status_lama);
        $this->assertSame('dibatalkan', $riwayat->status_baru);
        $this->assertStringContainsString('FR-SYS-01 #2', (string) $riwayat->catatan);

        $kabar = Notification::where('user_id', $this->user->id)->sole();
        $this->assertStringContainsString('Masa revisi desain berakhir', $kabar->pesan);
        Mail::assertSent(PembayaranMail::class, 1);
    }

    public function test_pesanan_yang_sudah_dikirim_ulang_tidak_dibatalkan(): void
    {
        ['pesanan' => $pesanan, 'desain' => $desain] = $this->siapRevisi([], [
            'status' => 'menunggu_validasi',
        ]);

        $this->jalankan();

        $this->assertSame('menunggu_validasi', $pesanan->refresh()->status);
        $this->assertSame('perlu_revisi', $desain->refresh()->status);
        $this->assertSame(0, Notification::count());
    }

    public function test_masa_revisi_belum_lewat_tidak_dibatalkan(): void
    {
        ['pesanan' => $pesanan, 'desain' => $desain] = $this->siapRevisi([
            'batas_revisi_pada' => now()->addDay(),
        ]);

        $this->jalankan();

        $this->assertSame('menunggu_revisi_desain', $pesanan->refresh()->status);
        $this->assertSame('perlu_revisi', $desain->refresh()->status);
    }

    public function test_pengulangan_scheduler_tidak_mengirim_kabar_dua_kali(): void
    {
        ['pesanan' => $pesanan] = $this->siapRevisi();

        $this->jalankan();
        $this->jalankan();

        $this->assertSame(1, OrderStatusHistory::where('order_id', $pesanan->id)->count());
        $this->assertSame(1, Notification::where('user_id', $this->user->id)->count());
        Mail::assertSent(PembayaranMail::class, 1);
    }
}
