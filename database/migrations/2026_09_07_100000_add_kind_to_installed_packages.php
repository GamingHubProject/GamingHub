<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an installed package IS, so the list can stop pretending everything
 * in it is loadable code.
 *
 * A theme installed from the registry is recorded here — that is what makes
 * "installed v1.0.0" and the update path work on the Browse Registry page
 * — but it has no enable/disable state, because a theme is applied through
 * a ThemeAssignment rather than switched on. Without this column the list
 * would offer a toggle that does nothing.
 *
 * Defaults to 'extension' so every row that already exists keeps meaning
 * exactly what it meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installed_packages', function (Blueprint $table) {
            $table->string('kind')->default('extension')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('installed_packages', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
