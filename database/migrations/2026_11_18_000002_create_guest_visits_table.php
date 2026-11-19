<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('guest_book_qr_code', 36);
            $table->string('full_name', 255);
            $table->string('phone_number', 20);
            $table->string('company_name', 255);
            $table->string('position', 255);
            $table->string('visit_purpose', 500);
            $table->string('person_to_meet', 255);
            $table->string('vehicle_plate_number', 20)->nullable();
            $table->timestamp('check_in_at');
            $table->timestamp('check_out_at')->nullable();
            $table->string('checkout_source', 20)->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('signature_path', 255)->nullable();
            $table->string('selfie_path', 255)->nullable();
            $table->boolean('consent_given')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->string('unit_name_snapshot', 255);
            $table->string('unit_code_snapshot', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('unit_id', 'idx_guest_visits_unit_id');
            $table->index('check_in_at', 'idx_guest_visits_check_in_at');
            $table->index(['unit_id', 'check_in_at'], 'idx_guest_visits_unit_check_in');
            $table->index('check_out_at', 'idx_guest_visits_check_out_at');
            $table->index(['unit_id', 'check_out_at'], 'idx_guest_visits_unit_check_out');
            $table->index(['unit_id', 'phone_number', 'check_out_at'], 'idx_guest_visits_unit_phone_active');
            $table->index('full_name', 'idx_guest_visits_full_name');
            $table->index('company_name', 'idx_guest_visits_company_name');
            $table->index('checked_out_by', 'idx_guest_visits_checked_out_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_visits');
    }
};
