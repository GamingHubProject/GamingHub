<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Map extends Model
{
    /** @use HasFactory<\Database\Factories\MapFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id',
        'name',
        'slug',
        'description',
        'image_url',
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
}
