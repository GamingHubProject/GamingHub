<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PageLayoutWidget */
class PageLayoutWidgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page_layout_id' => $this->page_layout_id,
            'group_widget_id' => $this->group_widget_id,
            'widget_type' => $this->widget_type,
            'config' => $this->config,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
