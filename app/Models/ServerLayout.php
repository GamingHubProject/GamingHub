<?php

namespace App\Models;

use GamingHub\Core\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per server, shared by every viewer of that server's detail page
 * — not user-owned like DashboardPage. Read access is public (matches the
 * server itself); write access is Admin-role gated, not identity gated.
 * See ServerLayoutController (public read) and ServerLayoutWidgetController
 * (Admin-only writes).
 */
class ServerLayout extends Model
{
    protected $fillable = [
        'server_id',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(ServerLayoutWidget::class);
    }
}
