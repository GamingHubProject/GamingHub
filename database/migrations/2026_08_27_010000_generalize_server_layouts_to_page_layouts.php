<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames server_layouts/server_layout_widgets in place — not a copy into
 * new tables — so every existing row keeps its primary key and no data is
 * moved or re-mapped. subject_type backfills to 'server' for every
 * existing row via the column default, which is exactly correct: every
 * layout that existed before this migration was a server's.
 *
 * subject_id uses 0 as the Home singleton's sentinel rather than leaving
 * it nullable — Postgres treats multiple NULLs as distinct under a unique
 * index, so unique(subject_type, subject_id) wouldn't actually stop two
 * "home" rows from being created if NULL were allowed. 0 is never a real
 * model id, so one plain composite unique index covers all three subject
 * types uniformly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('server_layouts', 'page_layouts');
        Schema::rename('server_layout_widgets', 'page_layout_widgets');

        Schema::table('page_layouts', function (Blueprint $table) {
            $table->dropForeign('server_layouts_server_id_foreign');
            $table->dropUnique('server_layouts_server_id_unique');
        });

        Schema::table('page_layouts', function (Blueprint $table) {
            $table->string('subject_type')->default('server')->after('id');
        });

        Schema::table('page_layouts', function (Blueprint $table) {
            $table->renameColumn('server_id', 'subject_id');
        });

        Schema::table('page_layouts', function (Blueprint $table) {
            $table->unique(['subject_type', 'subject_id']);
        });

        Schema::table('page_layout_widgets', function (Blueprint $table) {
            $table->dropForeign('server_layout_widgets_server_layout_id_foreign');
        });

        Schema::table('page_layout_widgets', function (Blueprint $table) {
            $table->renameColumn('server_layout_id', 'page_layout_id');
        });

        Schema::table('page_layout_widgets', function (Blueprint $table) {
            $table->foreign('page_layout_id')->references('id')->on('page_layouts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Renaming the tables back first — rather than last, the mirror
        // image of up()'s ordering — matters here: Postgres does *not*
        // rename a table's constraints along with the table itself, and
        // Laravel names a newly added constraint after whatever the table
        // is currently called. Adding the FK/unique constraints below
        // before renaming would name them "page_layouts_server_id_..."
        // instead of "server_layouts_server_id_..." — silently breaking a
        // *second* up() run later, which drops constraints by their
        // original up()-created names. Confirmed for real via a rollback
        // -> re-migrate cycle while testing this migration.
        Schema::rename('page_layout_widgets', 'server_layout_widgets');
        Schema::rename('page_layouts', 'server_layouts');

        Schema::table('server_layout_widgets', function (Blueprint $table) {
            $table->dropForeign('page_layout_widgets_page_layout_id_foreign');
        });

        Schema::table('server_layout_widgets', function (Blueprint $table) {
            $table->renameColumn('page_layout_id', 'server_layout_id');
        });

        Schema::table('server_layout_widgets', function (Blueprint $table) {
            $table->foreign('server_layout_id')->references('id')->on('server_layouts')->cascadeOnDelete();
        });

        Schema::table('server_layouts', function (Blueprint $table) {
            $table->dropUnique('page_layouts_subject_type_subject_id_unique');
        });

        // Anything that isn't a 'server' row (a Game/Home layout created
        // after this migration ran) has nowhere to go back to in the old
        // schema — dropped rather than left dangling with a meaningless
        // server_id. Expected to be a no-op immediately after up() on a
        // fresh rollback; only matters if down() runs long after Game/Home
        // layouts have actually been created.
        DB::table('server_layouts')->where('subject_type', '!=', 'server')->delete();

        Schema::table('server_layouts', function (Blueprint $table) {
            $table->dropColumn('subject_type');
        });

        Schema::table('server_layouts', function (Blueprint $table) {
            $table->renameColumn('subject_id', 'server_id');
        });

        Schema::table('server_layouts', function (Blueprint $table) {
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->unique('server_id');
        });
    }
};
