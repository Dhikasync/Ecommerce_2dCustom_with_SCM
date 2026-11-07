<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'variant_id',
        'design_id',
        'quantity',
        'harga_satuan',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'harga_satuan' => 'integer',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function design()
    {
        return $this->belongsTo(Design::class);
    }
}
