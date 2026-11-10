<?php

namespace App\Services;

use App\Exceptions\RajaOngkirException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * SI-02: satu-satunya pintu aplikasi ke API RajaOngkir.
 *
 * Kunci API hanya dibaca di server. Batas waktu dan cara gagal di bawah ini
 * bukan aturan bisnis — SRS dan User Story tidak mengaturnya — jadi nilainya
 * dipilih di sini dan sengaja dicatat supaya mudah diubah.
 */
class RajaOngkir
{
    /** Batas menunggu respons penuh; pencarian lokasi dipakai saat User mengetik. */
    private const BATAS_WAKTU_DETIK = 8;

    /** Batas membuka koneksi, supaya server yang menggantung tidak menahan request. */
    private const BATAS_KONEKSI_DETIK = 4;

    public function __construct(
        private readonly string $kunci,
        private readonly string $baseUrl,
    ) {}

    /**
     * FR-PROF-01 #4, UC-08: cari kecamatan/kelurahan dari kata kunci.
     *
     * Hasil RajaOngkir berupa id kecamatan dan label panjang
     * ("AIRLANGGA, GUBENG, SURABAYA, JAWA TIMUR, 60286"); keduanya dipetakan
     * ke nama kolom yang dipakai aplikasi, kode_lokasi dan label_lokasi.
     *
     * @return list<array{kode_lokasi: string, label_lokasi: string}>
     *
     * @throws RajaOngkirException kalau layanan tidak bisa dipakai.
     */
    public function cariLokasi(string $kataKunci): array
    {
        $peminta = $this->peminta();

        try {
            $response = $peminta->get('/destination/domestic-destination', [
                'search' => $kataKunci,
            ]);
        } catch (ConnectionException $e) {
            throw new RajaOngkirException('RajaOngkir tidak dapat dihubungi', previous: $e);
        }

        return array_map(
            fn (array $lokasi) => [
                'kode_lokasi' => (string) $lokasi['id'],
                'label_lokasi' => (string) $lokasi['label'],
            ],
            $this->bacaData($response),
        );
    }

    private function peminta(): PendingRequest
    {
        if ($this->kunci === '') {
            throw new RajaOngkirException('RAJAONGKIR_API_KEY belum diisi');
        }

        return Http::baseUrl($this->tautanDasar())
            ->withHeaders(['key' => $this->kunci])
            ->acceptJson()
            ->timeout(self::BATAS_WAKTU_DETIK)
            ->connectTimeout(self::BATAS_KONEKSI_DETIK);
    }

    /**
     * Base URL di .env sering ditulis dengan garis miring di akhir. Laravel
     * sudah menormalkan saat menyusun URL, tetapi rtrim membuat bentuknya
     * tegas dan tetap aman kalau nanti base URL dipakai untuk tautan sendiri.
     */
    private function tautanDasar(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bacaData(Response $response): array
    {
        if (! $response->successful()) {
            throw new RajaOngkirException('RajaOngkir membalas status '.$response->status());
        }

        // Bentuk respons yang dipakai di sini disalin dari respons asli:
        // {"meta":{"status":"success",...},"data":[...]}. Kalau meta.status
        // tidak ada, keberhasilan dinilai dari data saja.
        $status = $response->json('meta.status');

        if ($status !== null && $status !== 'success') {
            throw new RajaOngkirException('RajaOngkir membalas status '.$status);
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RajaOngkirException('Respons RajaOngkir tidak memuat data lokasi');
        }

        foreach ($data as $lokasi) {
            if (! is_array($lokasi) || ! isset($lokasi['id'], $lokasi['label'])) {
                throw new RajaOngkirException('Bentuk data lokasi tidak dikenali');
            }
        }

        return $data;
    }
}
