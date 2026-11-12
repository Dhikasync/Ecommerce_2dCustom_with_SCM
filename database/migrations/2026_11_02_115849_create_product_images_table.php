<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-KTLG-02 #1, SRS 4.4.2: satu produk banyak foto; `products.gambar` tetap
     * foto utama kartu katalog.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('gambar');
            $table->integer('urutan')->default(0);
            $table->timestamps();

            // Galeri selalu dibaca per produk dan berurutan.
            $table->index(['product_id', 'urutan']);
        });

        DB::statement('alter table product_images add constraint product_images_urutan_check check (urutan >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
