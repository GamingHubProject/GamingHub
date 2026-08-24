<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * status is exposed as the raw string the connector/admin set (e.g.
 * "running", "stopped") — badge color/label mapping (ServerStatusBadge) is
 * a Filament-only rendering concern, the SPA does its own presentation.
 *
 * @mixin \GamingHub\Core\Models\Server
 */
class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            // The SPA's server route is slug-based
            // (/games/{slug}/servers/{id}), not id-based — a standalone
            // server-card widget (config: server_id only, no page context
            // to borrow a slug from, see ServerCardWidget) needs this to
            // build its own link without a second /games fetch.
            'game_slug' => $this->whenLoaded('game', fn () => $this->game->slug),
            'server_group_id' => $this->server_group_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'max_players' => $this->max_players,
            'current_players' => $this->current_players,
            'cpu_current' => $this->cpu_current,
            'cpu_limit' => $this->cpu_limit,
            'cpu_percent' => $this->cpu_percent,
            'memory_current' => $this->memory_current,
            'memory_limit' => $this->memory_limit,
            'memory_percent' => $this->memory_percent,
            'disk_current' => $this->disk_current,
            'disk_limit' => $this->disk_limit,
            'disk_percent' => $this->disk_percent,
            'network_rx' => $this->network_rx,
            'network_tx' => $this->network_tx,
            'node_name' => $this->node_name,
            'supported_features' => $this->supported_features,
            'game_version' => $this->game_version,
            'last_polled_at' => $this->last_polled_at,
            'allocations' => ServerAllocationResource::collection($this->whenLoaded('allocations')),
        ];
    }
}
