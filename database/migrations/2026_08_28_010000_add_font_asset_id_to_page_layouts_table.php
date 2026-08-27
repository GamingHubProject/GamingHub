<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_layouts', function (Blueprint $table) {
            // NULL means "sync to global" (inherit SiteOption's font_asset_id)
            // — not a separate boolean, the null-ness *is* the sync state.
            // See ThemeResolver::resolveFont().
            $table->foreignId('font_asset_id')->nullable()->after('subject_id')
                ->constrained('assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('page_layouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('font_asset_id');
        });
    }
};
