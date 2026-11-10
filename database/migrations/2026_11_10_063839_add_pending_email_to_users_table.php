<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-AUTH-06 #7, FR-PROF-01 #8: email baru tidak boleh langsung
     * menggantikan email lama. Sampai tautan konfirmasi diklik, email lama
     * tetap berlaku; karena itu email baru disimpan terpisah di sini.
     *
     * Kolom `pending_email_token` menyimpan hash SHA-256 token, sama seperti
     * `email_verification_token`, sehingga isi tabel yang bocor tidak bisa
     * dipakai mengonfirmasi perubahan email orang lain.
     *
     * Masa berlaku tautan tidak disimpan sebagai kolom: ditangani tanda
     * tangan URL (24 jam), sehingga tidak ada kolom yang bisa jadi basi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email_verification_token');
            $table->string('pending_email_token', 64)->nullable()->after('pending_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'pending_email_token']);
        });
    }
};
