<?php

namespace App\Http\Controllers\Pesanan;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\RingkasanPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FR-ORDR-01, UC-32: User melihat status dan riwayat pesanannya sendiri.
 * Pembatasan "milik sendiri" adalah NFR-SEC-03, bukan aturan tambahan.
 */
class PesananController extends Controller
{
    private const PESAN_TIDAK_DITEMUKAN = 'Pesanan tidak ditemukan';

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
     * FR-ORDR-01 #2: detail terpisah — status pesanan, pembayaran, dan desain,
     * ditambah item, alamat, kurir, dan riwayat status.
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

        return response()->json(['data' => RingkasanPesanan::untukDetail($pesanan)]);
    }
}
