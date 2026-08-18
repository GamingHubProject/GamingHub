<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ServerResource;
use GamingHub\Core\Models\Server;

class ServerController extends Controller
{
    public function show(Server $server): ServerResource
    {
        return new ServerResource($server->load('allocations'));
    }
}
