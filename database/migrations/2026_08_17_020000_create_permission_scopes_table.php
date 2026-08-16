<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restricts a Role's Spatie permission to specific Games instead of
 * applying everywhere. Deliberately NOT a column added to Spatie's own
 * role_has_permissions pivot (fighting a vendor package's schema is more
 * fragile than a parallel table) — this table only ever *narrows* an
 * existing grant, it never grants a permission by itself.
 *
 * A (role_id, permission) pair with zero rows here means that permission
 * is unrestricted (global) — the default, and what every existing role
 * already behaves as, so this is purely additive: nothing that worked
 * before needs a row here to keep working. A pair with one or more rows
 * means the role only has that permission within the union of those
 * scopes (e.g. two rows = "Palworld OR ARK", not narrowed further).
 *
 * scope_type is a plain string, not a DB enum, so adding 'server' or
 * 'extension' later (per CLAUDE.md's roadmap) is a data change, not a
 * migration.
 */
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('permission_scopes');
    }
};
