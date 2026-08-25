<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ServerGroup */
class ServerGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            // Same reasoning as ServerResource's game_slug — server-group-
            // card links to its parent Game Detail page (no dedicated
            // group page exists), and the SPA's game route is slug-based.
            'game_slug' => $this->whenLoaded('game', fn () => $this->game->slug),
            'name' => $this->name,
            'description' => $this->description,
            // Both always eager-loaded by the controller
            // (withCount('servers') + an aliased withCount for the
            // running subset) — unlike GameResource's servers_count,
            // there's no whenCounted-style optional path here since
            // ServerGroupController's one read endpoint always loads both.
            'servers_count' => $this->servers_count,
            'running_count' => $this->running_count,
        ];
    }
}
