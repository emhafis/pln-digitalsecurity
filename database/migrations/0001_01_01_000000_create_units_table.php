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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->index()->constrained('units')->restrictOnDelete();
            $table->string('unit_code')->unique()->nullable();
            $table->string('unit_name')->unique();
            $table->enum('unit_level', ['uid', 'up', 'ulp']);
            $table->boolean('is_active')->default(true);
            $table->string('guest_book_qr_code', 36)->unique()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
