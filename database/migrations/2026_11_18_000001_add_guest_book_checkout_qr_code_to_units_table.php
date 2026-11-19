<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('guest_book_checkout_qr_code', 36)->unique()->nullable()->after('guest_book_qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['guest_book_checkout_qr_code']);
            $table->dropColumn('guest_book_checkout_qr_code');
        });
    }
};
