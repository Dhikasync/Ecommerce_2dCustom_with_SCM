<?php

namespace App\Models\Auth;

use App\Mail\Auth\EmailChangeNoticeMail;
use App\Mail\Auth\VerifyEmailMail;
use App\Mail\Auth\VerifyNewEmailMail;
use App\Models\AuditLog;
use App\Models\CancellationRequest;
use App\Models\Cart;
use App\Models\Design;
use App\Models\DesignRevision;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Profil\Address;
use App\Models\Review;
use Database\Factories\Auth\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** `role` bisa diisi massal; pembatasannya di controller (FR-AUTH-01 #7). */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'no_hp',
        'foto_profil',
        'is_aktif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'pending_email_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_aktif' => 'boolean',
    ];

    /** Email tidak membedakan huruf besar/kecil, jadi disamakan agar unique tidak bentrok. */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::lower(trim($value)),
        );
    }

    /** Sama seperti `email`, supaya pengecekan "sudah dipakai" konsisten. */
    protected function pendingEmail(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Str::lower(trim($value)),
        );
    }

    /**
     * FR-AUTH-06 #7, FR-PROF-01 #8: email lama tetap berlaku sampai konfirmasi.
     * Yang disimpan hanya hash token; token baru membatalkan tautan lama.
     *
     * @return string token asli, untuk dikirim lewat email
     */
    public function ajukanPerubahanEmail(string $emailBaru): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'pending_email' => $emailBaru,
            'pending_email_token' => hash('sha256', $token),
        ])->save();

        return $token;
    }

    /** Tautan ke email baru + pemberitahuan ke email lama (FR-AUTH-06 #7). */
    public function kirimTautanPerubahanEmail(string $token): void
    {
        Mail::to((string) $this->pending_email)->send(new VerifyNewEmailMail($this, $token));
        Mail::to($this->email)->send(new EmailChangeNoticeMail($this, (string) $this->pending_email));
    }

    /** FR-AUTH-06 #5: hanya hash token yang disimpan, token asli hanya di email. */
    public function sendEmailVerificationNotification()
    {
        $token = Str::random(64);

        $this->forceFill(['email_verification_token' => hash('sha256', $token)])->save();

        Mail::to($this->email)->send(new VerifyEmailMail($this, $token));
    }

    /** FR-AUTH-01 #7. */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function designs()
    {
        return $this->hasMany(Design::class);
    }

    /** FR-CART-01: satu User satu keranjang. */
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /** Notifikasi in-app; trait Notifiable tidak dipakai karena skemanya berbeda. */
    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function validatedDesignRevisions()
    {
        return $this->hasMany(DesignRevision::class, 'divalidasi_oleh');
    }

    /** FR-ORDR-02 #2: permohonan pembatalan yang diajukan User. */
    public function cancellationRequests()
    {
        return $this->hasMany(CancellationRequest::class);
    }

    /** FR-ORDR-06 #2/#3: permohonan yang diputuskan Admin. */
    public function decidedCancellationRequests()
    {
        return $this->hasMany(CancellationRequest::class, 'diputuskan_oleh');
    }

    /** NFR-SEC-07. */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
