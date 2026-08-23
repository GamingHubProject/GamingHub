<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "assets" table already existed — from this project's very first
 * commit (v0.1.000), never touched again. Confirmed dead before writing
 * this: no model, no controller, nothing anywhere in app/ references it,
 * and the table itself is empty. Rather than picking a different name to
 * dodge it, this properly claims it for the real Asset Library feature —
 * an alter, not a fresh create, since the table (empty or not) already
 * exists and dropping+recreating would be indistinguishable in effect but
 * misleading in intent (this isn't new, it's finishing what v0.1.000
 * scaffolded and never built).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['name', 'asset_id', 'path', 'origin', 'mimetype', 'filesize', 'permissions']);
        });

        Schema::table('assets', function (Blueprint $table) {
            // Provenance/scoping only ("uploaded from this Game's edit
            // page"), not an exclusivity lock — an asset is reused by
            // whatever else references its id/url (a widget config, a
            // Game's icon, ...), which never mutates this row. Null means
            // uploaded to the general library, usable anywhere. Same
            // morph convention as CapabilityBinding.subject_type/subject_id.
            $table->nullableMorphs('owner');
            $table->string('disk_path');
            $table->string('url');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropMorphs('owner');
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropColumn(['disk_path', 'url', 'mime_type', 'size', 'width', 'height', 'alt_text']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->string('name');
            $table->string('asset_id')->unique();
            $table->string('path');
            $table->string('origin')->default('system');
            $table->string('mimetype')->nullable();
            $table->unsignedBigInteger('filesize')->nullable();
            $table->string('permissions')->default('public');
        });
    }
};
