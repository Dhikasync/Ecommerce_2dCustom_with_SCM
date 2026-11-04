<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'distributor_id',
        'nomor_po',
        'status',
        'total_biaya',
        'estimasi_tanggal_kirim',
        'alasan_pembatalan',
    ];

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
