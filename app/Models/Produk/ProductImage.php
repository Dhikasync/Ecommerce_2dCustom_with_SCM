<?php

namespace App\Models\Produk;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    /** Galeri detail produk (FR-KTLG-02 #1); foto utama tetap `products.gambar`. */
    protected $fillable = [
        'product_id',
        'gambar',
        'urutan',
    ];

    protected $casts = [
        'urutan' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
