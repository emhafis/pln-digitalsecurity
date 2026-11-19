<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apar_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apar_id')->constrained('apars')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('security_id')->constrained('securities')->restrictOnDelete();
            $table->timestamp('inspected_at');
            $table->enum('result', ['ok', 'not_ok']);
            $table->text('notes')->nullable();

            $table->string('unit_name_snapshot', 255);
            $table->string('unit_code_snapshot', 50)->nullable();
            $table->string('apar_code_snapshot', 50);
            $table->string('apar_type_snapshot', 30);
            $table->string('brand_snapshot', 100)->nullable();
            $table->string('capacity_snapshot', 50)->nullable();
            $table->string('serial_number_snapshot', 100)->nullable();
            $table->string('placement_snapshot', 255)->nullable();
            $table->date('apar_expiry_date_snapshot')->nullable();

            $table->timestamps();

            $table->index(['apar_id', 'inspected_at']);
            $table->index('unit_id');
            $table->index('security_id');
            $table->index('result');
            $table->index('inspected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apar_inspections');
    }
};
