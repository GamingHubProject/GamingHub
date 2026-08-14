<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameExtension extends Model
{
    /** @use HasFactory<\Database\Factories\GameExtensionFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id',
        'slug',
        'name',
        'version',
        'status',
        'description',
        'manifest',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
