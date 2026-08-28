<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * widget_type is an opaque string the backend never validates the meaning
 * of (see PageLayoutWidgetController's docblock) — the frontend registry
 * key that used to be 'server-banner' is now 'picture' (it's a generic
 * background-image widget, not Server-specific). Any row already
 * persisted under the old key would otherwise render "Unsupported widget
 * type: server-banner" after this deploy, on every existing Server
 * Detail layout that has a banner. down() reverses it for a rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_layout_widgets')
            ->where('widget_type', 'server-banner')
            ->update(['widget_type' => 'picture']);
    }

    /**
     * Best-effort, not exact: any 'picture' widget added *after* this
     * migration ran (a genuinely new placement, never 'server-banner')
     * gets incorrectly reverted too — there's no way to distinguish the
     * two by value alone. Acceptable for a rollback immediately after a
     * bad deploy; not safe to run once real new picture widgets exist.
     */
    public function down(): void
    {
        DB::table('page_layout_widgets')
            ->where('widget_type', 'picture')
            ->update(['widget_type' => 'server-banner']);
    }
};
