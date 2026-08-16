<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superseded by permissions generated directly per Game/ServerGroup/Server
 * (see ScopedPermissionGenerator) — a role now either holds one of those
 * real Spatie permissions or it doesn't, so the side table that used to
 * *narrow* a global grant to specific games has nothing left to narrow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('permission_scopes');
    }

    public function down(): void
    {
        Schema::create('permission_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id');
            $table->timestamps();

            $table->unique(['role_id', 'permission', 'scope_type', 'scope_id'], 'permission_scopes_unique');
        });
    }
};
