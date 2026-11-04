<?php

namespace App\Models;

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
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function template()
    {
        return $this->hasOne(Template::class);
    }
}
