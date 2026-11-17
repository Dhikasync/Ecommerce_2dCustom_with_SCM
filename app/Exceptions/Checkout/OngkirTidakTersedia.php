<?php

namespace App\Exceptions\Checkout;

use RuntimeException;

/**
 * FR-CKOT-01 #8: ongkir tidak tersedia, sehingga pesanan tidak boleh dibentuk.
 * Alamat asal toko belum diisi dan tarif layanan yang dipilih tidak dikembalikan
 * API diperlakukan sama: sama-sama "belum dapat dihitung".
 */
class OngkirTidakTersedia extends RuntimeException {}
