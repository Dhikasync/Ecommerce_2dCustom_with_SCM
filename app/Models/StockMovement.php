<?php

namespace App\Models;

use App\Models\Produk\Variant;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'variant_id',
        'tipe',
        'jumlah_masuk',
        'jumlah_keluar',
        'stok_fisik_setelah',
        'stok_direservasi_setelah',
        'alasan',
        'referensi',
    ];

    protected $casts = [
        'jumlah_masuk' => 'integer',
        'jumlah_keluar' => 'integer',
        'stok_fisik_setelah' => 'integer',
        'stok_direservasi_setelah' => 'integer',
    ];

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }
}
