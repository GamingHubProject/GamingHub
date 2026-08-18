<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DashboardWidgetResource;
use App\Models\DashboardWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A widget's owner is derived through its dashboard_page's user_id, not a
 * user_id column of its own — every lookup here is scoped through the
 * requesting user's own pages so patching by ID guess 404s instead of
 * touching another player's dashboard.
 */
class DashboardWidgetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dashboard_page_id' => [
                'required',
                Rule::exists('dashboard_pages', 'id')->where('user_id', $request->user()->id),
            ],
            'widget_type' => ['required', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $widget = DashboardWidget::create($data);

        return (new DashboardWidgetResource($widget))->response()->setStatusCode(201);
    }

    public function update(Request $request, DashboardWidget $widget): DashboardWidgetResource
    {
        abort_unless($widget->page->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'widget_type' => ['sometimes', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $widget->update($data);

        return new DashboardWidgetResource($widget);
    }
}
