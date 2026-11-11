<?php

namespace App\Models;

use App\Models\Produk\Variant;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    /**
     * Nama produk, nama varian, dan SKU disalin saat PO dibuat supaya
     * FR-PROD-01 #5 tetap terpenuhi: produk/varian boleh dihapus, riwayat PO
     * masih menampilkan barang apa yang dipesan.
     */
    protected $fillable = [
        'purchase_order_id',
        'variant_id',
        'nama_produk',
        'nama_varian',
        'sku',
        'quantity',
        'harga_beli',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
