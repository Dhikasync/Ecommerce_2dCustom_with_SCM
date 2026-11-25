<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * FR-CDSN-03: satu tempat untuk token pemilik dan alamat folder berkas
 * unggahan. Dipakai `UnggahanController` (daftar sementara) dan
 * `DesainController` (pemindahan berkas saat draft disimpan).
 */
final class UnggahanDesain
{
    public const FOLDER = 'desain/unggahan';

    public const KUNCI_PEMILIK = 'desain_unggah_owner';

    /**
     * Token pemilik ada di sesi, bukan di akun: Guest boleh mengustomisasi
     * (user-story.md:834 hanya membatasi keranjang, checkout, dan pesanan),
     * dan token bertahan saat sesi di-regenerate ketika Guest login.
     */
    public static function folder(Request $request): string
    {
        $pemilik = $request->session()->get(self::KUNCI_PEMILIK);

        if (! is_string($pemilik) || $pemilik === '') {
            $pemilik = (string) Str::uuid();
            $request->session()->put(self::KUNCI_PEMILIK, $pemilik);
        }

        return self::FOLDER.'/'.$pemilik;
    }

    /** FR-CDSN-02: gambar milik desain yang sudah tersimpan, bukan lagi sementara. */
    public static function folderDesain(int $designID): string
    {
        return 'desain/gambar/'.$designID;
    }

    /** FR-CDSN-04 #3: gambar preview hasil render klien, satu folder per desain. */
    public static function folderPreview(int $designID): string
    {
        return 'desain/preview/'.$designID;
    }
}
