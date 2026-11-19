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
        Schema::create('security_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_team_id')
                ->constrained('security_teams')
                ->restrictOnDelete();
            $table->foreignId('security_id')
                ->constrained('securities')
                ->restrictOnDelete();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->timestamps();

            $table->index(['security_id', 'left_at'], 'security_team_members_security_left_idx');
            $table->index(['security_team_id', 'left_at'], 'security_team_members_team_left_idx');
            $table->unique(
                ['security_team_id', 'security_id', 'joined_at'],
                'security_team_members_team_security_joined_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_team_members');
    }
};
