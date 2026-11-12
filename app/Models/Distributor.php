<?php

namespace App\Models;

use App\Models\Produk\Variant;
use Illuminate\Database\Eloquent\Model;

class Distributor extends Model
{
    /** Bukan pengguna sistem (tanpa login/role), hanya kontak restock (FR-SCM-04 #2). */
    protected $fillable = [
        'nama_toko',
        'no_hp',
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** Varian yang biasa disuplai distributor (FR-SCM-04 #2). */
    public function variants()
    {
        return $this->belongsToMany(Variant::class, 'distributor_variants')->withTimestamps();
    }
}
