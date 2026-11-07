<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    /**
     * FR-SCM-03 #4: barang masuk dicatat sesuai jumlah aktual yang diterima,
     * beserta tanggal terima dan catatan selisih bila berbeda dari PO.
     */
    protected $fillable = [
        'purchase_order_item_id',
        'jumlah_diterima',
        'tanggal_terima',
        'catatan_selisih',
    ];

    protected $casts = [
        'jumlah_diterima' => 'integer',
        'tanggal_terima' => 'date',
    ];

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
