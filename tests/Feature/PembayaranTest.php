<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FR-PAYM-01 #1, UC-31, user-story.md:524. User membuka halaman pembayaran,
 * sistem membuat transaksi Midtrans dan menampilkan pilihan metode dari Snap.
 */
class PembayaranTest extends TestCase
{
    use RefreshDatabase;

    private const SNAP = 'https://app.sandbox.midtrans.com/snap/v1/transactions';

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

    /** @param  array<string, mixed>  $ganti */
    private function pesanan(array $ganti = []): Order
    {
        return Order::create(array_merge([
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
        ], $ganti));
    }

    private function palsukanSnap(): void
    {
        // Bentuk respons disalin dari dokumentasi Snap: token + redirect_url.
        Http::fake([self::SNAP => Http::response([
            'token' => 'token-snap-uji',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/token-snap-uji',
        ], 201)]);
    }

    private function bukaPembayaran(string $nomor = 'INV-UJI-1')
    {
        return $this->actingAs($this->user)->postJson('/pesanan/'.$nomor.'/pembayaran');
    }

    // ---------- Skenario #1: memilih metode pembayaran ----------

    public function test_membuka_pembayaran_membuat_transaksi_snap_untuk_total_pesanan(): void
    {
        $pesanan = $this->pesanan();
        $this->palsukanSnap();

        $this->bukaPembayaran()
            ->assertCreated()
            ->assertJsonPath('data.snap_token', 'token-snap-uji')
            ->assertJsonPath('data.redirect_url', 'https://app.sandbox.midtrans.com/snap/v4/redirection/token-snap-uji')
            ->assertJsonPath('data.nomor_pesanan', 'INV-UJI-1');

        // Nominal yang ditagih gateway adalah `orders.total`, bukan angka dari browser.
        Http::assertSent(function (HttpRequest $permintaan) {
            $badan = $permintaan->data();

            return $permintaan->url() === self::SNAP
                && $badan['transaction_details'] === [
                    'order_id' => 'INV-UJI-1',
                    'gross_amount' => 48000,
                ]
                && $badan['customer_details']['first_name'] === 'Andhi'
                && $badan['customer_details']['phone'] === '+628123456789'
                && $badan['customer_details']['email'] === $this->user->email;
        });

        $pembayaran = Payment::sole();
        $this->assertSame($pesanan->id, $pembayaran->order_id);
        $this->assertSame('belum_dibayar', $pembayaran->status);
        $this->assertSame(48000, $pembayaran->jumlah);
        $this->assertSame('token-snap-uji', $pembayaran->snap_token);
        $this->assertTrue($pembayaran->kadaluwarsa_pada->equalTo($pesanan->kadaluwarsa_pada));
    }

    /** Membuka ulang halaman tidak boleh membuat transaksi kedua di Midtrans. */
    public function test_membuka_pembayaran_dua_kali_memakai_token_yang_sama(): void
    {
        $this->pesanan();
        $this->palsukanSnap();

        $this->bukaPembayaran()->assertCreated();

        $this->bukaPembayaran()
            ->assertOk()
            ->assertJsonPath('data.snap_token', 'token-snap-uji');

        $this->assertSame(1, Payment::count());
        Http::assertSentCount(1);
    }

    /** Setelah percobaan pertama gagal, User boleh memilih metode lain (skenario #3). */
    public function test_percobaan_baru_setelah_gagal_membuat_baris_pembayaran_baru(): void
    {
        $pesanan = $this->pesanan();
        Payment::create([
            'order_id' => $pesanan->id,
            'jumlah' => 48000,
            'status' => 'gagal',
        ]);

        $this->palsukanSnap();

        $this->bukaPembayaran()->assertCreated();

        $this->assertSame(2, Payment::count());
        $this->assertSame('belum_dibayar', Payment::latest('id')->first()->status);
    }

    // ---------- Penjagaan pesanan yang tidak boleh dibayar ----------

    public function test_pesanan_milik_user_lain_dijawab_404(): void
    {
        $this->pesanan(['user_id' => User::factory()->create()->id]);
        Http::fake();

        $this->bukaPembayaran()
            ->assertStatus(404)
            ->assertJsonPath('message', 'Pesanan tidak ditemukan');

        Http::assertNothingSent();
    }

    /** FR-PAYM-02: pesanan custom dibayar setelah desain disetujui Admin. */
    public function test_pesanan_custom_yang_belum_disetujui_tidak_dapat_dibayar(): void
    {
        $this->pesanan(['status' => 'menunggu_validasi', 'kadaluwarsa_pada' => null]);
        Http::fake();

        $this->bukaPembayaran()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pembayaran hanya dapat dilakukan setelah desain disetujui');

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_pesanan_yang_sudah_dibayar_tidak_membuat_transaksi_baru(): void
    {
        $pesanan = $this->pesanan(['status' => 'diproses']);
        Payment::create([
            'order_id' => $pesanan->id,
            'jumlah' => 48000,
            'status' => 'lunas',
            'dibayar_pada' => now(),
        ]);
        Http::fake();

        $this->bukaPembayaran()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan ini sudah dibayar');

        Http::assertNothingSent();
    }

    public function test_pesanan_yang_sudah_dibatalkan_tidak_dapat_dibayar(): void
    {
        $this->pesanan(['status' => 'dibatalkan']);
        Http::fake();

        $this->bukaPembayaran()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan ini tidak dapat dibayar');

        Http::assertNothingSent();
    }

    /** FR-PAYM-01 #4: lewat 24 jam, pesanan menunggu pembatalan scheduler. */
    public function test_batas_bayar_yang_sudah_lewat_tidak_membuat_transaksi(): void
    {
        $this->pesanan(['kadaluwarsa_pada' => now()->subMinute()]);
        Http::fake();

        $this->bukaPembayaran()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Batas pembayaran pesanan sudah lewat');

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    // ---------- Gateway gagal ----------

    public function test_gangguan_midtrans_tidak_menyimpan_pembayaran_setengah_jadi(): void
    {
        $this->pesanan();
        Http::fake([self::SNAP => Http::response(['error_messages' => ['Internal server error']], 500)]);

        $this->bukaPembayaran()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pembayaran belum dapat diproses, silakan coba lagi');

        $this->assertSame(0, Payment::count());
    }

    public function test_tamu_tidak_dapat_membuka_pembayaran(): void
    {
        $this->pesanan();
        Http::fake();

        $this->postJson('/pesanan/INV-UJI-1/pembayaran')->assertStatus(401);

        Http::assertNothingSent();
    }

    /** Token Snap adalah kunci sesi pembayaran: jangan disimpan perantara. */
    public function test_respons_pembayaran_tidak_disimpan_cache(): void
    {
        $this->pesanan();
        $this->palsukanSnap();

        $respons = $this->bukaPembayaran();
        $respons->assertCreated();
        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));
    }
}
