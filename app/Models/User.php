<?php

namespace App\Models;

use App\Mail\VerifyEmailMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Kolom yang boleh diisi massal. `role` sengaja ada di sini supaya
     * pembatasan role dilakukan di controller (FR-AUTH-01 #7), bukan di model.
     */
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
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_aktif' => 'boolean',
    ];

    /**
     * FR-AUTH-06 #5: setiap pengiriman membuat token baru, sehingga
     * tautan pada email sebelumnya tidak berlaku lagi.
     */
    public function sendEmailVerificationNotification()
    {
        $this->forceFill(['email_verification_token' => Str::random(64)])->save();

        Mail::to($this->email)->send(new VerifyEmailMail($this));
    }

    /**
     * FR-AUTH-01 #7: apakah user memegang salah satu role yang diminta.
     */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function designs()
    {
        return $this->hasMany(Design::class);
    }

    /**
     * FR-CART-01: satu User hanya punya satu keranjang.
     */
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

    /**
     * Notifikasi in-app memakai tabel `notifications` milik aplikasi
     * (judul, pesan, tipe, is_dibaca). Trait Notifiable bawaan Laravel tidak
     * dipakai karena method notifications()-nya menargetkan skema tabel yang
     * berbeda; email dikirim lewat Mail/Mailable.
     */
    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function validatedDesignRevisions()
    {
        return $this->hasMany(DesignRevision::class, 'divalidasi_oleh');
    }

    /**
     * FR-ORDR-02 #2: permohonan pembatalan yang diajukan User.
     */
    public function cancellationRequests()
    {
        return $this->hasMany(CancellationRequest::class);
    }

    /**
     * FR-ORDR-06 #2/#3: permohonan yang diputuskan Admin.
     */
    public function decidedCancellationRequests()
    {
        return $this->hasMany(CancellationRequest::class, 'diputuskan_oleh');
    }

    /**
     * NFR-SEC-07: jejak aksi sensitif yang dilakukan Admin ini.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
