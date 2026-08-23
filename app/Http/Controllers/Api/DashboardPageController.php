<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DashboardPageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardPageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $pages = $request->user()->dashboardPages()->with('widgets')->get();

        return DashboardPageResource::collection($pages);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $page = $request->user()->dashboardPages()->create($data);
        // whenLoaded('widgets') in the resource needs this — a brand new
        // page has none, but without an explicit (empty) load the key
        // comes back missing entirely rather than [], breaking any client
        // code that assumes page.widgets is always an array.
        $page->load('widgets');

        return (new DashboardPageResource($page))->response()->setStatusCode(201);
    }
}
