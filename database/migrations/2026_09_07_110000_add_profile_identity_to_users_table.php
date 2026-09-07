<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public presentation, kept separate from login identity: `name` and
 * `email` stay the account credentials and are untouched here.
 *
 * `display_name` is nullable and falls back to `name` when unset, so every
 * existing account keeps working with no backfill and nobody is forced to
 * pick a second name before their profile renders. Its uniqueness is
 * enforced by a *functional* index over lower(display_name) rather than a
 * plain unique constraint — without that, "Rose" and "rose" are two
 * accounts that look identical in every interface that shows them, which
 * is an impersonation vector rather than a cosmetic problem. That makes
 * this the one Postgres-specific migration in the app, which is a safe
 * trade: postgres is what docker-compose ships, what phpunit.xml points
 * at, and what production runs.
 *
 * The legacy `avatar` URL column is deliberately left in place. Nothing
 * has ever written it, but dropping a column to save nothing is a bad
 * trade against losing whatever a production row happens to hold; it
 * stays as a read-only fallback for when avatar_asset_id is null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_name', 50)->nullable()->after('name');
            $table->foreignId('avatar_asset_id')->nullable()->after('avatar')->constrained('assets')->nullOnDelete();
            $table->boolean('profile_public')->default(true)->after('bio');
        });

        DB::statement('CREATE UNIQUE INDEX users_display_name_lower_unique ON users (lower(display_name))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_display_name_lower_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['avatar_asset_id']);
            $table->dropColumn(['display_name', 'avatar_asset_id', 'profile_public']);
        });
    }
};
