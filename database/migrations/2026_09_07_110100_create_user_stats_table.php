<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where features record numbers about a person — play hours, kills,
 * sessions. Schema only in this release: nothing writes to it yet beyond
 * the UserStats service, and the profile's Stats widget ships rendering an
 * honest empty state.
 *
 * Two deliberate departures from the shape this was first sketched as
 * ("hours_on_server_5: 47"):
 *
 * The subject comes out of the key. Encoding an id inside the key makes
 * "hours across all servers" a LIKE 'hours_on_server_%' scan with string
 * parsing, and makes a per-server leaderboard impossible to index.
 * subject_type/subject_id with key='hours' turns both into ordinary
 * indexed aggregates. A site-wide stat leaves both null — see the two
 * partial unique indexes below for why that needs handling rather than
 * one plain composite unique.
 *
 * `value` is numeric, not JSON. Stats get summed, sorted, compared and
 * ranked, none of which JSON can do in the database. `metadata` is there
 * for the genuinely unstructured remainder.
 *
 * `source` namespaces every key ('core', 'pelican', 'games.rust'), so a
 * panel integration and a game integration can both record 'hours'
 * without silently overwriting one another.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 64);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('key', 64);
            $table->decimal('value', 20, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Leaderboard shape: "everyone's 'hours' on server 5, ranked".
            $table->index(['subject_type', 'subject_id', 'key']);
        });

        // Two partial unique indexes rather than one over all five columns:
        // Postgres treats every NULL as distinct, so a plain unique index
        // including the nullable subject columns would happily accept the
        // same site-wide stat twice. Splitting on "has a subject" gives
        // both cases a real constraint — which is what makes the service's
        // upsert safe under concurrency.
        DB::statement(
            'CREATE UNIQUE INDEX user_stats_subject_unique ON user_stats (user_id, source, subject_type, subject_id, key) WHERE subject_type IS NOT NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX user_stats_site_wide_unique ON user_stats (user_id, source, key) WHERE subject_type IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stats');
    }
};
