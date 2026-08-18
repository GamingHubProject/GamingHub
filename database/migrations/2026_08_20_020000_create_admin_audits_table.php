<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable, append-only — no updated_at (see AdminAudit::UPDATED_AT).
 * user_id is nullable with nullOnDelete() rather than cascading: deleting
 * the acting user's account must never delete their own audit trail, so
 * user_name/user_email are also denormalized directly onto the row at
 * write time rather than only reachable through the user_id relation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('action');
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip')->nullable();
            $table->string('country', 2)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('action');
            $table->index('resource_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audits');
    }
};
