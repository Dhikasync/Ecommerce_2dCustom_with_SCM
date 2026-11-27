<?php

namespace App\Services\Profil;

use App\Exceptions\Profil\RajaOngkirException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * SI-02: satu-satunya pintu aplikasi ke API RajaOngkir; kunci hanya di server.
 * Batas waktu bukan aturan dokumen, dipilih di sini agar mudah diubah.
 */
class RajaOngkir
{
    private const BATAS_WAKTU_DETIK = 8;

    /** Lebih pendek dari timeout, agar server yang menggantung tidak menahan request. */
    private const BATAS_KONEKSI_DETIK = 4;

    /** Satu kali ulang: gangguan sepersekian detik tidak perlu terlihat User. */
    private const PERCOBAAN_MAKS = 2;

    private const JEDA_ULANG_MILIDETIK = 200;

    public function __construct(
        private readonly string $kunci,
        private readonly string $baseUrl,
    ) {}

    /**
     * FR-PROF-01 #4, UC-08. Format RajaOngkir (`id`, `label`) dipetakan ke
     * nama kolom aplikasi.
     *
     * @return list<array{kode_lokasi: string, label_lokasi: string}>
     *
     * @throws RajaOngkirException
     */
    public function cariLokasi(string $kataKunci): array
    {
        $response = $this->kirim(
            fn (PendingRequest $peminta) => $peminta->get('/destination/domestic-destination', [
                'search' => $kataKunci,
            ]),
        );

        $data = $this->bacaData($response, 'Respons RajaOngkir tidak memuat data lokasi');

        foreach ($data as $lokasi) {
            if (! is_array($lokasi) || ! isset($lokasi['id'], $lokasi['label'])) {
                throw new RajaOngkirException('Bentuk data lokasi tidak dikenali');
            }
        }

        return array_map(
            fn (array $lokasi) => [
                'kode_lokasi' => (string) $lokasi['id'],
                'label_lokasi' => (string) $lokasi['label'],
            ],
            $data,
        );
    }

    /**
     * FR-CKOT-01 #3, UC-27. Tarif hanya bisa dihitung per rute, jadi daftar
     * `service` yang dikembalikan API adalah satu-satunya sumber layanan.
     *
     * @return list<array{kode_kurir: string, nama_kurir: string, layanan: string, deskripsi: string, biaya: int, etd: string}>
     *
     * @throws RajaOngkirException
     */
    public function hitungOngkir(string $asal, string $tujuan, int $beratGram, string $kurir): array
    {
        $response = $this->kirim(
            fn (PendingRequest $peminta) => $peminta->asForm()->post('/calculate/domestic-cost', [
                'origin' => $asal,
                'destination' => $tujuan,
                'weight' => $beratGram,
                'courier' => $kurir,
            ]),
        );

        $data = $this->bacaData($response, 'Respons RajaOngkir tidak memuat data tarif');
        $hasil = [];

        foreach ($data as $baris) {
            if (! is_array($baris) || ! isset($baris['code'], $baris['service'], $baris['cost'])) {
                throw new RajaOngkirException('Bentuk data tarif tidak dikenali');
            }

            // Respons pihak ketiga adalah input tidak tepercaya: angkanya menjadi
            // `orders.ongkir`, jadi bentuk dan nilainya diperiksa sebelum dipakai.
            $biaya = $baris['cost'];

            if (! is_int($biaya) && ! (is_string($biaya) && ctype_digit($biaya))) {
                throw new RajaOngkirException('Bentuk data tarif tidak dikenali');
            }

            // srs.md:520: ongkir tidak boleh diam-diam menjadi Rp 0.
            if ((int) $biaya <= 0) {
                throw new RajaOngkirException('Tarif tidak wajar');
            }

            $hasil[] = [
                'kode_kurir' => (string) $baris['code'],
                'nama_kurir' => (string) ($baris['name'] ?? $baris['code']),
                'layanan' => (string) $baris['service'],
                'deskripsi' => (string) ($baris['description'] ?? ''),
                'biaya' => (int) $biaya,
                'etd' => (string) ($baris['etd'] ?? ''),
            ];
        }

        return $hasil;
    }

    /** FR-ORDR-04 #3: status + manifest AWB; tidak ada URL dari endpoint ini. */
    public function lacakWaybill(string $awb, string $kurir, string $lastPhone): array
    {
        $response = $this->kirim(
            fn (PendingRequest $peminta) => $peminta->asForm()->post('/track/waybill', [
                'awb' => $awb,
                'courier' => $kurir,
                'last_phone_number' => $lastPhone,
            ]),
        );

        $data = $this->bacaData($response, 'Respons RajaOngkir tidak memuat data tracking');

        if (! isset($data['summary']) || ! is_array($data['summary'])) {
            throw new RajaOngkirException('Bentuk data tracking tidak dikenali');
        }

        return $data;
    }

    /**
     * Satu pintu keluar HTTP: gangguan sementara diulang sekali sebelum menyerah.
     * 4xx tidak diulang karena kunci atau permintaan yang salah bukan gangguan.
     *
     * @param  Closure(PendingRequest): Response  $perintah
     *
     * @throws RajaOngkirException
     */
    private function kirim(Closure $perintah): Response
    {
        $percobaan = 0;

        while (true) {
            $percobaan++;

            try {
                $response = $perintah($this->peminta());
            } catch (ConnectionException $e) {
                if ($percobaan >= self::PERCOBAAN_MAKS) {
                    throw new RajaOngkirException('RajaOngkir tidak dapat dihubungi', previous: $e);
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

    private function tautanDasar(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bacaData(Response $response, string $pesanKosong): array
    {
        if (! $response->successful()) {
            throw new RajaOngkirException('RajaOngkir membalas status '.$response->status());
        }

        // Bentuk respons disalin dari respons asli: {"meta":{"status":"success"},"data":[...]}.
        $status = $response->json('meta.status');

        if ($status !== null && $status !== 'success') {
            throw new RajaOngkirException('RajaOngkir membalas status '.$status);
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RajaOngkirException($pesanKosong);
        }

        return $data;
    }
}
