<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AssetTagResource;
use App\Models\AssetTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Tags carry no visibility of their own — a tag name (e.g. "winter-event")
 * isn't sensitive, only the assets/folders it's attached to are gated. So
 * the tag list itself is public read, same trust level as browsing assets;
 * only create/delete are Admin-gated, matching AssetController's pattern.
 */
class AssetTagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => AssetTagResource::collection(AssetTag::query()->orderBy('name')->get()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $slug = Str::slug($data['name']);

        $tag = AssetTag::query()->firstOrCreate(['slug' => $slug], ['name' => $data['name'], 'slug' => $slug]);

        return (new AssetTagResource($tag))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, AssetTag $tag): Response
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $tag->delete();

        return response()->noContent();
    }
}
