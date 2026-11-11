<?php

namespace App\Models\Produk;

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

    /**
     * FR-CDSN-01: satu produk custom dapat memiliki lebih dari satu template
     * desain (mis. ukuran kanvas berbeda), jadi relasinya hasMany — bukan hasOne.
     */
    public function templates()
    {
        return $this->hasMany(Template::class);
    }

    /**
     * FR-KTLG-02 #1: galeri gambar pada halaman detail produk, tampil
     * berurutan sesuai kolom `urutan`.
     */
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
