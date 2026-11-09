<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/**
 * FR-AUTH-06 #1/#5: email berisi tautan verifikasi bertanda tangan
 * yang membawa token aktif milik user.
 */
class VerifyEmailMail extends Mailable
{
    public function __construct(public User $user) {}

    /**
     * Tautan 24 jam: id + hash email + token, ditandatangani APP_KEY.
     */
    public static function urlFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 1440)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
                'token' => $user->email_verification_token,
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
        $url = self::urlFor($this->user);

        return new Content(
            htmlString: '<p>Silakan klik tautan berikut untuk memverifikasi email Anda:</p>'
                .'<p><a href="'.$url.'">Verifikasi Email</a></p>'
                .'<p>Tautan berlaku 24 jam sejak email ini dikirim.</p>',
        );
    }
}
