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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('nama');
            $table->string('file');
            $table->integer('lebar_kanvas');
            $table->integer('tinggi_kanvas');
            $table->integer('area_x')->nullable();
            $table->integer('area_y')->nullable();
            $table->integer('area_lebar')->nullable();
            $table->integer('area_tinggi')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
