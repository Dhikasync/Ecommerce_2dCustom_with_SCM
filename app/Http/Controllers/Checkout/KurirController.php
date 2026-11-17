<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use Illuminate\Http\JsonResponse;

/**
 * FR-CKOT-04 #2-#4, srs.md:520: Admin Ecommerce mengaktifkan atau menonaktifkan
 * layanan kurir. Barisnya disiapkan seeder; dokumen tidak punya skenario tambah.
 */
class KurirController extends Controller
{
    /** Pesan persis FR-CKOT-04 #4. */
    private const PESAN_MINIMAL_SATU = 'Minimal satu layanan kurir harus aktif';

    private const PESAN_TIDAK_DITEMUKAN = 'Kurir tidak ditemukan';

    public function daftar(): JsonResponse
    {
        $kurir = Courier::query()
            ->orderBy('kode')
            ->orderBy('layanan')
            ->get()
            ->map(fn (Courier $baris) => $this->ringkas($baris))
            ->all();

        return response()->json(['data' => $kurir]);
    }

    public function aktifkan(int $id): JsonResponse
    {
        $kurir = Courier::find($id);

        if (! $kurir) {
            return $this->tidakDitemukan();
        }

        $kurir->update(['is_aktif' => true]);

        return response()->json([
            'message' => 'Layanan kurir diaktifkan',
            'data' => $this->ringkas($kurir),
        ]);
    }

    public function nonaktifkan(int $id): JsonResponse
    {
        $kurir = Courier::find($id);

        if (! $kurir) {
            return $this->tidakDitemukan();
        }

        // Aturan antar-baris: tidak bisa dijaga CHECK constraint PostgreSQL.
        if ($kurir->is_aktif && ! Courier::where('is_aktif', true)->whereKeyNot($kurir->getKey())->exists()) {
            return response()->json(['message' => self::PESAN_MINIMAL_SATU], 422);
        }

        $kurir->update(['is_aktif' => false]);

        return response()->json([
            'message' => 'Layanan kurir dinonaktifkan',
            'data' => $this->ringkas($kurir),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkas(Courier $kurir): array
    {
        return [
            'id' => $kurir->id,
            'kode' => $kurir->kode,
            'nama' => $kurir->nama,
            'layanan' => $kurir->layanan,
            'is_aktif' => $kurir->is_aktif,
        ];
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
    }
}
