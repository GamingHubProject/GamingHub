<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    /** @use HasFactory<\Database\Factories\GameFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon_url',
        'status',
        'has_servers',
        'metadata',
        'configuration_schema',
    ];

    protected function casts(): array
    {
        return [
            'has_servers' => 'boolean',
            'metadata' => 'array',
            'configuration_schema' => 'array',
        ];
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function serverGroups(): HasMany
    {
        return $this->hasMany(ServerGroup::class);
    }

    public function maps(): HasMany
    {
        return $this->hasMany(Map::class);
    }

    public function configurationPresets(): HasMany
    {
        return $this->hasMany(ConfigurationPreset::class);
    }

    public function gameExtension(): HasOne
    {
        return $this->hasOne(GameExtension::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class);
    }
}
