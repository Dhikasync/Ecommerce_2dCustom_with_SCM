<?php

namespace App\Models\Profil;

use App\Models\Auth\User;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    /** FR-PROF-01. `label_lokasi` disalin ke pesanan saat checkout. */
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

    /** `Order` diimpor dari App\Models; tanpa itu relasi menunjuk namespace salah. */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** Kolom eksplisit supaya bentuk JSON sama di GET /profil dan GET /profil/alamat. */
    public function ringkas(): array
    {
        return [
            'id' => $this->getKey(),
            'nama_penerima' => $this->nama_penerima,
            'no_hp' => $this->no_hp,
            'alamat_lengkap' => $this->alamat_lengkap,
            'kode_lokasi' => $this->kode_lokasi,
            'label_lokasi' => $this->label_lokasi,
            'is_utama' => $this->is_utama,
        ];
    }
}
