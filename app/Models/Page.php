<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'game_id',
        'status',
        'blocks',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
