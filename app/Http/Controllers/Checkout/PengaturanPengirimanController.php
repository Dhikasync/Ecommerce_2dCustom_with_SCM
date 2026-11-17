<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Models\ShopSetting;
use App\Services\Profil\LokasiDitawarkan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * FR-CKOT-04 #1, srs.md:520: alamat asal toko berupa jalan + lokasi hasil
 * pencarian. Satu baris saja karena `shop_settings` dijaga CHECK (id = 1).
 */
class PengaturanPengirimanController extends Controller
{
    /** Pesan persis FR-PROF-01 #11, dirujuk FR-CKOT-04 #1. */
    private const PESAN_LOKASI_TIDAK_DIKENAL = 'Lokasi tidak ditemukan, coba kata kunci lain';

    public function tampil(): JsonResponse
    {
        $setting = ShopSetting::find(1);

        return response()->json(['data' => $setting ? $this->ringkas($setting) : null]);
    }

    public function simpan(Request $request, LokasiDitawarkan $ditawarkan): JsonResponse
    {
        if (is_string($request->input('alamat_jalan'))) {
            $request->merge(['alamat_jalan' => trim($request->input('alamat_jalan'))]);
        }

        $data = $request->validate([
            'alamat_jalan' => ['required', 'string'],
            'kode_lokasi' => ['required', 'string', 'max:255'],
            // Diterima demi bentuk request yang tidak berubah, tetapi tidak
            // dipakai: label selalu versi hasil pencarian (srs.md:174).
            'label_lokasi' => ['sometimes', 'string', 'max:255'],
        ], [
            'alamat_jalan.required' => 'Alamat jalan wajib diisi',
            'kode_lokasi.required' => 'Lokasi wajib dipilih',
        ]);

        $label = $ditawarkan->label($data['kode_lokasi']);

        if ($label === null) {
            throw ValidationException::withMessages([
                'kode_lokasi' => self::PESAN_LOKASI_TIDAK_DIKENAL,
            ]);
        }

        $setting = $this->simpanSatuBaris([
            'alamat_jalan' => $data['alamat_jalan'],
            'kode_lokasi' => $data['kode_lokasi'],
            'label_lokasi' => $label,
        ]);

        return response()->json([
            'message' => 'Pengaturan pengiriman disimpan',
            'data' => $this->ringkas($setting),
        ]);
    }

    /**
     * `id` tidak ada di $fillable, jadi dibuat eksplisit: CHECK constraint
     * menolak baris dengan id selain 1.
     *
     * @param  array<string, mixed>  $data
     */
    private function simpanSatuBaris(array $data): ShopSetting
    {
        $setting = ShopSetting::find(1);

        if ($setting) {
            $setting->update($data);

            return $setting;
        }

        $setting = new ShopSetting($data);
        $setting->id = 1;
        $setting->save();

        return $setting;
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkas(ShopSetting $setting): array
    {
        return [
            'alamat_jalan' => $setting->alamat_jalan,
            'kode_lokasi' => $setting->kode_lokasi,
            'label_lokasi' => $setting->label_lokasi,
        ];
    }
}
