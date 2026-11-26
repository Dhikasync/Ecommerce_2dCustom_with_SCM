<?php

namespace App\Console\Commands;

use App\Mail\Pesanan\PembayaranMail;
use App\Models\Design;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Produk\Variant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * FR-SYS-01 #2 + FR-CDSN-05 #8: batas revisi 3 hari kalender. Sebelum Lunas,
 * yang dilepas hanya Stok Direservasi; Stok Fisik tidak disentuh.
 */
class BatalkanMasaRevisi extends Command
{
    protected $signature = 'orders:revisi-kedaluwarsa';

    protected $description = 'Membatalkan pesanan yang melewati batas revisi desain';

    private const ALASAN = 'Masa revisi desain berakhir';

    public function handle(): int
    {
        $jumlah = 0;

        Order::query()
            ->where('status', 'menunggu_revisi_desain')
            ->orderBy('id')
            ->chunkById(100, function ($daftar) use (&$jumlah) {
                foreach ($daftar as $pesanan) {
                    if ($this->batalkan($pesanan)) {
                        $jumlah++;
                    }
                }
            });

        $this->info("{$jumlah} pesanan dibatalkan karena masa revisi desain berakhir.");

        return self::SUCCESS;
    }

    private function batalkan(Order $pesanan): bool
    {
        $berubah = DB::transaction(function () use ($pesanan) {
            // FR-SYS-01 #7: baca ulang di bawah kunci, karena User bisa saja
            // mengirim ulang desain setelah kandidat dibaca scheduler.
            $segar = Order::whereKey($pesanan->id)->lockForUpdate()->first();

            if ($segar === null || $segar->status !== 'menunggu_revisi_desain') {
                return false;
            }

            $desain = Design::query()
                ->whereIn('id', $segar->orderItems()->whereNotNull('design_id')->pluck('design_id'))
                ->where('status', 'perlu_revisi')
                ->whereNotNull('batas_revisi_pada')
                ->where('batas_revisi_pada', '<=', now())
                ->lockForUpdate()
                ->get();

            if ($desain->isEmpty()) {
                return false;
            }

            foreach ($segar->orderItems()->whereNotNull('variant_id')->get()->groupBy('variant_id') as $variantId => $item) {
                Variant::whereKey($variantId)->lockForUpdate()->first()?->decrement('stok_direservasi', (int) $item->sum('quantity'));
            }

            foreach ($desain as $d) {
                $d->update(['status' => 'kedaluwarsa']);
            }

            OrderStatusHistory::create([
                'order_id' => $segar->id,
                'status_lama' => 'menunggu_revisi_desain',
                'status_baru' => 'dibatalkan',
                'catatan' => self::ALASAN.' (FR-SYS-01 #2)',
            ]);

            $segar->update([
                'status' => 'dibatalkan',
                'alasan_pembatalan' => self::ALASAN,
            ]);

            return true;
        });

        if (! $berubah) {
            return false;
        }

        $pesan = 'Pesanan '.$pesanan->nomor_pesanan.' dibatalkan karena '.self::ALASAN
            .'. Stok yang sempat direservasi sudah dilepas.';

        Notification::create([
            'user_id' => $pesanan->user_id,
            'judul' => 'Pesanan dibatalkan',
            'pesan' => $pesan,
            'tipe' => 'pembayaran',
        ]);

        try {
            Mail::to($pesanan->user->email)->send(new PembayaranMail(
                $pesanan->refresh(),
                'Pesanan dibatalkan',
                $pesan,
            ));
        } catch (Throwable $e) {
            report($e);
        }

        return true;
    }
}
