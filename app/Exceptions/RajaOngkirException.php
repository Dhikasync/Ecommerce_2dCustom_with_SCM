<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * SI-02: layanan lokasi (RajaOngkir) tidak bisa dipakai.
 *
 * Dilempar untuk kunci kosong, gangguan jaringan, status HTTP gagal, atau
 * bentuk respons yang tidak dikenali. Pemanggil menerjemahkannya menjadi
 * pesan FR-PROF-01 #12, jadi detail teknis tidak pernah sampai ke User.
 */
class RajaOngkirException extends RuntimeException {}
