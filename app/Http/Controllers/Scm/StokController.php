<?php

namespace App\Http\Controllers\Scm;

use App\Http\Controllers\Controller;
use App\Models\Produk\Variant;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** FR-SCM-01 #6: Admin SCM mengisi stok awal varian yang baru dibuat. */
class StokController extends Controller
{
    public function awal(Request $request, int $id): JsonResponse
    {
        $varian = Variant::find($id);

        if ($varian === null) {
            return response()->json(['message' => 'Varian tidak ditemukan'], 404);
        }

        if ($varian->stok_fisik > 0) {
            return response()->json([
                'message' => 'Stok awal hanya untuk varian yang belum memiliki stok',
            ], 422);
        }

        $data = $request->validate([
            'stok_fisik' => ['required', 'integer', 'min:1'],
            'alasan' => ['required', 'string', 'max:255'],
        ]);

        $gerakan = DB::transaction(function () use ($varian, $data, $request) {
            $segar = Variant::whereKey($varian->id)->lockForUpdate()->first();

            if ($segar === null || $segar->stok_fisik > 0) {
                return null;
            }

            $segar->update(['stok_fisik' => $data['stok_fisik']]);

            return StockMovement::create([
                'variant_id' => $segar->id,
                'tipe' => 'stok_awal',
                'jumlah_masuk' => $data['stok_fisik'],
                'jumlah_keluar' => 0,
                'stok_fisik_setelah' => $data['stok_fisik'],
                'stok_direservasi_setelah' => $segar->stok_direservasi,
                'alasan' => $data['alasan'],
                'referensi' => 'admin_scm:'.$request->user()->id,
            ]);
        });

        if ($gerakan === null) {
            return response()->json([
                'message' => 'Stok awal hanya untuk varian yang belum memiliki stok',
            ], 422);
        }

        $segar = $varian->refresh();

        return response()->json(['data' => [
            'id' => $segar->id,
            'sku' => $segar->sku,
            'stok_fisik' => $segar->stok_fisik,
            'stok_direservasi' => $segar->stok_direservasi,
            'stok_tersedia' => $segar->stok_fisik - $segar->stok_direservasi,
        ]], 201);
    }
}
