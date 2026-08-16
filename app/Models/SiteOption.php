<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A singleton row (id=1) holding every site-wide setting as one JSON blob
 * — see the migration's docblock for why not one column per setting.
 * Always go through current()/value(), never Model::first() directly, so
 * "the row doesn't exist yet" is handled in one place.
 */
class SiteOption extends Model
{
    protected $table = 'site_options';

    protected $fillable = [
        'values',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['values' => []]);
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        return static::current()->values[$key] ?? $default;
    }
}
