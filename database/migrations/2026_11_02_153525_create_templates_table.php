<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** FR-CDSN-01: kanvas dan area kustomisasi (canvas boundary) per template. */
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
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

            $table->index('product_id');
        });

        DB::statement('alter table templates add constraint templates_kanvas_check check (lebar_kanvas > 0 and tinggi_kanvas > 0)');
        DB::statement('alter table templates add constraint templates_area_check check (area_lebar is null or area_lebar > 0)');
        DB::statement('alter table templates add constraint templates_area_tinggi_check check (area_tinggi is null or area_tinggi > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
