<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['variant_id', 'tipe', 'jumlah_masuk', 'jumlah_keluar', 'stok_fisik_setelah', 'stok_direservasi_setelah', 'alasan', 'referensi'];

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }
}
