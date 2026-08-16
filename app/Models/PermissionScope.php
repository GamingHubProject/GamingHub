<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * One narrowing of a Role's permission to a single Game (or, later,
 * Server) — see the migration's docblock for the "zero rows = global"
 * semantics. Never consulted on its own; always through
 * ScopedPermissionChecker.
 */
class PermissionScope extends Model
{
    protected $fillable = [
        'role_id',
        'permission',
        'scope_type',
        'scope_id',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
