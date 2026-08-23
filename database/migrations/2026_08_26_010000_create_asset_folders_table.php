<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('asset_folders')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('visibility', ['public', 'admin_only', 'user_private'])->default('public');
            // Only set (and only meaningful) for visibility = user_private —
            // the user this folder is scoped to. Nullable rather than a
            // separate table since every other visibility value leaves it
            // unused, same nullable-column-for-one-variant pattern as
            // Asset's owner morph.
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            // Denormalized materialized path (e.g. "/maps/palworld") so
            // "everything under this folder" is a LIKE query instead of a
            // recursive CTE — maintained by the model on create/rename/move,
            // not by the database.
            $table->string('path');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_folders');
    }
};
