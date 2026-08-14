<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigurationPreset extends Model
{
    /** @use HasFactory<\Database\Factories\ConfigurationPresetFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id',
        'name',
        'values',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
