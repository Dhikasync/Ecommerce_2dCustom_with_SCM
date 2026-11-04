<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    protected $fillable = ['product_id', 'sku', 'ukuran', 'warna', 'ketebalan', 'harga', 'berat', 'moq', 'stok_fisik', 'stok_direservasi'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function designs()
    {
        return $this->hasMany(Design::class);
    }
}
