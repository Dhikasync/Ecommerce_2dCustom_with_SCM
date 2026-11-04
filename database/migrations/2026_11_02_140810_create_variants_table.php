<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('sku')->unique();
            $table->string('ukuran')->nullable();
            $table->string('warna')->nullable();
            $table->string('ketebalan')->nullable();
            $table->integer('harga');
            $table->integer('berat');
            $table->integer('moq');
            $table->integer('stok_fisik')->default(0);
            $table->integer('stok_direservasi')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
