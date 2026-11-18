<?php

namespace App\Services\Pembayaran;

use App\Exceptions\Pembayaran\MidtransException;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * SI-01, KM-02: satu-satunya pintu aplikasi ke Midtrans. Kunci server hanya
 * hidup di sini dan di config; yang boleh sampai ke browser hanya client_key.
 */
class Midtrans
{
    private const BATAS_WAKTU_DETIK = 8;

    /** Lebih pendek dari timeout, agar server yang menggantung tidak menahan request. */
    private const BATAS_KONEKSI_DETIK = 4;

    /** Hanya untuk membaca status: permintaan GET boleh diulang tanpa efek ganda. */
    private const PERCOBAAN_MAKS = 2;

    private const JEDA_ULANG_MILIDETIK = 200;

    /** `expiry.start_time` Midtrans memakai WIB (NFR-LOC-01). */
    private const ZONA_WAKTU = 'Asia/Jakarta';

    public function __construct(
        private readonly string $serverKey,
        private readonly bool $produksi,
    ) {}

    /**
     * FR-PAYM-01 #1, UC-31. Batas bayar ditentukan `orders.kadaluwarsa_pada`,
     * jadi Midtrans diminta kedaluwarsa pada saat yang sama.
     *
     * @param  array{nama: string, email: string, telepon: string}  $pelanggan
     * @return array{snap_token: string, redirect_url: string}
     *
     * @throws MidtransException
     */
    public function buatTransaksi(
        string $nomorPesanan,
        int $jumlah,
        CarbonInterface $berlakuSampai,
        array $pelanggan,
    ): array {
        $detik = $berlakuSampai->getTimestamp() - now()->getTimestamp();

        if ($detik <= 0) {
            throw new MidtransException('Batas pembayaran pesanan sudah lewat');
        }

        $response = $this->peminta($this->tautanSnap())->post('/snap/v1/transactions', [
            'transaction_details' => [
                'order_id' => $nomorPesanan,
                'gross_amount' => $jumlah,
            ],
            'customer_details' => [
                'first_name' => $pelanggan['nama'],
                'email' => $pelanggan['email'],
                'phone' => $pelanggan['telepon'],
            ],
            'expiry' => [
                'start_time' => now(self::ZONA_WAKTU)->format('Y-m-d H:i:s O'),
                'unit' => 'minutes',
                'duration' => (int) ceil($detik / 60),
            ],
        ]);

        $this->pastikanBerhasil($response);

        $token = $response->json('token');
        $tautan = $response->json('redirect_url');

        if (! is_string($token) || $token === '' || ! is_string($tautan) || $tautan === '') {
            throw new MidtransException('Respons Snap Midtrans tidak memuat token');
        }

        return ['snap_token' => $token, 'redirect_url' => $tautan];
    }

    /**
     * FR-PAYM-01 #5, UC-33. Ditanya langsung ke Midtrans, bukan dibaca dari
     * database, karena tujuannya justru menutup notifikasi yang terlambat.
     *
     * @return array{order_id: string, transaction_id: ?string, metode: ?string, jumlah: int, transaction_status: string, fraud_status: ?string, status_code: string, signature_key: ?string}
     *
     * @throws MidtransException
     */
    public function status(string $nomorPesanan): array
    {
        $response = $this->kirim(
            fn (PendingRequest $peminta) => $peminta->get('/v2/'.rawurlencode($nomorPesanan).'/status'),
        );

        $this->pastikanBerhasil($response);

        $badan = $response->json();

        if (! is_array($badan)) {
            throw new MidtransException('Respons status Midtrans tidak dikenali');
        }

        $status = $badan['transaction_status'] ?? null;

        if (! is_string($status) || $status === '') {
            throw new MidtransException('Respons status Midtrans tidak memuat status transaksi');
        }

        return [
            'order_id' => (string) ($badan['order_id'] ?? $nomorPesanan),
            'transaction_id' => isset($badan['transaction_id']) ? (string) $badan['transaction_id'] : null,
            'metode' => isset($badan['payment_type']) ? (string) $badan['payment_type'] : null,
            // Uang tidak boleh ditebak: bentuk "48000.00" diperiksa sebelum jadi rupiah.
            'jumlah' => JumlahMidtrans::baca($badan['gross_amount'] ?? null),
            'transaction_status' => $status,
            'fraud_status' => isset($badan['fraud_status']) ? (string) $badan['fraud_status'] : null,
            'status_code' => (string) ($badan['status_code'] ?? $response->status()),
            'signature_key' => isset($badan['signature_key']) ? (string) $badan['signature_key'] : null,
        ];
    }

    /**
     * KM-02, srs.md:297: keaslian notifikasi dibuktikan dengan
     * sha512(order_id + status_code + gross_amount + server_key). Tanpa ini,
     * siapa pun yang tahu URL webhook bisa mengaku sebagai Midtrans.
     *
     * @param  array<string, mixed>  $badan
     */
    public function tandaTanganSah(array $badan): bool
    {
        if ($this->serverKey === '') {
            return false;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $wajib) {
            if (! isset($badan[$wajib]) || ! is_scalar($badan[$wajib])) {
                return false;
            }
        }

        $diharapkan = hash('sha512', sprintf(
            '%s%s%s%s',
            $badan['order_id'],
            $badan['status_code'],
            $badan['gross_amount'],
            $this->serverKey,
        ));

        return hash_equals($diharapkan, (string) $badan['signature_key']);
    }

    /**
     * Satu pintu keluar HTTP untuk permintaan yang idempoten. Gangguan koneksi
     * dan 5xx diulang sekali; 4xx tidak karena kunci salah bukan gangguan.
     *
     * @param  Closure(PendingRequest): Response  $perintah
     *
     * @throws MidtransException
     */
    private function kirim(Closure $perintah): Response
    {
        $percobaan = 0;

        while (true) {
            $percobaan++;

            try {
                $response = $perintah($this->peminta($this->tautanApi()));
            } catch (ConnectionException $e) {
                if ($percobaan >= self::PERCOBAAN_MAKS) {
                    throw new MidtransException('Midtrans tidak dapat dihubungi', previous: $e);
                }

                usleep(self::JEDA_ULANG_MILIDETIK * 1000);

                continue;
            }

            if (! $response->serverError() || $percobaan >= self::PERCOBAAN_MAKS) {
                return $response;
            }

            usleep(self::JEDA_ULANG_MILIDETIK * 1000);
        }
    }

    private function peminta(string $tautanDasar): PendingRequest
    {
        if ($this->serverKey === '') {
            throw new MidtransException('MIDTRANS_SERVER_KEY belum diisi');
        }

        return Http::baseUrl($tautanDasar)
            ->withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(self::BATAS_WAKTU_DETIK)
            ->connectTimeout(self::BATAS_KONEKSI_DETIK);
    }

    /** SI-01: sandbox saat pengembangan dan pengujian, produksi hanya bila disengaja. */
    private function tautanSnap(): string
    {
        return $this->produksi
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }

    private function tautanApi(): string
    {
        return $this->produksi
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    /** @throws MidtransException */
    private function pastikanBerhasil(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        // Snap memakai `error_messages[]`, Core API memakai `status_message`.
        $pesan = $response->json('error_messages.0') ?? $response->json('status_message');

        // Midtrans membalas 401 dengan badan kosong: arahkan ke penyebabnya.
        $petunjuk = in_array($response->status(), [401, 403], true)
            ? '; periksa MIDTRANS_SERVER_KEY dan MIDTRANS_IS_PRODUCTION'
            : '';

        throw new MidtransException(sprintf(
            'Midtrans membalas status %d: %s%s',
            $response->status(),
            is_string($pesan) && $pesan !== '' ? $pesan : 'tanpa pesan',
            $petunjuk,
        ));
    }
}
