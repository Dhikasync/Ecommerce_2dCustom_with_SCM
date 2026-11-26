<?php

namespace App\Services\Pesanan;

use App\Mail\Pesanan\PembayaranMail;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * FR-ORDR-01 #3 + FR-ORDR-03 #5/#6: perubahan status produksi dikabarkan lewat
 * dua kanal yang diizinkan dokumen — in-app dan email.
 */
class KabarStatusPesanan
{
    public function kirim(Order $pesanan, string $judul, string $pesan): void
    {
        Notification::create([
            'user_id' => $pesanan->user_id,
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe' => 'pesanan',
        ]);

        try {
            Mail::to($pesanan->user->email)->send(new PembayaranMail($pesanan, $judul, $pesan));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
