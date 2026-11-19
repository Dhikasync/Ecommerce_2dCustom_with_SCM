<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * NFR-LOC-01: antarmuka berbahasa Indonesia, termasuk saat batas permintaan
 * terlampaui. Satu frasa dipakai controller dan renderer supaya tidak ada dua
 * kalimat berbeda untuk keadaan yang sama.
 */
class BatasPermintaan
{
    public const PESAN = 'Terlalu banyak permintaan, coba lagi beberapa saat lagi';

    /**
     * Balasan 429 yang membawa sisa waktu tunggu, bukan hanya kalimat.
     *
     * @param  array<string, mixed>  $headers  header pembatas dari middleware
     */
    public static function respons(int $retryAfter, array $headers = []): JsonResponse
    {
        return response()->json([
            'message' => self::PESAN,
            'retry_after' => $retryAfter,
        ], 429, $headers);
    }
}
