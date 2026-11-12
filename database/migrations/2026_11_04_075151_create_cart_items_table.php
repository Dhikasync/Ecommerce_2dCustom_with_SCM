<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-CART-01 #2. Dua partial unique index karena PostgreSQL menganggap NULL
     * berbeda; item custom memakai design_id, item jadi tidak.
     */
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->cascadeOnDelete();
            $table->foreignId('design_id')->nullable()->constrained('designs')->cascadeOnDelete();
            $table->integer('quantity');
            $table->integer('harga_satuan');
            $table->timestamps();

            $table->index('cart_id');
            $table->index('variant_id');
            $table->index('design_id');
        });

        DB::statement('create unique index cart_items_produk_jadi_unique on cart_items (cart_id, variant_id) where design_id is null');
        DB::statement('create unique index cart_items_produk_custom_unique on cart_items (cart_id, variant_id, design_id) where design_id is not null');
        DB::statement('alter table cart_items add constraint cart_items_quantity_check check (quantity >= 1)');
        DB::statement('alter table cart_items add constraint cart_items_harga_satuan_check check (harga_satuan >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
