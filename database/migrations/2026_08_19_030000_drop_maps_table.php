<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps is a Hub Extension concern, not core — CODER_BRIEF_V1.md always
 * scoped it out of V1 ("Not in V1: Maps, Trading, or any game-specific
 * extensions"). Removed rather than left as an unused core resource;
 * rebuild later as a real installable Hub Extension package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('maps');
    }

    public function down(): void
    {
        Schema::create('maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['game_id', 'slug']);
        });
    }
};
