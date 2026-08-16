<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Navcom: one row per top-level admin sidebar group. 'key' identifies which
 * built-in Filament navigationGroup a row controls (e.g. "games" ->
 * GameResource's group) — rows are seeded once for every group that
 * exists today and are never created/deleted through the admin UI (see
 * NavigationItemResource, which has no create/delete actions), only
 * reordered and favorited. AdminPanelProvider reads this table to build
 * Panel::navigationGroups() in the order admins set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_favorite')->default(false);
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('navigation_items')->insert(collect([
            ['key' => 'capabilities', 'label' => 'Capabilities', 'order' => 1],
            ['key' => 'extensions', 'label' => 'Extensions', 'order' => 2],
            ['key' => 'games', 'label' => 'Games', 'order' => 3],
            ['key' => 'experience', 'label' => 'Experience', 'order' => 4],
            ['key' => 'servers', 'label' => 'Servers', 'order' => 5],
            ['key' => 'administration', 'label' => 'Administration', 'order' => 6],
            ['key' => 'basic-settings', 'label' => 'Basic Settings', 'order' => 7],
        ])->map(fn (array $row) => $row + [
            'is_favorite' => false,
            'icon' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
