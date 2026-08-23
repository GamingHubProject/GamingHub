<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AssetFolder */
class AssetFolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'visibility' => $this->visibility,
            'owner_id' => $this->owner_id,
            'path' => $this->path,
            'created_at' => $this->created_at,
        ];
    }
}
