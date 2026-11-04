<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'address_id',
        'courier_id',
        'voucher_id',
        'nomor_pesanan',
        'status',
        'subtotal_barang',
        'ongkir',
        'diskon_voucher',
        'total',
        'alasan_pembatalan',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasOne(Payment::class);
    }
}
