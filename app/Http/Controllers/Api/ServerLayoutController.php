<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ServerLayoutResource;
use App\Models\ServerLayout;
use GamingHub\Core\Models\Server;
use Illuminate\Http\JsonResponse;

/**
 * Public — same visibility as the server itself (ServerController::show).
 * A server's layout row is created lazily on first request rather than at
 * server-creation time, so every existing server (and any created before
 * this feature existed) already "has" one without a backfill migration.
 */
class ServerLayoutController extends Controller
{
    public function show(Server $server): JsonResponse
    {
        $layout = ServerLayout::with('widgets')->firstOrCreate(['server_id' => $server->id]);
        // with('widgets') above only applies on the "found" path — when
        // firstOrCreate has to create the row, the returned instance comes
        // straight from create() without ever running the eager-loaded
        // query, so widgets would serialize as a missing key instead of []
        // on a server's very first layout request. Same fix as
        // DashboardPageController::store().
        $layout->loadMissing('widgets');

        // Not just `new ServerLayoutResource($layout)` — JsonResource's
        // default toResponse() reports 201 whenever the wrapped model's
        // wasRecentlyCreated is true, which firstOrCreate makes true on
        // this row's very first request. That's an internal lazy-init
        // detail, invisible to the API consumer; this is a GET and must
        // always read as 200, whether or not the row already existed.
        return (new ServerLayoutResource($layout))->response()->setStatusCode(200);
    }
}
