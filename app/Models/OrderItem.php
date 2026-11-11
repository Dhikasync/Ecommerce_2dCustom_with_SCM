<?php

namespace App\Models;

use App\Models\Produk\Variant;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    /**
     * Nama produk, nama varian, dan SKU disalin saat checkout supaya
     * FR-PROD-01 #5 tetap terpenuhi: produk boleh dihapus, riwayat pesanan
     * masih menampilkan apa yang dibeli.
     */
    protected $fillable = [
        'order_id',
        'variant_id',
        'design_id',
        'nama_produk',
        'nama_varian',
        'sku',
        'quantity',
        'harga_satuan',
        'sla_hari',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'harga_satuan' => 'integer',
        'sla_hari' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function design()
    {
        return $this->belongsTo(Design::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
