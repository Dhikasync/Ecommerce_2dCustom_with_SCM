<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Satu sumber URL berkas publik untuk kartu katalog, detail produk, panel admin,
 * dan item keranjang. Sebelumnya aturan yang sama disalin di tiga controller.
 */
final class GambarPublik
{
    private const DISK = 'public';

    public static function url(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        // `url()` ada di kontrak Cloud/FilesystemAdapter, bukan di kontrak
        // Filesystem yang dijanjikan facade Storage.
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);

        return $disk->url($path);
    }
}
