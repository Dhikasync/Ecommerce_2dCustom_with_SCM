<?php

namespace App\Http\Controllers\Profil;

use App\Exceptions\Profil\RajaOngkirException;
use App\Http\Controllers\Controller;
use App\Services\Profil\LokasiDitawarkan;
use App\Services\Profil\RajaOngkir;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-PROF-01 #4, #11, #12, UC-08: pencarian lokasi kecamatan/kelurahan.
 * Aturan alamat diperiksa di AlamatController, bukan di sini.
 */
class LokasiController extends Controller
{
    private const PESAN_TIDAK_DITEMUKAN = 'Lokasi tidak ditemukan, coba kata kunci lain';

    /** Pesan contoh FR-PROF-01 #12; dokumen menulisnya dengan "misal". */
    private const PESAN_GANGGUAN = 'Layanan lokasi sedang bermasalah, silakan coba lagi';

    public function cari(Request $request, RajaOngkir $rajaOngkir, LokasiDitawarkan $ditawarkan): JsonResponse
    {
        $kataKunci = $request->query('search');
        $kataKunci = is_string($kataKunci) ? trim($kataKunci) : '';

        // Kata kunci kosong tidak diatur dokumen: API tidak dipanggil dan
        // dijawab seperti "tidak ada hasil".
        if ($kataKunci === '') {
            return $this->tidakDitemukan();
        }

        try {
            $lokasi = $rajaOngkir->cariLokasi($kataKunci);
        } catch (RajaOngkirException $e) {
            // Detail teknis masuk log; User hanya melihat pesan gangguan.
            report($e);

            return response()->json([
                'message' => self::PESAN_GANGGUAN,
            ], 503);
        }

        if ($lokasi === []) {
            return $this->tidakDitemukan();
        }

        // Dicatat supaya AlamatController bisa memastikan lokasi yang dipilih
        // memang berasal dari daftar ini (FR-PROF-01 #4).
        $ditawarkan->catat($lokasi);

        return response()->json([
            'data' => $lokasi,
        ]);
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json([
            'message' => self::PESAN_TIDAK_DITEMUKAN,
            'data' => [],
        ]);
    }
}
