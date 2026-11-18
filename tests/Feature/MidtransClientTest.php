<?php

namespace Tests\Feature;

use App\Exceptions\Pembayaran\MidtransException;
use App\Services\Pembayaran\Midtrans;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SI-01, KM-02. Pembayaran harus benar-benar dikirim ke Midtrans, bukan
 * disimulasikan: yang diuji di sini bentuk permintaan dan pembacaan balasannya.
 */
class MidtransClientTest extends TestCase
{
    private const SNAP_SANDBOX = 'https://app.sandbox.midtrans.com/snap/v1/transactions';

    private const API_SANDBOX = 'https://api.sandbox.midtrans.com/v2/INV-UJI-1/status';

    private const KUNCI = 'SB-Mid-server-kunci-uji';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => self::KUNCI,
            'services.midtrans.client_key' => 'SB-Mid-client-kunci-uji',
            'services.midtrans.is_production' => false,
        ]);
    }

    private function klien(): Midtrans
    {
        return $this->app->make(Midtrans::class);
    }

    /** Bentuk respons disalin dari dokumentasi Snap: token dan redirect_url. */
    private function responsSnap(): array
    {
        return [
            'token' => 'token-snap-uji',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/token-snap-uji',
        ];
    }

    /** Bentuk respons disalin dari API status Midtrans. */
    private function responsStatus(array $ubah = []): array
    {
        return array_merge([
            'status_code' => '200',
            'status_message' => 'Success, transaction is found',
            'transaction_id' => 'b1a2c3d4-uji',
            'order_id' => 'INV-UJI-1',
            'gross_amount' => '48000.00',
            'payment_type' => 'bank_transfer',
            'transaction_time' => '2026-11-17 09:14:00',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'signature_key' => 'tanda-tangan-uji',
        ], $ubah);
    }

    private function pelanggan(): array
    {
        return [
            'nama' => 'Siti Aminah',
            'email' => 'siti@example.test',
            'telepon' => '081200000000',
        ];
    }

    // ---------- Membuat transaksi Snap (FR-PAYM-01 #1) ----------

    public function test_transaksi_snap_dikirim_ke_midtrans_dengan_kunci_server(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 09:00:00'));
        Http::fake([self::SNAP_SANDBOX => Http::response($this->responsSnap(), 201)]);

        $hasil = $this->klien()->buatTransaksi(
            'INV-UJI-1',
            48000,
            CarbonImmutable::parse('2026-11-18 09:30:00'),
            $this->pelanggan(),
        );

        $this->assertSame('token-snap-uji', $hasil['snap_token']);
        $this->assertSame(
            'https://app.sandbox.midtrans.com/snap/v4/redirection/token-snap-uji',
            $hasil['redirect_url'],
        );

        Http::assertSent(function (Request $permintaan) {
            $badan = $permintaan->data();

            return $permintaan->method() === 'POST'
                && $permintaan->url() === self::SNAP_SANDBOX
                && $permintaan->hasHeader('Authorization', 'Basic '.base64_encode(self::KUNCI.':'))
                && $badan['transaction_details'] === ['order_id' => 'INV-UJI-1', 'gross_amount' => 48000]
                && $badan['customer_details']['first_name'] === 'Siti Aminah'
                && $badan['customer_details']['email'] === 'siti@example.test'
                && $badan['customer_details']['phone'] === '081200000000'
                && $badan['expiry'] === [
                    'start_time' => '2026-11-18 09:00:00 +0700',
                    'unit' => 'minutes',
                    'duration' => 30,
                ];
        });
    }

    /** srs.md:317: kunci server hanya di server, tidak boleh ikut ke badan/URL. */
    public function test_kunci_server_tidak_ikut_di_url_maupun_badan_permintaan(): void
    {
        Http::fake([self::SNAP_SANDBOX => Http::response($this->responsSnap(), 201)]);

        $this->klien()->buatTransaksi(
            'INV-UJI-1',
            48000,
            CarbonImmutable::now()->addHour(),
            $this->pelanggan(),
        );

        Http::assertSent(fn (Request $permintaan) => ! str_contains($permintaan->url(), self::KUNCI)
            && ! str_contains($permintaan->body(), self::KUNCI));
    }

    /** SI-01: sandbox saat pengembangan, host produksi hanya bila dimatikan sengaja. */
    public function test_host_produksi_dipakai_hanya_saat_is_production_true(): void
    {
        config(['services.midtrans.is_production' => true]);
        Http::fake(['https://app.midtrans.com/snap/v1/transactions' => Http::response($this->responsSnap(), 201)]);

        $this->klien()->buatTransaksi(
            'INV-UJI-1',
            48000,
            CarbonImmutable::now()->addHour(),
            $this->pelanggan(),
        );

        Http::assertSent(fn (Request $permintaan) => $permintaan->url() === 'https://app.midtrans.com/snap/v1/transactions');
    }

    /** Membuat transaksi Snap tidak idempoten: diulang berarti dua transaksi. */
    public function test_kesalahan_snap_tidak_diulang(): void
    {
        Http::fake([self::SNAP_SANDBOX => Http::response([
            'error_messages' => ['Access denied due to unauthorized transaction, please check client or server key'],
        ], 401)]);

        try {
            $this->klien()->buatTransaksi('INV-UJI-1', 48000, CarbonImmutable::now()->addHour(), $this->pelanggan());
            $this->fail('Seharusnya melempar MidtransException');
        } catch (MidtransException $e) {
            $this->assertStringContainsString('Access denied', $e->getMessage());
            $this->assertStringContainsString('401', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    /** Batas bayar sudah lewat: jangan buat transaksi yang pasti kedaluwarsa. */
    public function test_batas_bayar_yang_sudah_lewat_tidak_dikirim_ke_midtrans(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00'));
        Http::fake();

        $this->expectException(MidtransException::class);

        $this->klien()->buatTransaksi(
            'INV-UJI-1',
            48000,
            CarbonImmutable::parse('2026-11-18 09:30:00'),
            $this->pelanggan(),
        );
    }

    // ---------- Cek status (FR-PAYM-01 #5) ----------

    public function test_status_dibaca_langsung_dari_api_midtrans(): void
    {
        Http::fake([self::API_SANDBOX => Http::response($this->responsStatus(), 200)]);

        $hasil = $this->klien()->status('INV-UJI-1');

        $this->assertSame('settlement', $hasil['transaction_status']);
        $this->assertSame('accept', $hasil['fraud_status']);
        $this->assertSame('bank_transfer', $hasil['metode']);
        $this->assertSame('b1a2c3d4-uji', $hasil['transaction_id']);
        $this->assertSame(48000, $hasil['jumlah']);
        $this->assertSame('tanda-tangan-uji', $hasil['signature_key']);

        Http::assertSent(fn (Request $permintaan) => $permintaan->method() === 'GET'
            && $permintaan->url() === self::API_SANDBOX
            && $permintaan->hasHeader('Authorization', 'Basic '.base64_encode(self::KUNCI.':')));
    }

    /** Transaksi yang belum pernah dibuat di Midtrans bukan gangguan: pesannya dibaca apa adanya. */
    public function test_status_yang_tidak_ada_dilempar_dengan_pesan_midtrans(): void
    {
        Http::fake([self::API_SANDBOX => Http::response([
            'status_code' => '404',
            'status_message' => "Transaction doesn't exist.",
        ], 404)]);

        try {
            $this->klien()->status('INV-UJI-1');
            $this->fail('Seharusnya melempar MidtransException');
        } catch (MidtransException $e) {
            $this->assertStringContainsString("Transaction doesn't exist.", $e->getMessage());
        }
    }

    /** Membaca status idempoten, jadi gangguan 5xx boleh diulang sekali. */
    public function test_cek_status_diulang_sekali_saat_midtrans_mengalami_gangguan(): void
    {
        Http::fake([self::API_SANDBOX => Http::sequence()
            ->pushStatus(502)
            ->push($this->responsStatus(), 200)]);

        $this->assertSame('settlement', $this->klien()->status('INV-UJI-1')['transaction_status']);
        Http::assertSentCount(2);
    }

    /** Kunci ditolak bukan gangguan: pesannya harus menunjuk apa yang diperiksa. */
    public function test_kunci_yang_ditolak_menyebut_pemeriksaan_kunci_dan_mode(): void
    {
        Http::fake([self::API_SANDBOX => Http::response('', 401)]);

        try {
            $this->klien()->status('INV-UJI-1');
            $this->fail('Seharusnya melempar MidtransException');
        } catch (MidtransException $e) {
            $this->assertStringContainsString('MIDTRANS_SERVER_KEY', $e->getMessage());
            $this->assertStringContainsString('MIDTRANS_IS_PRODUCTION', $e->getMessage());
        }
    }

    public function test_server_key_kosong_tidak_memanggil_midtrans(): void
    {
        config(['services.midtrans.server_key' => '']);
        Http::fake();

        try {
            $this->klien()->status('INV-UJI-1');
            $this->fail('Seharusnya melempar MidtransException');
        } catch (MidtransException $e) {
            $this->assertStringContainsString('MIDTRANS_SERVER_KEY belum diisi', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}
