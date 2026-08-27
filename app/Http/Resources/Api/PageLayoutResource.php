<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PageLayout */
class PageLayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'font_asset_id' => $this->font_asset_id,
            'widgets' => PageLayoutWidgetResource::collection($this->whenLoaded('widgets')),
        ];
    }
}
