<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hydrant_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hydrant_inspection_id')->constrained('hydrant_inspections')->cascadeOnDelete();
            $table->string('item_name', 50);
            $table->string('item_label', 150);
            $table->boolean('is_good');
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('hydrant_inspection_id');
            $table->unique(['hydrant_inspection_id', 'item_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydrant_inspection_items');
    }
};
