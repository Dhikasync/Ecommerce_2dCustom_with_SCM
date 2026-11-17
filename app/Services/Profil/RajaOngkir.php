<?php

namespace App\Services\Profil;

use App\Exceptions\Profil\RajaOngkirException;
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
        $peminta = $this->peminta();

        try {
            $response = $peminta->get('/destination/domestic-destination', [
                'search' => $kataKunci,
            ]);
        } catch (ConnectionException $e) {
            throw new RajaOngkirException('RajaOngkir tidak dapat dihubungi', previous: $e);
        }

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
        $peminta = $this->peminta();

        try {
            $response = $peminta->asForm()->post('/calculate/domestic-cost', [
                'origin' => $asal,
                'destination' => $tujuan,
                'weight' => $beratGram,
                'courier' => $kurir,
                'filter' => 'lowest',
            ]);
        } catch (ConnectionException $e) {
            throw new RajaOngkirException('RajaOngkir tidak dapat dihubungi', previous: $e);
        }

        $data = $this->bacaData($response, 'Respons RajaOngkir tidak memuat data tarif');
        $hasil = [];

        foreach ($data as $baris) {
            if (! is_array($baris) || ! isset($baris['code'], $baris['service'], $baris['cost'])) {
                throw new RajaOngkirException('Bentuk data tarif tidak dikenali');
            }

            $hasil[] = [
                'kode_kurir' => (string) $baris['code'],
                'nama_kurir' => (string) ($baris['name'] ?? $baris['code']),
                'layanan' => (string) $baris['service'],
                'deskripsi' => (string) ($baris['description'] ?? ''),
                'biaya' => (int) $baris['cost'],
                'etd' => (string) ($baris['etd'] ?? ''),
            ];
        }

        return $hasil;
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
