<?php

namespace App\Services\Pembayaran;

use App\Exceptions\Pembayaran\MidtransException;

/**
 * Bentuk `gross_amount` Midtrans: kadang bilangan bulat, kadang string
 * `"48000.00"`. Dipakai cek status (FR-PAYM-01 #5) dan notifikasi (#2/#3/#4)
 * supaya keduanya membaca uang dengan aturan yang sama persis.
 */
final class JumlahMidtrans
{
    /** @throws MidtransException */
    public static function baca(mixed $nilai): int
    {
        if (is_int($nilai)) {
            return $nilai;
        }

        if (is_string($nilai) && preg_match('/^(\d+)(\.0+)?$/', $nilai, $cocok) === 1) {
            return (int) $cocok[1];
        }

        throw new MidtransException('Bentuk jumlah pembayaran dari Midtrans tidak dikenali');
    }
}
