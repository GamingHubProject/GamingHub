<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DashboardWidget */
class DashboardWidgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dashboard_page_id' => $this->dashboard_page_id,
            'widget_type' => $this->widget_type,
            'config' => $this->config,
            'order' => $this->order,
        ];
    }
}
