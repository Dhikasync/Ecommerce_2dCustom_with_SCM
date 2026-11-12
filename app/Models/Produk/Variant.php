<?php

namespace App\Models\Produk;

use App\Models\CartItem;
use App\Models\Design;
use App\Models\Distributor;
use App\Models\OrderItem;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'ukuran',
        'warna',
        'ketebalan',
        'harga',
        'berat',
        'moq',
        'stok_fisik',
        'stok_direservasi',
        'stok_minimum',
    ];

    protected $casts = [
        'harga' => 'integer',
        'berat' => 'integer',
        'moq' => 'integer',
        'stok_fisik' => 'integer',
        'stok_direservasi' => 'integer',
        'stok_minimum' => 'integer',
    ];

    /** FR-SCM-01: Tersedia selalu dihitung, tidak pernah disimpan. */
    protected function stokTersedia(): Attribute
    {
        return Attribute::get(
            fn (): int => $this->stok_fisik - $this->stok_direservasi
        );
    }

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

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** FR-SCM-04 #2. */
    public function distributors()
    {
        return $this->belongsToMany(Distributor::class, 'distributor_variants')->withTimestamps();
    }
}
