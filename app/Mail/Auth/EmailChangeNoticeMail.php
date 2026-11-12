<?php

namespace App\Mail\Auth;

use App\Models\Auth\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** FR-AUTH-06 #7: pengaman, agar pemilik akun tahu bila perubahan diajukan orang lain. */
class EmailChangeNoticeMail extends Mailable
{
    public function __construct(public User $user, public string $emailBaru) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pemberitahuan Perubahan Email Nichibag',
        );
    }

    public function content(): Content
    {
        // Alamat email berasal dari input User, jadi di-escape sebelum masuk HTML.
        $emailBaru = e($this->emailBaru);

        return new Content(
            htmlString: '<p>Kami menerima permintaan mengubah email akun Nichibag Anda menjadi '
                .'<strong>'.$emailBaru.'</strong>.</p>'
                .'<p>Email ini tetap berlaku sampai perubahan dikonfirmasi lewat tautan yang '
                .'dikirim ke alamat baru tersebut. Sampai saat itu, Anda masih dapat login '
                .'menggunakan email ini.</p>'
                .'<p>Jika Anda tidak meminta perubahan ini, abaikan email ini dan segera ganti '
                .'password akun Anda.</p>',
        );
    }
}
