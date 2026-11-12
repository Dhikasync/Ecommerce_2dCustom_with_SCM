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
 *
 * Endpoint ini hanya meneruskan kata kunci ke RajaOngkir lalu
 * menerjemahkan hasil atau kegagalannya menjadi respons yang ditetapkan
 * User Story. Aturan alamat (lokasi wajib dipilih, alamat tidak boleh
 * disimpan tanpa lokasi) diperiksa di endpoint alamat, bukan di sini.
 */
class LokasiController extends Controller
{
    /** Pesan persis dari User Story FR-PROF-01 #11. */
    private const PESAN_TIDAK_DITEMUKAN = 'Lokasi tidak ditemukan, coba kata kunci lain';

    /** Pesan contoh dari FR-PROF-01 #12; dokumen menulisnya dengan "misal". */
    private const PESAN_GANGGUAN = 'Layanan lokasi sedang bermasalah, silakan coba lagi';

    public function cari(Request $request, RajaOngkir $rajaOngkir, LokasiDitawarkan $ditawarkan): JsonResponse
    {
        $kataKunci = $request->query('search');
        $kataKunci = is_string($kataKunci) ? trim($kataKunci) : '';

        // Dokumen tidak mengatur kata kunci kosong. Keputusan yang diambil:
        // tidak memanggil API dan menjawab seperti "tidak ada hasil".
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

        // Hasil yang baru dilihat User dicatat supaya endpoint alamat bisa
        // memastikan lokasi yang dipilih memang berasal dari daftar ini
        // (FR-PROF-01 #4: lokasi tidak dapat diketik bebas).
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
