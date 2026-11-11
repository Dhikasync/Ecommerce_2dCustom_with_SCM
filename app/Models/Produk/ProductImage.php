<?php

namespace App\Models\Produk;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    /**
     * FR-KTLG-02 #1: galeri gambar pada detail produk.
     * `products.gambar` tetap menjadi foto utama pada kartu katalog.
     */
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
