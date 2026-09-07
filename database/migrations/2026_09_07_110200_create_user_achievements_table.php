<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a person has earned. `achievement_key` is a string rather than a
 * foreign key to a definitions table: there are no achievements to define
 * yet, and a definitions table with no owner and no consumer is
 * speculative structure. A future table joins cleanly on
 * (source, achievement_key) when something real needs it — which is also
 * why `source` namespaces the key here exactly as it does on user_stats.
 *
 * `earned_at` is separate from created_at on purpose: an integration
 * backfilling history knows when the thing actually happened, and that is
 * the date a profile should show, not the date the row was written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 64);
            $table->string('achievement_key', 64);
            $table->timestamp('earned_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source', 'achievement_key']);
            $table->index(['source', 'achievement_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
    }
};
