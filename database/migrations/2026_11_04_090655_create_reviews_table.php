<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-ORDR-05. Rating 1-5, satu ulasan per item pesanan (unique
     * `order_item_id`), dan ulasan boleh diedit (`is_edited`).
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->cascadeOnDelete();
            $table->integer('rating');
            $table->text('komentar');
            $table->boolean('is_edited')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('product_id');
        });

        DB::statement('alter table reviews add constraint reviews_rating_check check (rating between 1 and 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
