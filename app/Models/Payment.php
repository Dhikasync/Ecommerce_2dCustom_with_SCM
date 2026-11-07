<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    /**
     * Pembayaran hanya lewat payment gateway. Satu pesanan dapat memiliki
     * beberapa baris percobaan; tidak ada bukti transfer manual.
     */
    protected $fillable = [
        'order_id',
        'snap_token',
        'transaction_id',
        'metode',
        'jumlah',
        'status',
        'kadaluwarsa_pada',
        'dibayar_pada',
        'dikembalikan_pada',
        'jumlah_dikembalikan',
    ];

    protected $casts = [
        'jumlah' => 'integer',
        'jumlah_dikembalikan' => 'integer',
        'kadaluwarsa_pada' => 'datetime',
        'dibayar_pada' => 'datetime',
        'dikembalikan_pada' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
