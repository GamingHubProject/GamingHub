<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssetTag extends Model
{
    /** @use HasFactory<\Database\Factories\AssetTagFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_asset_tag');
    }
}
