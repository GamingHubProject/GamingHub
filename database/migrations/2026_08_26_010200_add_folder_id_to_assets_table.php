<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Nullable, restrict-on-delete: null means "unfiled", which is
            // exactly how every Phase 1 asset already behaves (publicly
            // readable, no folder concept existed yet) — so this is a
            // no-op for existing rows, not a backfill. Restrict rather than
            // cascade so deleting a folder can't silently vaporize assets
            // still referenced elsewhere (e.g. a Server Banner config); the
            // admin has to empty or reassign a folder first.
            $table->foreignId('folder_id')->nullable()->after('owner_id')
                ->constrained('asset_folders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });
    }
};
