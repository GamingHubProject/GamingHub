<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Theme extends Model
{
    public const LEVEL_PLATFORM = 'platform';
    public const LEVEL_GAME = 'game';
    public const LEVEL_SERVER = 'server';

    protected $fillable = [
        'name',
        'level',
        'game_id',
        'server_id',
        'tokens',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
