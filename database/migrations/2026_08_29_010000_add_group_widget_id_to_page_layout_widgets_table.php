<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A widget with group_widget_id set is a member of that Group widget — its
 * own position_x/position_y/width/height are then interpreted relative to
 * the group's own inner grid instead of the page's (see PageLayoutEditor's
 * GroupWidgetContainer). No new table for live group membership: the same
 * flat page_layout_widgets list already has everything else a widget
 * needs, and this is one more column, not a parallel structure to keep in
 * sync. cascadeOnDelete — a Group's children have no existence independent
 * of it, so deleting the Group row is enough to clean up its members;
 * PageLayoutWidgetController never has to delete them one at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_layout_widgets', function (Blueprint $table) {
            $table->foreignId('group_widget_id')->nullable()->after('page_layout_id')
                ->constrained('page_layout_widgets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('page_layout_widgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_widget_id');
        });
    }
};
