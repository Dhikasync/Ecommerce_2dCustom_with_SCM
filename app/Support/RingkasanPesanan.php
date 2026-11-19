<?php

namespace App\Support;

use App\Models\Order;

/**
 * FR-ORDR-01 #1/#2, Lampiran B: tiga domain status yang dipisah dokumen —
 * status pesanan, status pembayaran, status desain. Turunannya ditulis di satu
 * tempat supaya daftar, detail, dan respons checkout tidak mungkin berbeda.
 */
class RingkasanPesanan
{
    /**
     * Lampiran A: "Belum Dibayar" selama belum ada pembayaran. Baris `payments`
     * baru lahir saat transaksi gateway dimulai (FR-PAYM-01 #1).
     */
    public static function statusPembayaran(Order $pesanan): string
    {
        return $pesanan->latestPayment?->status ?? 'belum_dibayar';
    }

    /** FR-ORDR-01 #2: pesanan produk jadi tidak punya status desain. */
    public static function statusDesain(Order $pesanan): string
    {
        return $pesanan->orderItems->pluck('design')->filter()->first()?->status ?? 'tidak_ada';
    }

    /**
     * Satu baris daftar "Pesanan Saya" (FR-ORDR-01 #1, srs.md:584).
     *
     * @return array<string, mixed>
     */
    public static function untukDaftar(Order $pesanan): array
    {
        return [
            'id' => $pesanan->id,
            'nomor_pesanan' => $pesanan->nomor_pesanan,
            'status' => $pesanan->status,
            'status_pembayaran' => self::statusPembayaran($pesanan),
            'status_desain' => self::statusDesain($pesanan),
            'total' => $pesanan->total,
            'jumlah_item' => (int) $pesanan->orderItems->sum('quantity'),
            // FR-ORDR-01: nomor resi hanya terisi setelah pesanan Dikirim.
            'nomor_resi' => $pesanan->nomor_resi,
            'alasan_pembatalan' => $pesanan->alasan_pembatalan,
            'dibuat_pada' => $pesanan->created_at?->toIso8601String(),
            'kadaluwarsa_pada' => $pesanan->kadaluwarsa_pada?->toIso8601String(),
        ];
    }
}
