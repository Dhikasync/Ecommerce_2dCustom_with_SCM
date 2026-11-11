<?php

namespace App\Models;

use App\Models\Auth\User;
use App\Models\Produk\Variant;
use Illuminate\Database\Eloquent\Model;

class Design extends Model
{
    protected $fillable = [
        'user_id',
        'template_id',
        'variant_id',
        'data_desain',
        'preview_gambar',
        'status',
        'catatan_revisi',
        'versi',
        'batas_revisi_pada',
    ];

    protected $casts = [
        'data_desain' => 'array',
        'versi' => 'integer',
        'batas_revisi_pada' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    /**
     * FR-CDSN-05 #5: riwayat validasi desain per versi.
     */
    public function revisions()
    {
        return $this->hasMany(DesignRevision::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
