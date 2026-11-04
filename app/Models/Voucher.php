<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'kode',
        'jenis_potongan',
        'nilai_potongan',
        'batas_maksimal_potongan',
        'minimal_belanja',
        'tanggal_mulai',
        'tanggal_berakhir',
        'kuota_total',
        'batas_pemakaian_per_user',
        'is_aktif',
    ];

    protected $casts = [
        'is_aktif' => 'boolean',
        'tanggal_mulai' => 'date',
        'tanggal_berakhir' => 'date',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
