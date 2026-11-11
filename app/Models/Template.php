<?php

namespace App\Models;

use App\Models\Produk\Product;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = ['product_id', 'nama', 'file', 'lebar_kanvas', 'tinggi_kanvas', 'area_x', 'area_y', 'area_lebar', 'area_tinggi', 'is_aktif'];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function designs()
    {
        return $this->hasMany(Design::class);
    }
}
