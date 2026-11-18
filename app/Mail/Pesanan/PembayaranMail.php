<?php

namespace App\Mail\Pesanan;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * FR-PAYM-01 #2/#4, FR-ORDR-01 #3: perubahan status pesanan dan pembayaran
 * diberitahukan lewat email. Kanal in-app ditangani baris `notifications`.
 */
class PembayaranMail extends Mailable
{
    public function __construct(
        public Order $pesanan,
        public string $judul,
        public string $pesan,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->judul.' - '.$this->pesanan->nomor_pesanan,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>'.e($this->pesan).'</p>'
                .'<p>Nomor pesanan: '.e($this->pesanan->nomor_pesanan).'</p>',
        );
    }
}
