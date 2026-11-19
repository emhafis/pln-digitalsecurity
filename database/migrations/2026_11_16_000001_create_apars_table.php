<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('apar_code', 50)->unique();
            $table->enum('apar_type', ['dry_chemical', 'co2', 'foam', 'air', 'powder']);
            $table->string('brand', 100);
            $table->string('capacity', 50);
            $table->string('serial_number', 100)->nullable();
            $table->date('procurement_date')->nullable();
            $table->date('installation_date')->nullable();
            $table->date('recharge_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('placement', 255);
            $table->uuid('qr_code')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('photo', 255)->nullable();
            $table->timestamps();

            $table->index('unit_id');
            $table->index('is_active');
            $table->index('apar_type');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apars');
    }
};
