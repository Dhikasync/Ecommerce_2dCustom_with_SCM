<?php

namespace App\Exceptions\Checkout;

use RuntimeException;

/**
 * FR-CKOT-01 #7: keranjang berubah saat checkout. Membawa item bermasalah agar
 * pemanggil bisa menampilkan penjelasannya, bukan hanya pesan umum.
 */
class KeranjangBerubah extends RuntimeException
{
    /**
     * @param  list<array{id: int, label: string}>  $items
     */
    public function __construct(public readonly array $items)
    {
        parent::__construct('Keranjang berubah, periksa kembali item Anda');
    }
}
