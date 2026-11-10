<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * FR-PROF-01 #3: format nomor telepon.
 *
 * Pesan "Format nomor telepon tidak valid" adalah kutipan persis User Story.
 * Bentuk yang diterima — wajib diawali +62 lalu 9 sampai 12 angka — adalah
 * keputusan pemilik proyek: dokumen hanya memberi contoh "mengandung huruf"
 * sebagai tidak valid dan tidak menetapkan format, panjang, atau normalisasi.
 * Karena itu nomor gaya "08..." sengaja DITOLAK, bukan diubah diam-diam.
 *
 * Batas 9-12 angka menampung nomor seluler Indonesia tanpa menerima nomor
 * sampah yang terlalu pendek atau terlalu panjang.
 */
class NomorTelepon implements ValidationRule
{
    public const PESAN = 'Format nomor telepon tidak valid';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^\+62[0-9]{9,12}$/', $value) !== 1) {
            $fail(self::PESAN);
        }
    }
}
