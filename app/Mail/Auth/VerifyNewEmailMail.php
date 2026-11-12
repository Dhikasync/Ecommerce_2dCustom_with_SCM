<?php

namespace App\Mail\Auth;

use App\Models\Auth\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/**
 * FR-AUTH-06 #7, FR-PROF-01 #8. Hash yang ditandatangani adalah `pending_email`,
 * bukan email aktif; kalau memakai email lama, tautan tetap sah setelah email
 * berubah dan bisa dipakai ulang.
 */
class VerifyNewEmailMail extends Mailable
{
    public function __construct(public User $user, public string $token) {}

    public static function urlFor(User $user, string $token): string
    {
        return URL::temporarySignedRoute(
            'profil.email.verify',
            now()->addMinutes(config('auth.verification.expire', 1440)),
            [
                'id' => $user->getKey(),
                'hash' => sha1((string) $user->pending_email),
                'token' => $token,
            ],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Konfirmasi Email Baru Nichibag',
        );
    }

    public function content(): Content
    {
        $url = self::urlFor($this->user, $this->token);

        return new Content(
            htmlString: '<p>Kami menerima permintaan mengubah email akun Nichibag Anda menjadi alamat ini.</p>'
                .'<p><a href="'.$url.'">Konfirmasi Email Baru</a></p>'
                .'<p>Tautan berlaku 24 jam dan hanya dapat dipakai sekali. '
                .'Email lama Anda tetap berlaku sampai tautan ini diklik.</p>',
        );
    }
}
