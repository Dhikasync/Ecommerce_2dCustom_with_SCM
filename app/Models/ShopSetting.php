<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSetting extends Model
{
    protected $fillable = [
        'alamat_jalan',
        'kode_lokasi',
        'label_lokasi',
    ];
}
