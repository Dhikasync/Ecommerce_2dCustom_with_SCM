<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * FR-PROF-01 #3. Format `+62` + 9-12 angka adalah keputusan proyek karena
 * dokumen tidak menetapkan format; nomor "08..." ditolak, bukan diubah.
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
