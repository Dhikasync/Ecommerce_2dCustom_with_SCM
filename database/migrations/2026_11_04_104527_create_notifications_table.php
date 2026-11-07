<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FR-ORDR-01 #3 notifikasi perubahan status (email dan in-app),
     * FR-SCM-02 #2/#3 notifikasi stok menipis dan stok habis.
     * Admin juga User, sehingga notifikasi Admin memakai tabel yang sama.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('judul');
            $table->text('pesan');
            $table->string('tipe')->nullable();
            $table->boolean('is_dibaca')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'is_dibaca']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
