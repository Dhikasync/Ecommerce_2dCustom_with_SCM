<?php

namespace App\Exceptions\Pembayaran;

use RuntimeException;

/** SI-01: Midtrans tidak dapat dipakai; pemanggil mengubahnya jadi pesan pengguna. */
class MidtransException extends RuntimeException {}
