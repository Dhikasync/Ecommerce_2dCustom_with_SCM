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
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FR-SYS-01 #1/#7, FR-PAYM-01 #4, FR-PAYM-02 #3: pesanan yang melewati batas
 * bayar 24 jam dibatalkan scheduler, Stok Direservasi dilepas, dan User diberi
 * kabar lewat email + in-app. Pesanan yang sudah Lunas tidak boleh tersentuh.
 */
class PesananKedaluwarsaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->user = User::factory()->create();
    }

    private function varian(int $direservasi = 2): Variant
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
            'stok_fisik' => 10,
            'stok_direservasi' => $direservasi,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array{pesanan: Order, varian: Variant}
     */
    private function pesanan(array $ganti = [], ?Variant $varian = null): array
    {
        $varian ??= $this->varian();

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
            // Default: batas bayar sudah lewat satu menit.
            'kadaluwarsa_pada' => now()->subMinute(),
        ], $ganti));

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'nama_produk' => 'Tote Bag',
            'nama_varian' => 'TOTE-1',
            'sku' => 'TOTE-1',
            'quantity' => 2,
            'harga_satuan' => 20000,
        ]);

        return ['pesanan' => $pesanan, 'varian' => $varian];
    }

    private function tagihan(Order $pesanan, string $status = 'belum_dibayar'): Payment
    {
        return Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-snap-uji',
            'jumlah' => 48000,
            'status' => $status,
            'kadaluwarsa_pada' => $pesanan->kadaluwarsa_pada,
        ]);
    }

    private function jalankan(): void
    {
        $this->artisan('orders:kedaluwarsa')->assertSuccessful();
    }

    public function test_pesanan_lewat_batas_dibatalkan_dan_reservasi_dilepas(): void
    {
        ['pesanan' => $pesanan, 'varian' => $varian] = $this->pesanan();
        $pembayaran = $this->tagihan($pesanan);

        $this->jalankan();

        $this->assertSame('dibatalkan', $pesanan->refresh()->status);
        $this->assertSame('kedaluwarsa', $pembayaran->refresh()->status);

        // Stok Fisik tidak disentuh: barang belum pernah keluar gudang.
        $varian->refresh();
        $this->assertSame(10, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);

        $riwayat = OrderStatusHistory::where('order_id', $pesanan->id)->latest('id')->first();
        $this->assertSame('menunggu_pembayaran', $riwayat->status_lama);
        $this->assertSame('dibatalkan', $riwayat->status_baru);
        $this->assertNull($riwayat->diubah_oleh);
        $this->assertStringContainsString('FR-SYS-01 #1', (string) $riwayat->catatan);

        $this->assertSame(1, Notification::where('user_id', $this->user->id)->count());
        Mail::assertSent(PembayaranMail::class, 1);
    }

    /** Pesanan yang belum melewati batas tidak boleh tersentuh. */
    public function test_pesanan_belum_lewat_batas_tidak_dibatalkan(): void
    {
        ['pesanan' => $pesanan, 'varian' => $varian] = $this->pesanan([
            'kadaluwarsa_pada' => now()->addHour(),
        ]);

        $this->jalankan();

        $this->assertSame('menunggu_pembayaran', $pesanan->refresh()->status);
        $this->assertSame(2, $varian->refresh()->stok_direservasi);
        $this->assertSame(0, OrderStatusHistory::count());
        $this->assertSame(0, Notification::count());
        Mail::assertNothingSent();
    }

    /** Skenario #7: pembayaran yang sudah Lunas tidak boleh dibatalkan. */
    public function test_pesanan_yang_sudah_lunas_tidak_dibatalkan(): void
    {
        ['pesanan' => $pesanan, 'varian' => $varian] = $this->pesanan();
        $this->tagihan($pesanan, 'lunas');

        $this->jalankan();

        $this->assertSame('menunggu_pembayaran', $pesanan->refresh()->status);
        $this->assertSame(2, $varian->refresh()->stok_direservasi);
        $this->assertSame(0, Notification::count());
    }

    /** Skenario #7: scheduler yang berjalan dua kali hanya membatalkan sekali. */
    public function test_dijalankan_dua_kali_tidak_membatalkan_dua_kali(): void
    {
        ['pesanan' => $pesanan, 'varian' => $varian] = $this->pesanan();
        $this->tagihan($pesanan);

        $this->jalankan();
        $this->jalankan();

        $this->assertSame('dibatalkan', $pesanan->refresh()->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $pesanan->id)->count());
        $this->assertSame(1, Notification::where('user_id', $this->user->id)->count());
        Mail::assertSent(PembayaranMail::class, 1);
        // Reservasi tidak boleh dilepas dua kali sampai menjadi minus.
        $this->assertSame(0, $varian->refresh()->stok_direservasi);
    }

    /** Checkout belum membuat baris payments; pembatalan tetap harus berjalan. */
    public function test_pesanan_tanpa_baris_pembayaran_tetap_dibatalkan(): void
    {
        ['pesanan' => $pesanan, 'varian' => $varian] = $this->pesanan();

        $this->jalankan();

        $this->assertSame('dibatalkan', $pesanan->refresh()->status);
        $this->assertSame(0, $varian->refresh()->stok_direservasi);
        $this->assertSame(1, Notification::where('user_id', $this->user->id)->count());
    }

    /** FR-PAYM-02 #3 hanya untuk pesanan yang sudah menunggu pembayaran. */
    public function test_pesanan_custom_menunggu_validasi_tidak_disentuh(): void
    {
        ['pesanan' => $pesanan, 'varian' => $varian] = $this->pesanan([
            'status' => 'menunggu_validasi',
            'kadaluwarsa_pada' => null,
        ]);

        $this->jalankan();

        $this->assertSame('menunggu_validasi', $pesanan->refresh()->status);
        $this->assertSame(2, $varian->refresh()->stok_direservasi);
        $this->assertSame(0, Notification::count());
    }

    public function test_pesanan_yang_sudah_dibatalkan_tidak_diproses_lagi(): void
    {
        ['pesanan' => $pesanan] = $this->pesanan(['status' => 'dibatalkan']);

        $this->jalankan();

        $this->assertSame('dibatalkan', $pesanan->refresh()->status);
        $this->assertSame(0, OrderStatusHistory::count());
        $this->assertSame(0, Notification::count());
    }

    public function test_perintah_melaporkan_jumlah_pesanan_yang_dibatalkan(): void
    {
        $varian = $this->varian(direservasi: 6);
        ['pesanan' => $satu] = $this->pesanan([], $varian);
        $this->pesanan(['nomor_pesanan' => 'INV-UJI-2'], $varian);
        $this->pesanan(['nomor_pesanan' => 'INV-UJI-3', 'kadaluwarsa_pada' => now()->addHour()], $varian);

        $this->artisan('orders:kedaluwarsa')
            ->expectsOutputToContain('2 pesanan')
            ->assertSuccessful();

        $this->assertSame('dibatalkan', $satu->refresh()->status);
        $this->assertSame(2, Notification::count());
        // Dua pesanan dibatalkan (2 + 2 unit); pesanan ketiga masih memegang 2.
        $this->assertSame(2, $varian->refresh()->stok_direservasi);
    }

    /** Stok dilepas per varian, dijumlahkan bila satu varian muncul dua kali. */
    public function test_reservasi_dilepas_sesuai_jumlah_per_varian(): void
    {
        $varian = $this->varian(direservasi: 5);
        ['pesanan' => $pesanan] = $this->pesanan([], $varian);
        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'nama_produk' => 'Tote Bag',
            'nama_varian' => 'TOTE-1',
            'sku' => 'TOTE-1',
            'quantity' => 3,
            'harga_satuan' => 20000,
        ]);

        $this->jalankan();

        // 5 direservasi, 2 + 3 dilepas, tidak menyisakan angka negatif.
        $this->assertSame(0, $varian->refresh()->stok_direservasi);
        $this->assertSame(10, $varian->stok_fisik);
    }
}
