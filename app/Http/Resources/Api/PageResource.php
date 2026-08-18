<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Page */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'game_id' => $this->game_id,
            'status' => $this->status,
            'content' => $this->content,
            'path' => implode('/', $this->pathSegments()),
        ];
    }
}
