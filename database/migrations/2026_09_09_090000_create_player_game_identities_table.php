<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a user to their per-game player identity via a one-way hash.
 *
 * game_slug is a plain string, not a FK to games.id — the link belongs to
 * the user and survives a game row being deleted or recreated. Same
 * principle as user_stats' subject_type/subject_id: data about the person
 * outlives the thing it refers to.
 *
 * The raw player ID (Steam ID, in-game name, etc.) is never stored. Only
 * an HMAC-SHA256 hash keyed with APP_KEY goes into hashed_player_id. The
 * second index supports PlayerIdentityResolver's batch lookup:
 * WHERE game_slug = ? AND hashed_player_id IN (?, ?, ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_game_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game_slug', 64);
            $table->string('hashed_player_id', 64);
            $table->timestamps();

            $table->unique(['user_id', 'game_slug']);
            $table->index(['game_slug', 'hashed_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_game_identities');
    }
};
