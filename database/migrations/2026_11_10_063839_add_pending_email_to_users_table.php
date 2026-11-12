<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-AUTH-06 #7, FR-PROF-01 #8. Email lama tetap berlaku sampai konfirmasi;
     * token disimpan sebagai hash, masa berlaku ikut tanda tangan URL (24 jam).
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
