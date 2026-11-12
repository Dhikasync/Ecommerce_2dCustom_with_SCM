<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-SCM-04 #2: varian yang biasa disuplai distributor; satu pasangan
     * distributor + varian hanya sekali.
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

    public function down(): void
    {
        Schema::dropIfExists('distributor_variants');
    }
};
