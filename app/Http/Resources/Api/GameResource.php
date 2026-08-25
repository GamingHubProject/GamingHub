<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \GamingHub\Core\Models\Game */
class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon_url' => $this->icon_url,
            'status' => $this->status,
            'has_servers' => $this->has_servers,
            // Only ever meaningful when has_servers is true — GameCard
            // shows "External" instead of a count otherwise (see its
            // docblock). Relies on the controller eager-loading the count
            // (Game::withCount('servers')); whenCounted degrades to
            // omitting the key rather than a slow per-row query if a
            // caller ever forgets to.
            'servers_count' => $this->whenCounted('servers'),
            'metadata' => $this->metadata,
        ];
    }
}
