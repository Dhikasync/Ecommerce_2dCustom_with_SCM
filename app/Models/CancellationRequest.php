<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancellationRequest extends Model
{
    /**
     * FR-ORDR-02 #2: User mengajukan pembatalan untuk pesanan yang sudah Lunas.
     * FR-ORDR-06: Admin Ecommerce memutuskan permohonan tersebut.
     */
    protected $fillable = [
        'order_id',
        'user_id',
        'alasan',
        'status',
        'stok_dikembalikan',
        'alasan_penolakan',
        'diputuskan_oleh',
    ];

    protected $casts = [
        'stok_dikembalikan' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }
}
