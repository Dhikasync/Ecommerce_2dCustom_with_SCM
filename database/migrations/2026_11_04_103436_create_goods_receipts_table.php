<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-SCM-03 #4: jumlah aktual yang diterima, tanggal, dan catatan selisih
     * bila berbeda dari PO.
     */
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->integer('jumlah_diterima');
            $table->date('tanggal_terima');
            $table->text('catatan_selisih')->nullable();
            $table->timestamps();

            $table->index('purchase_order_item_id');
        });

        DB::statement('alter table goods_receipts add constraint goods_receipts_jumlah_check check (jumlah_diterima >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
