<?php

namespace App\Models;

use App\Mail\EmailChangeNoticeMail;
use App\Mail\VerifyEmailMail;
use App\Mail\VerifyNewEmailMail;
use Database\Factories\UserFactory;
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
        // FR-AUTH-06 #7: token konfirmasi email baru juga rahasia.
        'pending_email_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_aktif' => 'boolean',
    ];

    /**
     * Email selalu disimpan dalam huruf kecil. Alamat email tidak membedakan
     * huruf besar/kecil, jadi tanpa ini "Budi@example.test" dan
     * "budi@example.test" bisa lolos validasi unique lalu bentrok di database.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::lower(trim($value)),
        );
    }

    /**
     * Email baru ikut dinormalkan seperti email utama, supaya
     * "Budi@Example.Test" dan "budi@example.test" tidak dianggap dua alamat
     * berbeda saat memeriksa apakah email sudah dipakai.
     */
    protected function pendingEmail(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Str::lower(trim($value)),
        );
    }

    /**
     * FR-AUTH-06 #7, FR-PROF-01 #8: simpan email baru sebagai "menunggu
     * konfirmasi". Email lama di kolom `email` TIDAK disentuh di sini, karena
     * dokumen menetapkan email lama tetap berlaku sampai verifikasi selesai.
     *
     * Token baru dibuat setiap permintaan, jadi tautan pada email sebelumnya
     * otomatis tidak berlaku. Yang disimpan hanya hash SHA-256-nya.
     *
     * @return string token asli, hanya untuk dikirim lewat email
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

    /**
     * Kirim tautan konfirmasi ke email BARU dan pemberitahuan ke email LAMA.
     *
     * Dua email dikirim karena dokumen meminta keduanya: tautan konfirmasi ke
     * email baru, dan "pemberitahuan perubahan dikirim ke email lama"
     * (FR-AUTH-06 #7). Pemberitahuan ke email lama adalah pengaman: kalau
     * perubahan dilakukan orang lain, pemilik akun tahu tanpa harus menunggu.
     */
    public function kirimTautanPerubahanEmail(string $token): void
    {
        Mail::to((string) $this->pending_email)->send(new VerifyNewEmailMail($this, $token));
        Mail::to($this->email)->send(new EmailChangeNoticeMail($this, (string) $this->pending_email));
    }

    /**
     * FR-AUTH-06 #5: setiap pengiriman membuat token baru, sehingga
     * tautan pada email sebelumnya tidak berlaku lagi.
     *
     * Yang disimpan hanya hash SHA-256 dari token. Kalau isi tabel bocor,
     * token asli tidak bisa dipakai membajak verifikasi; token asli hanya
     * ada di tautan yang dikirim ke email User.
     */
    public function sendEmailVerificationNotification()
    {
        $token = Str::random(64);

        $this->forceFill(['email_verification_token' => hash('sha256', $token)])->save();

        Mail::to($this->email)->send(new VerifyEmailMail($this, $token));
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
