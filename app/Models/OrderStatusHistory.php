<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    /** FR-ORDR-01 #2, FR-ORDR-03 #5: status beserta waktu dan pelakunya. */
    protected $fillable = [
        'order_id',
        'status_lama',
        'status_baru',
        'diubah_oleh',
        'catatan',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
