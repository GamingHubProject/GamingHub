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
            'position_x' => ['sometimes', 'integer', 'min:0'],
            'position_y' => ['sometimes', 'integer', 'min:0'],
            'width' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'height' => ['sometimes', 'integer', 'min:1'],
        ]);

        $widget = DashboardWidget::create($data);
        // position_x/position_y/width/height are "sometimes" — when the
        // client omits them (a REST client relying on the DB defaults
        // rather than the SPA, which always sends real values), the
        // in-memory model never has those attributes set at all, so it'd
        // serialize them as null instead of the default the row actually
        // got. refresh() pulls back what was really inserted.
        $widget->refresh();

        return (new DashboardWidgetResource($widget))->response()->setStatusCode(201);
    }

    public function update(Request $request, DashboardWidget $widget): DashboardWidgetResource
    {
        abort_unless($widget->page->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'widget_type' => ['sometimes', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'position_x' => ['sometimes', 'integer', 'min:0'],
            'position_y' => ['sometimes', 'integer', 'min:0'],
            'width' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'height' => ['sometimes', 'integer', 'min:1'],
        ]);

        $widget->update($data);

        return new DashboardWidgetResource($widget);
    }
}
