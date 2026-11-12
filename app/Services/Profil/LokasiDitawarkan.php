<?php

namespace App\Services\Profil;

use Illuminate\Support\Facades\Cache;

/**
 * FR-PROF-01 #4/#5, srs.md:174: lokasi alamat harus berasal dari hasil
 * pencarian, tetapi cari dan simpan terjadi di dua permintaan berbeda.
 *
 * Cache, bukan tabel baru: SRS tidak memuat tabel lokasi. Label disimpan
 * supaya AlamatController tidak perlu percaya label kiriman klien.
 */
class LokasiDitawarkan
{
    /** Dokumen tidak mengatur masanya; 24 jam cukup untuk satu sesi isi alamat. */
    private const MASA_BERLAKU_DETIK = 24 * 60 * 60;

    private const AWALAN = 'lokasi-ditawarkan:';

    /**
     * @param  list<array{kode_lokasi: string, label_lokasi: string}>  $lokasi
     */
    public function catat(array $lokasi): void
    {
        foreach ($lokasi as $item) {
            Cache::put(
                self::AWALAN.$item['kode_lokasi'],
                $item['label_lokasi'],
                self::MASA_BERLAKU_DETIK,
            );
        }
    }

    /** Null bila kode tidak pernah ditawarkan atau tawarannya sudah lewat. */
    public function label(string $kodeLokasi): ?string
    {
        $label = Cache::get(self::AWALAN.$kodeLokasi);

        return is_string($label) ? $label : null;
    }
}
