<?php

namespace App\Http\Controllers\Desain;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\DesignRevision;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\GambarPublik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FR-CDSN-05 #2/#3/#5, UC-18: antrean validasi desain untuk Admin Ecommerce.
 * #2/#3 sudah menyentuh pesanan; #4/#7 menyusul bersama notifikasi.
 */
class ValidasiDesainController extends Controller
{
    private const PESAN_TIDAK_DITEMUKAN = 'Desain tidak ditemukan';

    private const PESAN_BUKAN_ANTREAN = 'Desain ini tidak sedang menunggu validasi';

    private const STATUS_ANTREAN = 'menunggu_validasi';

    /** FR-CDSN-05 #3: batas revisi 3 hari kalender per putaran. */
    private const HARI_REVISI = 3;

    /** FR-CDSN-05 #1: antrean validasi, yang paling lama menunggu di atas. */
    public function daftar(): JsonResponse
    {
        $data = Design::query()
            ->where('status', self::STATUS_ANTREAN)
            ->with(['user:id,name', 'template:id,nama'])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Design $desain) => $this->ringkas($desain));

        return response()->json(['data' => $data]);
    }

    /** FR-CDSN-05 #5: detail desain beserta riwayat validasinya. */
    public function tampil(int $id): JsonResponse
    {
        $desain = Design::query()
            ->with(['user:id,name', 'template', 'revisions.validator:id,name'])
            ->find($id);

        if ($desain === null) {
            return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
        }

        return response()->json(['data' => $this->ringkas($desain, denganData: true, denganRiwayat: true)]);
    }

    /** FR-CDSN-05 #2: "Setujui" — desain boleh lanjut ke pembayaran. */
    public function setujui(Request $request, int $id): JsonResponse
    {
        $desain = Design::find($id);

        if ($desain === null) {
            return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
        }

        if ($desain->status !== self::STATUS_ANTREAN) {
            return response()->json(['message' => self::PESAN_BUKAN_ANTREAN], 422);
        }

        DB::transaction(function () use ($desain, $request) {
            // Catatan dan batas revisi lama tidak berlaku lagi setelah disetujui,
            // tetapi tetap tersimpan di riwayat.
            $desain->update([
                'status' => 'disetujui',
                'catatan_revisi' => null,
                'batas_revisi_pada' => null,
            ]);

            $this->catatRiwayat($desain, 'disetujui', null, $request->user()->id);

            // FR-CDSN-05 #2 + srs.md:822: order custom terbuka 24 jam sejak disetujui.
            $orderId = OrderItem::query()
                ->where('design_id', $desain->id)
                ->value('order_id');

            if ($orderId !== null) {
                Order::query()
                    ->whereKey($orderId)
                    ->where('status', 'menunggu_validasi')
                    ->update([
                        'status' => 'menunggu_pembayaran',
                        'kadaluwarsa_pada' => now()->addDay(),
                    ]);
            }
        });

        return response()->json([
            'message' => 'Desain disetujui',
            'data' => $this->ringkas($desain->fresh(['user:id,name', 'template'])),
        ]);
    }

    /** FR-CDSN-05 #3: "Minta Revisi" — catatan wajib, batas 3 hari kalender. */
    public function revisi(Request $request, int $id): JsonResponse
    {
        $desain = Design::find($id);

        if ($desain === null) {
            return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
        }

        if ($desain->status !== self::STATUS_ANTREAN) {
            return response()->json(['message' => self::PESAN_BUKAN_ANTREAN], 422);
        }

        $data = $request->validate([
            'catatan_revisi' => ['required', 'string', 'max:2000'],
        ], [
            'catatan_revisi.required' => 'Catatan revisi wajib diisi',
            'catatan_revisi.max' => 'Catatan revisi terlalu panjang',
        ]);

        $catatan = trim($data['catatan_revisi']);

        if ($catatan === '') {
            return response()->json([
                'message' => 'Catatan revisi wajib diisi',
                'errors' => ['catatan_revisi' => ['Catatan revisi wajib diisi']],
            ], 422);
        }

        DB::transaction(function () use ($desain, $request, $catatan) {
            $desain->update([
                'status' => 'perlu_revisi',
                'catatan_revisi' => $catatan,
                'batas_revisi_pada' => now()->addDays(self::HARI_REVISI),
            ]);

            $this->catatRiwayat($desain, 'perlu_revisi', $catatan, $request->user()->id);

            // FR-CDSN-05 #3: pesanan ikut masuk antrean revisi, tanpa batas bayar.
            $orderId = OrderItem::query()
                ->where('design_id', $desain->id)
                ->value('order_id');

            if ($orderId !== null) {
                Order::query()
                    ->whereKey($orderId)
                    ->where('status', 'menunggu_validasi')
                    ->update(['status' => 'menunggu_revisi_desain']);
            }
        });

        return response()->json([
            'message' => 'Permintaan revisi dikirim',
            'data' => $this->ringkas($desain->fresh(['user:id,name', 'template'])),
        ]);
    }

    /**
     * FR-CDSN-05 #5: satu baris per keputusan, berisi versi dan salinan desain
     * yang dinilai Admin supaya riwayat tetap terbaca walau desain berubah.
     */
    private function catatRiwayat(Design $desain, string $status, ?string $catatan, int $adminId): void
    {
        DesignRevision::create([
            'design_id' => $desain->id,
            'versi' => $desain->versi,
            'data_desain' => $desain->data_desain,
            'preview_gambar' => $desain->preview_gambar,
            'status' => $status,
            'catatan_revisi' => $catatan,
            'divalidasi_oleh' => $adminId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkas(Design $desain, bool $denganData = false, bool $denganRiwayat = false): array
    {
        $ringkas = [
            'id' => $desain->id,
            'status' => $desain->status,
            'versi' => $desain->versi,
            'catatan_revisi' => $desain->catatan_revisi,
            'batas_revisi_pada' => $desain->batas_revisi_pada?->toIso8601String(),
            'diajukan_pada' => $desain->updated_at?->toIso8601String(),
            'preview_gambar' => $desain->preview_gambar === null
                ? null
                : GambarPublik::url($desain->preview_gambar),
            'user' => $desain->user === null ? null : [
                'id' => $desain->user->id,
                'name' => $desain->user->name,
            ],
            'template' => $desain->template === null ? null : [
                'id' => $desain->template->id,
                'nama' => $desain->template->nama,
            ],
        ];

        if ($denganData) {
            $data = $desain->data_desain;

            if (is_array($data) && isset($data['elemen'])) {
                foreach ($data['elemen'] as $indeks => $item) {
                    if (($item['tipe'] ?? null) === 'gambar' && isset($item['berkas'])) {
                        $data['elemen'][$indeks]['url'] = GambarPublik::url($item['berkas']);
                    }
                }
            }

            $ringkas['data_desain'] = $data;
        }

        if ($denganRiwayat) {
            $ringkas['riwayat'] = $this->riwayat($desain);
        }

        return $ringkas;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function riwayat(Design $desain): array
    {
        return $desain->revisions
            ->sortBy('id')
            ->map(fn (DesignRevision $revisi) => [
                'id' => $revisi->id,
                'versi' => $revisi->versi,
                'status' => $revisi->status,
                'catatan_revisi' => $revisi->catatan_revisi,
                'tanggal' => $revisi->created_at?->toIso8601String(),
                'divalidasi_oleh' => $revisi->validator === null ? null : [
                    'id' => $revisi->validator->id,
                    'name' => $revisi->validator->name,
                ],
            ])
            ->values()
            ->all();
    }
}
