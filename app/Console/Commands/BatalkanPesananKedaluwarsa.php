<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Pembayaran\KabarPembayaran;
use App\Services\Pembayaran\SinkronPembayaran;
use Illuminate\Console\Command;

/**
 * FR-SYS-01 #1/#7, FR-PAYM-01 #4, FR-PAYM-02 #3: penegakan batas bayar 24 jam.
 * Voucher dilewati sesuai fallback FR-CKOT-02/03 (user-story.md:60).
 */
class BatalkanPesananKedaluwarsa extends Command
{
    protected $signature = 'orders:kedaluwarsa';

    protected $description = 'Membatalkan pesanan yang melewati batas bayar 24 jam';

    public function handle(SinkronPembayaran $sinkron, KabarPembayaran $kabar): int
    {
        $jumlah = 0;

        Order::query()
            ->where('status', 'menunggu_pembayaran')
            ->whereNotNull('kadaluwarsa_pada')
            ->where('kadaluwarsa_pada', '<=', now())
            // Skenario #7: pesanan yang sudah Lunas tidak boleh ikut tersapu.
            ->whereDoesntHave('payments', fn ($query) => $query->where('status', 'lunas'))
            ->orderBy('id')
            ->chunkById(100, function ($daftar) use ($sinkron, $kabar, &$jumlah) {
                foreach ($daftar as $pesanan) {
                    if (! $sinkron->kedaluwarsakan(
                        $pesanan,
                        'Batas pembayaran 24 jam terlewat',
                        'Batas pembayaran 24 jam terlewat (FR-SYS-01 #1)',
                    )) {
                        continue;
                    }

                    $jumlah++;

                    // Kabar dikirim setelah transaksi commit, dan hanya untuk
                    // pesanan yang benar-benar berubah pada giliran ini.
                    $kabar->kirim($pesanan->refresh(), 'kedaluwarsa');
                }
            });

        $this->info("{$jumlah} pesanan dibatalkan karena batas pembayaran lewat.");

        return self::SUCCESS;
    }
}
