<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * FR-AUTH-04 #1: email berisi tautan reset password bertoken.
 *
 * Token dibuat oleh PasswordBroker (disimpan sebagai hash di tabel
 * password_reset_tokens), jadi token asli hanya ada di email ini.
 */
class ResetPasswordMail extends Mailable
{
    public function __construct(public User $user, public string $token)
    {
    }

    /**
     * Tautan 24 jam sesuai FR-AUTH-04 #4 (config auth.passwords.users.expire).
     */
    public static function urlFor(User $user, string $token): string
    {
        return route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Password Nichibag',
        );
    }

    public function content(): Content
    {
        $url = self::urlFor($this->user, $this->token);

        return new Content(
            htmlString: '<p>Kami menerima permintaan reset password untuk akun Anda.</p>'
                .'<p><a href="'.$url.'">Reset Password</a></p>'
                .'<p>Tautan berlaku 24 jam dan hanya dapat dipakai sekali. '
                .'Abaikan email ini jika Anda tidak meminta reset password.</p>',
        );
    }
}
