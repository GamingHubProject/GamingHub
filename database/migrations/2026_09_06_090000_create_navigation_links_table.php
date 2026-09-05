<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public site's navigation — the links a visitor sees in the header
 * and the sidebar.
 *
 * NOT to be confused with `navigation_items`, which despite the name is
 * Navcom: one row per *Filament admin panel* navigation group, seeded
 * once, reorderable but never created or deleted, read by
 * AdminPanelProvider to build the admin sidebar. Different audience,
 * different lifecycle, deliberately a separate table.
 *
 * Nesting is a plain adjacency list. The tree is tens of rows, and a
 * drag-and-drop reorder rewrites it wholesale anyway, so the write-heavy
 * alternatives (nested set, materialised path) would buy read performance
 * this never needs while making every move cost a bulk update. Keeping
 * each link a real row is also what lets `icon_asset_id` be a foreign key
 * and lets "which links point at this game" stay an answerable question —
 * a single JSON blob would foreclose both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('navigation_links')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('type'); // page | link | folder
            $table->string('label');

            // A page link stores WHAT it points at, never a resolved URL:
            // a stored "/games/phantom-galaxies" breaks silently the day
            // someone renames that game's slug, and target_type+target_id
            // cannot. The Page model sets the same precedent by walking
            // ancestors instead of keeping a path column.
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();

            // External links only.
            $table->string('url')->nullable();

            // Nullable rather than constrained: deleting an icon from the
            // Asset Library shouldn't take the navigation link with it.
            $table->unsignedBigInteger('icon_asset_id')->nullable();

            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_links');
    }
};
