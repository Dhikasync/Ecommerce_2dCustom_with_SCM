<?php

namespace App\Console\Commands;

use App\Exceptions\Profil\RajaOngkirException;
use App\Models\Courier;
use App\Models\ShopSetting;
use App\Services\Profil\RajaOngkir;
use Illuminate\Console\Command;

/**
 * FR-CKOT-04 #2, srs.md:228: layanan kurir berasal dari RajaOngkir, bukan daftar
 * tulisan tangan. Rute contoh asal-ke-asal memberi tarif termurah.
 */
class SinkronLayananKurir extends Command
{
    /** Kontrak statis RajaOngkir: kode kurir tidak disediakan lewat endpoint. */
    private const KURIR_DIDUKUNG = [
        'anteraja', 'dse', 'ide', 'jne', 'jnt', 'lion', 'ncs', 'ninja', 'pos',
        'rex', 'rpx', 'sap', 'sentral', 'sicepat', 'star', 'tiki', 'wahana',
    ];

    /** Berat tidak mengubah daftar layanan, hanya tarifnya. */
    private const BERAT_CONTOH_GRAM = 1000;

    protected $signature = 'rajaongkir:sinkron-layanan
        {--asal= : Kode lokasi asal; default dari pengaturan pengiriman}
        {--kurir= : Kode kurir dipisah koma; default semua yang didukung}';

    protected $description = 'Menyimpan layanan kurir dari RajaOngkir sebagai data awal Nonaktif';

    public function handle(RajaOngkir $rajaOngkir): int
    {
        $asal = $this->option('asal') ?: ShopSetting::find(1)?->kode_lokasi;

        if (! is_string($asal) || $asal === '') {
            $this->error('Alamat asal belum diisi; lengkapi pengaturan pengiriman atau pakai --asal.');

            return self::FAILURE;
        }

        $gagal = 0;

        foreach ($this->daftarKurir() as $kode) {
            try {
                $layanan = $rajaOngkir->hitungOngkir($asal, $asal, self::BERAT_CONTOH_GRAM, $kode);
            } catch (RajaOngkirException $e) {
                $this->error("{$kode}: gagal — {$e->getMessage()}");
                $gagal++;

                continue;
            }

            $baru = 0;

            foreach ($layanan as $baris) {
                $courier = Courier::firstOrCreate(
                    ['kode' => $baris['kode_kurir'], 'layanan' => $baris['layanan']],
                    ['nama' => $baris['nama_kurir'], 'is_aktif' => false],
                );

                if ($courier->wasRecentlyCreated) {
                    $baru++;
                }
            }

            $this->line("{$kode}: {$baru} baru dari ".count($layanan).' layanan');
        }

        return $gagal === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function daftarKurir(): array
    {
        $opsi = $this->option('kurir');

        if (! is_string($opsi) || trim($opsi) === '') {
            return self::KURIR_DIDUKUNG;
        }

        return array_values(array_filter(array_map('trim', explode(',', $opsi))));
    }
}
