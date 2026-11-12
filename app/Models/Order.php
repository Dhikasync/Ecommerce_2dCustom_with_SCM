<?php

namespace App\Models;

use App\Models\Auth\User;
use App\Models\Profil\Address;
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

        // Salinan alamat saat checkout: pesanan tetap utuh walau alamat dihapus.
        'nama_penerima',
        'no_hp',
        'alamat_pengiriman',
        'label_lokasi',

        'nomor_resi',
        'alasan_pembatalan',
        'kadaluwarsa_pada',
        'dikirim_pada',
        'selesai_pada',
    ];

    protected $casts = [
        'subtotal_barang' => 'integer',
        'ongkir' => 'integer',
        'diskon_voucher' => 'integer',
        'total' => 'integer',
        'kadaluwarsa_pada' => 'datetime',
        'dikirim_pada' => 'datetime',
        'selesai_pada' => 'datetime',
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

    /** FR-PAYM-01 #3: satu pesanan boleh punya beberapa percobaan pembayaran. */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /** Percobaan terakhir yang menentukan status pembayaran pesanan. */
    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** FR-ORDR-01 #2, FR-ORDR-03 #5. */
    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /** FR-ORDR-02 #2, FR-ORDR-06. */
    public function cancellationRequests()
    {
        return $this->hasMany(CancellationRequest::class);
    }
}
