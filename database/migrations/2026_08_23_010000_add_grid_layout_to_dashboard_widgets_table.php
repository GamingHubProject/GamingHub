<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            // Real react-grid-layout position/size, replacing the old
            // "order" column's vertical-stack-only layout. Defaults place a
            // widget at the grid's origin at a medium size — the frontend
            // always sends real values on create (positioned below existing
            // widgets), these only matter as a DB-level fallback.
            $table->unsignedInteger('position_x')->default(0)->after('order');
            $table->unsignedInteger('position_y')->default(0)->after('position_x');
            $table->unsignedInteger('width')->default(6)->after('position_y');
            $table->unsignedInteger('height')->default(4)->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y', 'width', 'height']);
        });
    }
};
