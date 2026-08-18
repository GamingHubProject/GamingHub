<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \GamingHub\Core\Models\ServerAllocation */
class ServerAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'ip' => $this->ip,
            'ip_alias' => $this->ip_alias,
            'port' => $this->port,
            'is_default' => $this->is_default,
            'notes' => $this->notes,
        ];
    }
}
