<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationItem extends Model
{
    protected $fillable = [
        'key',
        'label',
        'order',
        'is_favorite',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_favorite' => 'boolean',
        ];
    }

    /**
     * Favorites first (each other ordered by their own 'order'), then
     * everyone else by 'order' — the effective sidebar sequence
     * AdminPanelProvider reads.
     */
    public static function inSidebarOrder()
    {
        return static::query()->orderByDesc('is_favorite')->orderBy('order')->get();
    }
}
