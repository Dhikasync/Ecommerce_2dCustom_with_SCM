<?php

namespace App\Models\Produk;

use App\Models\Review;
use App\Models\Template;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'nama',
        'deskripsi',
        'tipe',
        'harga_dasar',
        'gambar',
        'is_aktif',
        'sla_hari',
    ];

    protected $casts = [
        'harga_dasar' => 'integer',
        'is_aktif' => 'boolean',
        'sla_hari' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    /** FR-CDSN-01: satu produk custom boleh punya beberapa template desain. */
    public function templates()
    {
        return $this->hasMany(Template::class);
    }

    /** FR-KTLG-02 #1: galeri detail produk, urut kolom `urutan`. */
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('urutan');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function isCustom(): bool
    {
        return $this->tipe === 'custom';
    }
}
