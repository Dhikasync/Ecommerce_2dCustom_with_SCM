<?php

namespace App\Mail\Auth;

use App\Models\Auth\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/**
 * FR-AUTH-06 #1/#5. Token asli hanya ada di objek ini dan tautan email;
 * kolom `email_verification_token` menyimpan hash-nya.
 */
class VerifyEmailMail extends Mailable
{
    public function __construct(public User $user, public string $token) {}

    /** Tautan 24 jam: id + hash email + token, ditandatangani APP_KEY. */
    public static function urlFor(User $user, string $token): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 1440)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
                'token' => $token,
            ],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verifikasi Email Nichibag',
        );
    }

    public function content(): Content
    {
        $url = self::urlFor($this->user, $this->token);

        return new Content(
            htmlString: '<p>Silakan klik tautan berikut untuk memverifikasi email Anda:</p>'
                .'<p><a href="'.$url.'">Verifikasi Email</a></p>'
                .'<p>Tautan berlaku 24 jam sejak email ini dikirim.</p>',
        );
    }
}
