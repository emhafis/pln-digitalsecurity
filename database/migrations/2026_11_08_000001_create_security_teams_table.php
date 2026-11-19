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
        Schema::create('security_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_post_id')
                ->constrained('security_posts')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['security_post_id', 'code'], 'security_teams_post_code_unique');
            $table->unique(['security_post_id', 'name'], 'security_teams_post_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_teams');
    }
};
