<?php

namespace App\Services\Desain;

use App\Mail\Pesanan\PembayaranMail;
use App\Models\Design;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * FR-CDSN-05 #7: User diberi kabar revisi lewat email + in-app, lengkap dengan
 * catatan Admin dan tautan editor untuk memperbaiki desain.
 */
class KabarValidasiDesain
{
    public function kirimRevisi(Design $desain, string $catatan): void
    {
        $desain->loadMissing('template');

        $produkId = $desain->template?->product_id;
        $tautan = $produkId === null ? null : route('desain.editor', $produkId);

        $judul = 'Desain perlu revisi';
        $pesan = 'Desain Anda perlu revisi: '.$catatan;

        if ($tautan !== null) {
            $pesan .= ' Buka editor untuk memperbaikinya: '.$tautan;
        }

        Notification::create([
            'user_id' => $desain->user_id,
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe' => 'desain',
        ]);

        $orderId = OrderItem::orderIdUntukDesain($desain->id);

        if ($orderId === null || ($pesanan = Order::with('user')->find($orderId)) === null) {
            return;
        }

        try {
            Mail::to($pesanan->user->email)->send(new PembayaranMail($pesanan, $judul, $pesan));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
