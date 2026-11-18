<?php

namespace Tests\Feature;

use App\Mail\Pesanan\PembayaranMail;
use App\Models\Auth\User;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FR-PAYM-01 #2/#3/#4, UC-31, srs.md:297 dan KM-02: notifikasi HTTP dari
 * Midtrans diverifikasi tanda tangannya, diproses sekali, lalu status
 * pembayaran, pesanan, dan stok mengikuti hasilnya.
 */
class NotifikasiPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'SB-Mid-server-kunci-uji';

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
     * @param  array<string, mixed>  $gantiPesanan
     * @return array{pesanan: Order, pembayaran: Payment, varian: Variant}
     */
    private function pesanan(array $gantiPesanan = []): array
    {
        $varian = $this->varian();

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
            'quantity' => 2,
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
    private function badan(array $ganti = []): array
    {
        return array_merge([
            'order_id' => 'INV-UJI-1',
            'status_code' => '200',
            'gross_amount' => '48000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'trx-uji-1',
            'payment_type' => 'bank_transfer',
            'fraud_status' => 'accept',
        ], $ganti);
    }

    /** @param  array<string, mixed>  $badan */
    private function tandaTangan(array $badan): string
    {
        return hash(
            'sha512',
            $badan['order_id'].$badan['status_code'].$badan['gross_amount'].self::KUNCI,
        );
    }

    /** @param  array<string, mixed>  $ganti */
    private function kirim(array $ganti = [])
    {
        $badan = $this->badan($ganti);
        $badan['signature_key'] = $this->tandaTangan($badan);

        return $this->postJson('/pembayaran/notifikasi', $badan);
    }

    // ---------- Skenario #2: pembayaran berhasil ----------

    public function test_settlement_melunasi_pembayaran_dan_menurunkan_stok(): void
    {
        ['pesanan' => $pesanan, 'pembayaran' => $pembayaran, 'varian' => $varian] = $this->pesanan();

        $this->kirim()
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'lunas')
            ->assertJsonPath('data.status_pesanan', 'diproses');

        $pembayaran->refresh();
        $this->assertSame('lunas', $pembayaran->status);
        $this->assertSame('trx-uji-1', $pembayaran->transaction_id);
        $this->assertSame('bank_transfer', $pembayaran->metode);
        $this->assertNotNull($pembayaran->dibayar_pada);

        $this->assertSame('diproses', $pesanan->refresh()->status);

        $varian->refresh();
        $this->assertSame(8, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);

        $this->assertSame(1, Notification::where('user_id', $this->user->id)->count());
        Mail::assertSent(PembayaranMail::class, 1);
    }

    /**
     * Uang bisa masuk setelah scheduler membatalkan pesanan. Pesanannya tetap
     * batal (Lampiran B: Dibatalkan = status akhir), dan kabar tidak boleh
     * mengklaim pesanan sedang diproses.
     */
    public function test_settlement_setelah_dibatalkan_tetap_batal_dan_pesannya_jujur(): void
    {
        ['pesanan' => $pesanan, 'pembayaran' => $pembayaran, 'varian' => $varian] = $this->pesanan([
            'kadaluwarsa_pada' => now()->subMinute(),
        ]);

        // Scheduler membatalkan lebih dulu; notifikasi gateway menyusul terlambat.
        $this->artisan('orders:kedaluwarsa')->assertSuccessful();
        $this->assertSame('dibatalkan', $pesanan->refresh()->status);

        $this->kirim()
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'lunas')
            ->assertJsonPath('data.status_pesanan', 'dibatalkan');

        $this->assertSame('lunas', $pembayaran->refresh()->status);
        $this->assertSame('dibatalkan', $pesanan->refresh()->status);

        // Reservasi sudah dilepas scheduler; jangan dikurangi lagi.
        $varian->refresh();
        $this->assertSame(10, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);

        $kabar = Notification::where('user_id', $this->user->id)->latest('id')->firstOrFail();
        $this->assertSame('Pembayaran diterima', $kabar->judul);
        $this->assertStringNotContainsString('sedang diproses', $kabar->pesan);
        $this->assertStringContainsString('sudah dibatalkan', $kabar->pesan);
    }

    /** FR-SYS-01 #7: notifikasi yang sama tidak diterapkan dua kali. */
    public function test_notifikasi_ganda_diproses_sekali(): void
    {
        ['varian' => $varian] = $this->pesanan();

        $this->kirim()->assertOk();
        $this->kirim()->assertOk();

        $varian->refresh();
        $this->assertSame(8, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);
        $this->assertSame(1, Notification::where('user_id', $this->user->id)->count());
        Mail::assertSent(PembayaranMail::class, 1);
    }

    public function test_capture_dengan_fraud_challenge_belum_lunas(): void
    {
        ['pembayaran' => $pembayaran, 'varian' => $varian] = $this->pesanan();

        $this->kirim(['transaction_status' => 'capture', 'fraud_status' => 'challenge'])
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'belum_dibayar');

        $this->assertSame('belum_dibayar', $pembayaran->refresh()->status);
        $this->assertSame(10, $varian->refresh()->stok_fisik);
        $this->assertSame(0, Notification::count());
    }

    public function test_pending_tidak_mengubah_apa_pun(): void
    {
        ['pesanan' => $pesanan, 'pembayaran' => $pembayaran] = $this->pesanan();

        $this->kirim(['transaction_status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'belum_dibayar');

        $this->assertSame('belum_dibayar', $pembayaran->refresh()->status);
        $this->assertSame('menunggu_pembayaran', $pesanan->refresh()->status);
        $this->assertSame(0, Notification::count());
    }

    // ---------- Skenario #3: pembayaran gagal ----------

    public function test_deny_menjadikan_pembayaran_gagal_tanpa_notifikasi(): void
    {
        ['pesanan' => $pesanan, 'pembayaran' => $pembayaran, 'varian' => $varian] = $this->pesanan();

        $this->kirim(['transaction_status' => 'deny'])
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'gagal');

        $this->assertSame('gagal', $pembayaran->refresh()->status);
        $this->assertSame('menunggu_pembayaran', $pesanan->refresh()->status);
        // Reservasi tetap dipegang supaya User dapat mencoba metode lain.
        $this->assertSame(2, $varian->refresh()->stok_direservasi);
        $this->assertSame(0, Notification::count());
        Mail::assertNothingSent();
    }

    // ---------- Skenario #4: kedaluwarsa ----------

    public function test_expire_membatalkan_pesanan_dan_memberi_kabar(): void
    {
        ['pesanan' => $pesanan, 'pembayaran' => $pembayaran, 'varian' => $varian] = $this->pesanan();

        $this->kirim(['transaction_status' => 'expire'])
            ->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'kedaluwarsa')
            ->assertJsonPath('data.status_pesanan', 'dibatalkan');

        $this->assertSame('kedaluwarsa', $pembayaran->refresh()->status);
        $this->assertSame('dibatalkan', $pesanan->refresh()->status);

        $varian->refresh();
        $this->assertSame(10, $varian->stok_fisik);
        $this->assertSame(0, $varian->stok_direservasi);

        $this->assertSame(1, Notification::where('user_id', $this->user->id)->count());
        Mail::assertSent(PembayaranMail::class, 1);
    }

    // ---------- Keaslian notifikasi (KM-02) ----------

    public function test_tanda_tangan_salah_ditolak_tanpa_perubahan(): void
    {
        ['pembayaran' => $pembayaran] = $this->pesanan();

        $this->postJson('/pembayaran/notifikasi', array_merge($this->badan(), [
            'signature_key' => str_repeat('a', 128),
        ]))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tanda tangan notifikasi tidak sah');

        $this->assertSame('belum_dibayar', $pembayaran->refresh()->status);
        $this->assertSame(0, Notification::count());
    }

    public function test_tanda_tangan_dari_kunci_lain_ditolak(): void
    {
        ['pembayaran' => $pembayaran] = $this->pesanan();
        $badan = $this->badan();
        $badan['signature_key'] = hash('sha512', 'order-lain'.$badan['status_code'].$badan['gross_amount'].self::KUNCI);

        $this->postJson('/pembayaran/notifikasi', $badan)->assertStatus(403);

        $this->assertSame('belum_dibayar', $pembayaran->refresh()->status);
    }

    public function test_tanpa_tanda_tangan_ditolak_validasi(): void
    {
        $this->pesanan();

        $this->postJson('/pembayaran/notifikasi', $this->badan())
            ->assertStatus(422)
            ->assertJsonValidationErrors('signature_key');
    }

    public function test_pesanan_tidak_dikenal_dijawab_404(): void
    {
        $this->pesanan();

        $this->kirim(['order_id' => 'INV-TIDAK-ADA'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Pesanan tidak ditemukan');
    }

    public function test_nominal_tidak_cocok_ditolak(): void
    {
        ['pembayaran' => $pembayaran] = $this->pesanan();

        $this->kirim(['gross_amount' => '1000.00'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nominal pembayaran tidak cocok dengan pesanan');

        $this->assertSame('belum_dibayar', $pembayaran->refresh()->status);
    }

    public function test_pesanan_tanpa_tagihan_dijawab_422(): void
    {
        ['pembayaran' => $pembayaran] = $this->pesanan();
        $pembayaran->delete();

        $this->kirim()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pembayaran untuk pesanan ini belum dibuat');
    }

    /** Webhook datang dari Midtrans, bukan dari browser: tanpa login dan tanpa CSRF. */
    public function test_tidak_membutuhkan_login_maupun_token_csrf(): void
    {
        ['pembayaran' => $pembayaran] = $this->pesanan();

        $this->postJson('/pembayaran/notifikasi', array_merge($this->badan(), [
            'signature_key' => $this->tandaTangan($this->badan()),
        ]))->assertOk();

        $this->assertSame('lunas', $pembayaran->refresh()->status);
    }
}
