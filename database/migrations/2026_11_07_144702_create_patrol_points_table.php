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
        Schema::create('patrol_points', function (Blueprint $table) {
            $table->id();
            $table->uuid('qr_code')->unique();
            $table->string('name');
            $table->foreignId('security_post_id')
                ->constrained()
                ->restrictOnDelete();
            $table->timestamps();

            $table->index('security_post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_points');
    }
};
