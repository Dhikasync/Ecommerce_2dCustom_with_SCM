<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** FR-SCM-03 #1: varian, quantity restock, dan harga beli per item PO. */
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            // FR-PROD-01 #5: varian boleh dihapus, jadi tautannya dilepas dan
            // identitas barang disalin ke baris PO agar riwayat tetap terbaca.
            $table->foreignId('variant_id')->nullable()->constrained('variants')->nullOnDelete();
            $table->string('nama_produk');
            $table->string('nama_varian')->nullable();
            $table->string('sku')->nullable();

            $table->integer('quantity');
            $table->integer('harga_beli');
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('variant_id');
        });

        DB::statement('alter table purchase_order_items add constraint purchase_order_items_quantity_check check (quantity >= 1)');
        DB::statement('alter table purchase_order_items add constraint purchase_order_items_harga_beli_check check (harga_beli >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
