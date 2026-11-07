<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Distributor extends Model
{
    /**
     * Distributor bukan pengguna sistem: tidak ada login dan tidak ada role.
     * Tabel ini hanya buku kontak restock yang diisi Admin SCM (FR-SCM-04 #2).
     */
    protected $fillable = [
        'nama_toko',
        'no_hp',
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * FR-SCM-04 #2: daftar varian yang biasa disuplai distributor ini.
     */
    public function variants()
    {
        return $this->belongsToMany(Variant::class, 'distributor_variants')->withTimestamps();
    }
}
