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
        Schema::create('securities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            // Registrasi
            $table->string('registration_number')->nullable()->unique();

            // Identitas
            $table->string('full_name');
            $table->string('phone_number', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();

            // KTA
            $table->string('kta_number')->nullable()->unique();
            $table->string('kta_file')->nullable();
            $table->date('kta_issued_date');
            $table->date('kta_expired_date')->nullable();

            // Foto Profil
            $table->string('photo')->nullable();

            $table->timestamps();

            $table->index('kta_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('securities');
    }
};
