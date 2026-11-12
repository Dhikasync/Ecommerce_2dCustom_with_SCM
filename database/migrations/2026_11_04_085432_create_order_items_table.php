<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-CKOT-01, FR-CDSN-06, FR-PROD-01 #5. Nama varian/SKU/harga disalin saat
     * checkout sehingga produk boleh dihapus tanpa mengubah pesanan lama.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('variants')->nullOnDelete();
            $table->foreignId('design_id')->nullable()->constrained('designs')->nullOnDelete();
            $table->string('nama_produk');
            $table->string('nama_varian')->nullable();
            $table->string('sku')->nullable();
            $table->integer('quantity');
            $table->integer('harga_satuan');
            $table->integer('sla_hari')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('variant_id');
            $table->index('design_id');
        });

        DB::statement('alter table order_items add constraint order_items_quantity_check check (quantity >= 1)');
        DB::statement('alter table order_items add constraint order_items_harga_satuan_check check (harga_satuan >= 0)');
        DB::statement('alter table order_items add constraint order_items_sla_hari_check check (sla_hari is null or sla_hari > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
