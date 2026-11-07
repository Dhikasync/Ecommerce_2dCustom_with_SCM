<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FR-SCM-04 #2: daftar varian yang biasa disuplai oleh seorang distributor.
     * Satu pasangan distributor + varian hanya boleh tercatat sekali.
     */
    public function up(): void
    {
        Schema::create('distributor_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained('distributors')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['distributor_id', 'variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributor_variants');
    }
};
