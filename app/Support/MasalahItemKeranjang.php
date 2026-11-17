<?php

namespace App\Support;

use App\Models\CartItem;

/**
 * FR-CART-02 #2/#3 dan FR-CKOT-01 #7 memakai kalimat yang sama, jadi label
 * masalah item disimpan satu tempat: keranjang dan checkout tidak bisa
 * menampilkan teks berbeda untuk keadaan yang sama.
 */
final class MasalahItemKeranjang
{
    public const PRODUK_TIDAK_TERSEDIA = 'Produk tidak tersedia';

    public const STOK = 'Stok tidak mencukupi';

    /** Teks dokumen FR-PROD-03 #2, dipakai juga sebagai label keranjang. */
    public static function moq(int $moq): string
    {
        return "Minimal pemesanan untuk varian ini adalah {$moq} pcs";
    }

    /**
     * Satu label saja per item; urutannya produk nonaktif, MOQ, lalu stok.
     */
    public static function label(CartItem $item): ?string
    {
        $varian = $item->variant;

        if (! $varian->product->is_aktif) {
            return self::PRODUK_TIDAK_TERSEDIA;
        }

        if ($item->quantity < $varian->moq) {
            return self::moq($varian->moq);
        }

        // Stok custom ikut membatasi: srs.md:116 menyatakan produk jadi dan custom
        // memakai satuan stok yang sama, yaitu varian dasar (blanko).
        if ($item->quantity > $varian->stok_tersedia) {
            return self::STOK;
        }

        return null;
    }
}
