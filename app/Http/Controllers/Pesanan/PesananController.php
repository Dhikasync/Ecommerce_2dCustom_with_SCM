<?php

namespace App\Http\Controllers\Pesanan;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Pesanan\KabarStatusPesanan;
use App\Support\PelacakanKurir;
use App\Support\RingkasanPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * FR-ORDR-01, UC-32: User melihat status dan riwayat pesanannya sendiri.
 * Pembatasan "milik sendiri" adalah NFR-SEC-03, bukan aturan tambahan.
 */
class PesananController extends Controller
{
    private const PESAN_TIDAK_DITEMUKAN = 'Pesanan tidak ditemukan';

    public function __construct(private readonly KabarStatusPesanan $kabar) {}

    /**
     * FR-ORDR-01 #1: daftar pesanan dengan filter status pesanan. Sembilan
     * nilai yang boleh datang dari Lampiran B, bukan daftar karangan.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(Order::STATUS)],
        ]);

        $daftar = Order::query()
            // NFR-SEC-03: User hanya melihat pesanannya sendiri.
            ->where('user_id', $request->user()->id)
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            // Status pembayaran dan desain diturunkan dari relasi ini; tanpa
            // eager load, satu baris daftar berarti dua query tambahan.
            ->with('orderItems.design', 'latestPayment')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $daftar->map(fn (Order $pesanan) => RingkasanPesanan::untukDaftar($pesanan))->all(),
        ]);
    }

    /**
     * FR-ORDR-01 #2 + FR-ORDR-04 #3: detail pesanan beserta kabar pelacakan.
     */
    public function show(Request $request, string $nomor): JsonResponse
    {
        $pesanan = Order::query()
            ->where('nomor_pesanan', $nomor)
            // NFR-SEC-03: nomor pesanan User lain dijawab seperti nomor asing.
            ->where('user_id', $request->user()->id)
            ->with(['orderItems.design', 'latestPayment', 'courier', 'statusHistories.changedBy'])
            ->first();

        if (! $pesanan) {
            return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
        }

        $data = RingkasanPesanan::untukDetail($pesanan);
        $data['pesan_tracking'] = null;
        $data['riwayat_tracking'] = [];

        $kode = $pesanan->courier?->kode;

        if ($pesanan->status === 'dikirim' && ($kode === null || ! PelacakanKurir::didukung($kode))) {
            $data['pesan_tracking'] = PelacakanKurir::PESAN_TIDAK_TERSEDIA;

            if ($kode !== null) {
                $data['tautan_lacak'] = PelacakanKurir::tautanUntukTidakDidukung($kode);
            }
        }

        return response()->json(['data' => $data]);
    }

    /** FR-ORDR-04 #4: User menyatakan paket diterima. */
    public function terima(Request $request, string $nomor): JsonResponse
    {
        $pesanan = Order::query()
            ->where('nomor_pesanan', $nomor)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($pesanan === null) {
            return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
        }

        if ($pesanan->status !== 'dikirim') {
            return response()->json(['message' => 'Perubahan status tidak valid'], 422);
        }

        $berubah = DB::transaction(function () use ($pesanan, $request) {
            $segar = Order::whereKey($pesanan->id)->lockForUpdate()->first();

            if ($segar === null || $segar->status !== 'dikirim') {
                return false;
            }

            OrderStatusHistory::create([
                'order_id' => $segar->id,
                'status_lama' => 'dikirim',
                'status_baru' => 'selesai',
                'diubah_oleh' => $request->user()->id,
            ]);

            $segar->update([
                'status' => 'selesai',
                'selesai_pada' => now(),
            ]);

            return true;
        });

        if (! $berubah) {
            return response()->json(['message' => 'Perubahan status tidak valid'], 422);
        }

        $segar = $pesanan->refresh();

        $this->kabar->kirim(
            $segar,
            'Pesanan selesai',
            'Pesanan '.$segar->nomor_pesanan.' telah selesai. Terima kasih sudah berbelanja.',
        );

        return response()->json([
            'message' => 'Pesanan diterima',
            'data' => [
                'nomor_pesanan' => $segar->nomor_pesanan,
                'status' => $segar->status,
            ],
        ]);
    }
}
