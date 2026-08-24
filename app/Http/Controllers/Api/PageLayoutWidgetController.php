<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PageLayoutWidgetResource;
use App\Models\PageLayout;
use App\Models\PageLayoutWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every mutating action here is Admin-role gated, not ownership gated —
 * same as before the page_layouts generalization. Subject-agnostic: a
 * widget row already points at its PageLayout via page_layout_id, so
 * store() is the only method that needs a subject at all, and even that's
 * just "which layout" — never "which kind of subject". The frontend
 * always fetches the layout (PageLayoutController) before adding a widget,
 * so it already has the real layout id by the time it calls this.
 */
class PageLayoutWidgetController extends Controller
{
    public function store(Request $request, PageLayout $layout): JsonResponse
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $data = $request->validate([
            'widget_type' => ['required', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'position_x' => ['sometimes', 'integer', 'min:0'],
            'position_y' => ['sometimes', 'integer', 'min:0'],
            'width' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'height' => ['sometimes', 'integer', 'min:1'],
        ]);

        $data['page_layout_id'] = $layout->id;

        $widget = PageLayoutWidget::create($data);
        // Omitted position/size fields need a refresh to reflect the DB
        // defaults the row actually got, instead of serializing as null.
        $widget->refresh();

        return (new PageLayoutWidgetResource($widget))->response()->setStatusCode(201);
    }

    public function update(Request $request, PageLayoutWidget $widget): PageLayoutWidgetResource
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $data = $request->validate([
            'widget_type' => ['sometimes', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'position_x' => ['sometimes', 'integer', 'min:0'],
            'position_y' => ['sometimes', 'integer', 'min:0'],
            'width' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'height' => ['sometimes', 'integer', 'min:1'],
        ]);

        $widget->update($data);

        return new PageLayoutWidgetResource($widget);
    }

    public function destroy(Request $request, PageLayoutWidget $widget): Response
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $widget->delete();

        return response()->noContent();
    }
}
