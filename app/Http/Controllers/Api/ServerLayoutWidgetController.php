<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ServerLayoutWidgetResource;
use App\Models\ServerLayout;
use App\Models\ServerLayoutWidget;
use GamingHub\Core\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every mutating action here is Admin-role gated, not ownership gated —
 * unlike DashboardWidgetController (a widget's owner is whoever owns its
 * page), a server layout has no owner at all. Read access (see
 * ServerLayoutController) is public; only these writes are restricted.
 */
class ServerLayoutWidgetController extends Controller
{
    public function store(Request $request, Server $server): JsonResponse
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

        $layout = ServerLayout::firstOrCreate(['server_id' => $server->id]);
        $data['server_layout_id'] = $layout->id;

        $widget = ServerLayoutWidget::create($data);
        // Same reasoning as DashboardWidgetController::store — omitted
        // position/size fields need a refresh to reflect the DB defaults
        // the row actually got, instead of serializing as null.
        $widget->refresh();

        return (new ServerLayoutWidgetResource($widget))->response()->setStatusCode(201);
    }

    public function update(Request $request, ServerLayoutWidget $widget): ServerLayoutWidgetResource
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

        return new ServerLayoutWidgetResource($widget);
    }

    public function destroy(Request $request, ServerLayoutWidget $widget): Response
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $widget->delete();

        return response()->noContent();
    }
}
