<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Asset */
class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'folder_id' => $this->folder_id,
            'tags' => AssetTagResource::collection($this->whenLoaded('tags')),
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnailUrl(),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'alt_text' => $this->alt_text,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at,
        ];
    }

    private function thumbnailUrl(): string
    {
        if (! $this->resource->hasThumbnail()) {
            return $this->url;
        }

        return Storage::disk(config('assets.disk'))->url($this->resource->thumbnailPath());
    }
}
