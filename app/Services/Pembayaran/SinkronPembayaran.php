<?php

namespace App\Services\Pembayaran;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Produk\Variant;
use Illuminate\Support\Facades\DB;

/**
 * FR-PAYM-01 #2/#3/#4/#5, UC-33: satu tempat yang menerjemahkan status Midtrans
 * menjadi keadaan lokal. Tombol "Cek Status Pembayaran" dan webhook nanti sama-
 * sama memakai kelas ini supaya efeknya tidak mungkin berbeda.
 */
class SinkronPembayaran
{
    /** Status Midtrans yang berarti uang sudah masuk. */
    private const BERHASIL = ['settlement', 'capture'];

    /** Status Midtrans yang berarti percobaan ini gagal, pesanan masih boleh dibayar. */
    private const GAGAL = ['deny', 'cancel', 'failure'];

    /**
     * @param  array{order_id: string, transaction_id: ?string, metode: ?string, jumlah: int, transaction_status: string, fraud_status: ?string}  $status
     * @return array{status_pembayaran: string, diterapkan: bool} `diterapkan` false
     *                                                            berarti notifikasi ini tidak mengubah apa pun (mis. kiriman ganda).
     */
    public function terapkan(Order $pesanan, Payment $pembayaran, array $status): array
    {
        return DB::transaction(function () use ($pesanan, $pembayaran, $status) {
            // NFR-PRF-05: baca ulang di bawah kunci, karena webhook dan tombol
            // cek bisa datang bersamaan untuk pesanan yang sama.
            $pesanan = Order::whereKey($pesanan->id)->lockForUpdate()->firstOrFail();
            $pembayaran = Payment::whereKey($pembayaran->id)->lockForUpdate()->firstOrFail();

            if ($pembayaran->status === 'lunas') {
                return ['status_pembayaran' => 'lunas', 'diterapkan' => false];
            }

            $transaksi = [
                'transaction_id' => $status['transaction_id'],
                'metode' => $status['metode'],
            ];

            if ($this->berhasil($status)) {
                return ['status_pembayaran' => $this->lunas($pesanan, $pembayaran, $transaksi), 'diterapkan' => true];
            }

            if ($status['transaction_status'] === 'expire') {
                if ($pembayaran->status === 'kedaluwarsa') {
                    return ['status_pembayaran' => 'kedaluwarsa', 'diterapkan' => false];
                }

                return ['status_pembayaran' => $this->kedaluwarsa($pesanan, $pembayaran, $transaksi), 'diterapkan' => true];
            }

            if (in_array($status['transaction_status'], self::GAGAL, true)) {
                if ($pembayaran->status === 'gagal') {
                    return ['status_pembayaran' => 'gagal', 'diterapkan' => false];
                }

                // Skenario #3: pesanan tetap Menunggu Pembayaran supaya User
                // dapat mencoba metode lain selama 24 jam belum habis.
                $pembayaran->update([...$transaksi, 'status' => 'gagal']);

                return ['status_pembayaran' => 'gagal', 'diterapkan' => true];
            }

            // `pending` dan status lain yang tidak diatur dokumen: tidak ada
            // perubahan, bukan dianggap gagal.
            return ['status_pembayaran' => $pembayaran->status, 'diterapkan' => false];
        });
    }

    /**
     * `capture` baru dianggap lunas kalau tidak ditandai mencurigakan. Status
     * `challenge` menunggu keputusan Midtrans, jadi belum boleh jadi uang.
     *
     * @param  array<string, mixed>  $status
     */
    private function berhasil(array $status): bool
    {
        if (! in_array($status['transaction_status'], self::BERHASIL, true)) {
            return false;
        }

        if ($status['transaction_status'] === 'capture') {
            return $status['fraud_status'] !== 'challenge';
        }

        return true;
    }

    /**
     * @param  array{transaction_id: ?string, metode: ?string}  $transaksi
     */
    private function lunas(Order $pesanan, Payment $pembayaran, array $transaksi): string
    {
        $pembayaran->update([
            ...$transaksi,
            'status' => 'lunas',
            'dibayar_pada' => now(),
        ]);

        // Pesanan yang sudah dibatalkan tidak dihidupkan lagi oleh notifikasi
        // yang datang terlambat; uangnya menjadi urusan refund (FR-PAYM-04).
        if ($pesanan->status === 'menunggu_pembayaran') {
            $this->ubahStatus($pesanan, 'diproses');
            $this->kurangiStok($pesanan);
        }

        return 'lunas';
    }

    /**
     * @param  array{transaction_id: ?string, metode: ?string}  $transaksi
     */
    private function kedaluwarsa(Order $pesanan, Payment $pembayaran, array $transaksi): string
    {
        $pembayaran->update([...$transaksi, 'status' => 'kedaluwarsa']);

        if ($pesanan->status === 'menunggu_pembayaran') {
            $this->ubahStatus($pesanan, 'dibatalkan');
            $this->lepasReservasi($pesanan);
        }

        return 'kedaluwarsa';
    }

    /** Skema Lampiran A: Stok Fisik dan Stok Direservasi sama-sama turun. */
    private function kurangiStok(Order $pesanan): void
    {
        foreach ($this->jumlahPerVarian($pesanan) as $variantId => $jumlah) {
            $varian = Variant::whereKey($variantId)->lockForUpdate()->first();

            if ($varian === null) {
                // Varian dihapus setelah checkout; identitasnya sudah tersalin
                // di order_items, jadi tidak ada stok yang bisa dikurangi.
                continue;
            }

            $varian->decrement('stok_fisik', $jumlah);
            $varian->decrement('stok_direservasi', $jumlah);
        }
    }

    /** Pembatalan sebelum Lunas: reservasi dikembalikan, Stok Fisik tidak disentuh. */
    private function lepasReservasi(Order $pesanan): void
    {
        foreach ($this->jumlahPerVarian($pesanan) as $variantId => $jumlah) {
            Variant::whereKey($variantId)->lockForUpdate()->first()?->decrement('stok_direservasi', $jumlah);
        }
    }

    /** @return array<int, int> variant_id => total quantity, digabung bila varian sama. */
    private function jumlahPerVarian(Order $pesanan): array
    {
        return $pesanan->orderItems()
            ->whereNotNull('variant_id')
            ->get()
            ->groupBy('variant_id')
            ->map(fn ($item) => (int) $item->sum('quantity'))
            ->all();
    }

    private function ubahStatus(Order $pesanan, string $baru): void
    {
        // FR-ORDR-01 #2: perubahan status sistem tetap masuk riwayat, dengan
        // `diubah_oleh` kosong karena bukan tindakan Admin maupun User.
        OrderStatusHistory::create([
            'order_id' => $pesanan->id,
            'status_lama' => $pesanan->status,
            'status_baru' => $baru,
            'diubah_oleh' => null,
            'catatan' => 'Disinkronkan dari status payment gateway (FR-PAYM-01 #5)',
        ]);

        $pesanan->update(['status' => $baru]);
    }
}
