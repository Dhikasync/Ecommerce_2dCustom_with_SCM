<?php

namespace App\Services\Profil;

use Illuminate\Support\Facades\Cache;

/**
 * FR-PROF-01 #4, #5, srs.md:174: lokasi alamat harus berasal dari daftar hasil
 * pencarian, bukan diketik bebas, karena `kode_lokasi` dipakai menghitung
 * ongkir.
 *
 * Pencarian dan penyimpanan alamat terjadi pada dua permintaan berbeda, jadi
 * hasil pencarian harus diingat di antara keduanya. Yang disimpan adalah
 * tawaran server sendiri: kode lokasi beserta label yang benar-benar dikirim
 * ke browser. Saat alamat disimpan, hanya kode yang pernah ditawarkan yang
 * diterima, dan labelnya diambil dari ingatan itu — bukan dari kiriman klien.
 *
 * Cache dipakai, bukan tabel baru, karena SRS tidak memuat tabel lokasi dan
 * membuat tabel baru berarti mengarang struktur data di luar dokumen.
 */
class LokasiDitawarkan
{
    /**
     * Lama tawaran diingat. Dokumen tidak mengaturnya; 24 jam cukup untuk
     * satu sesi pengisian alamat, dan kode yang sudah tersimpan di alamat
     * tetap diterima lewat pengecualian di `AlamatController`.
     */
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

    /**
     * Label yang pernah ditawarkan untuk kode ini, atau null kalau kode itu
     * tidak pernah muncul di hasil pencarian (atau tawarannya sudah lewat).
     */
    public function label(string $kodeLokasi): ?string
    {
        $label = Cache::get(self::AWALAN.$kodeLokasi);

        return is_string($label) ? $label : null;
    }
}
