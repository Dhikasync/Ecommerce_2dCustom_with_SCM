<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'metode',
        'jumlah',
        'status',
        'transaction_id',
        'bukti_bayar',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
