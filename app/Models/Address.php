<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    /**
     * FR-PROF-01: alamat hanya menyimpan data yang diminta SRS.
     * Lokasi mengikuti kode wilayah; label lokasi disimpan sebagai teks
     * yang ditampilkan ke User dan disalin ke pesanan saat checkout.
     */
    protected $fillable = [
        'user_id',
        'nama_penerima',
        'no_hp',
        'alamat_lengkap',
        'kode_lokasi',
        'label_lokasi',
        'is_utama',
    ];

    protected $casts = [
        'is_utama' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
