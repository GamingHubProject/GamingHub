<?php

namespace App\Models;

use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerGroup extends Model
{
    /** @use HasFactory<\Database\Factories\ServerGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id',
        'name',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }
}
