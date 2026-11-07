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
        'catatan',
        'alasan_pembatalan',
    ];

    protected $casts = [
        'total_biaya' => 'integer',
        'estimasi_tanggal_kirim' => 'date',
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
