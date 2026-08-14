<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('game_extensions', 'installed_packages');

        Schema::table('installed_packages', function (Blueprint $table) {
            $table->timestamp('installed_at')->nullable()->after('manifest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installed_packages', function (Blueprint $table) {
            $table->dropColumn('installed_at');
        });

        Schema::rename('installed_packages', 'game_extensions');
    }
};
