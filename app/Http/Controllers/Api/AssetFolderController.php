<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AssetFolderResource;
use App\Models\AssetFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Folder CRUD is Admin-only across the board (create/rename/move/delete).
 * index() is public, same trust level as AssetController#index — visibleTo
 * degrades gracefully for an anonymous (null) user to "public folders
 * only", so an unauthenticated visitor just never sees admin_only/
 * user_private names rather than being blocked outright.
 */
class AssetFolderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $folders = AssetFolder::query()
            ->visibleTo($request->user())
            ->orderBy('path')
            ->get();

        return response()->json(['data' => AssetFolderResource::collection($folders)]);
    }

    /**
     * The Theme font system's dedicated folder — lazily created the first
     * time anything asks for it (same idiom PageLayout already uses for
     * Home/the games list), not a migration-time seeder. No "protected
     * from deletion" flag: if an admin deletes it, the next call here just
     * recreates it, same resilience model as a PageLayout row.
     */
    public function fonts(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Admin'), 403);

        $folder = AssetFolder::firstOrCreate(
            ['parent_id' => null, 'slug' => 'fonts'],
            ['name' => 'Fonts', 'visibility' => 'admin_only', 'path' => AssetFolder::buildPath(null, 'fonts'), 'created_by' => $request->user()->id]
        );

        return (new AssetFolderResource($folder))->response()->setStatusCode(200);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $data = $request->validate([
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('asset_folders', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['required', Rule::in(['public', 'admin_only', 'user_private'])],
            'owner_id' => [
                Rule::requiredIf(fn () => $request->input('visibility') === 'user_private'),
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ]);

        $parent = isset($data['parent_id']) ? AssetFolder::findOrFail($data['parent_id']) : null;
        $slug = Str::slug($data['name']);

        $folder = AssetFolder::create([
            'parent_id' => $parent?->id,
            'name' => $data['name'],
            'slug' => $slug,
            'visibility' => $data['visibility'],
            'owner_id' => $data['owner_id'] ?? null,
            'path' => AssetFolder::buildPath($parent, $slug),
            'created_by' => $request->user()->id,
        ]);

        return (new AssetFolderResource($folder))->response()->setStatusCode(201);
    }

    public function update(Request $request, AssetFolder $folder): AssetFolderResource
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('asset_folders', 'id')],
        ]);

        $renaming = array_key_exists('name', $data);
        $moving = array_key_exists('parent_id', $data);

        if (! $renaming && ! $moving) {
            return new AssetFolderResource($folder);
        }

        $newParent = $moving
            ? ($data['parent_id'] !== null ? AssetFolder::findOrFail($data['parent_id']) : null)
            : $folder->parent;

        if ($moving && $newParent !== null) {
            abort_if($newParent->id === $folder->id, 422, 'A folder cannot be moved into itself.');
            abort_if(Str::startsWith($newParent->path.'/', $folder->path.'/'), 422, 'A folder cannot be moved into its own descendant.');
        }

        $newSlug = $renaming ? Str::slug($data['name']) : $folder->slug;
        $oldPath = $folder->path;
        $newPath = AssetFolder::buildPath($newParent, $newSlug);

        $folder->update([
            'name' => $data['name'] ?? $folder->name,
            'slug' => $newSlug,
            'parent_id' => $newParent?->id,
            'path' => $newPath,
        ]);

        if ($oldPath !== $newPath) {
            // Every descendant's stored path is prefixed with the old path;
            // rewriting them here keeps the materialized-path column
            // (path's whole reason to exist — see the migration) accurate
            // without a recursive query at read time.
            foreach (AssetFolder::query()->where('path', 'like', $oldPath.'/%')->get() as $descendant) {
                $descendant->update(['path' => $newPath.Str::after($descendant->path, $oldPath)]);
            }
        }

        return new AssetFolderResource($folder);
    }

    public function destroy(Request $request, AssetFolder $folder): Response
    {
        abort_unless($request->user()->hasRole('Admin'), 403);
        abort_if($folder->children()->exists(), 422, 'Delete or move this folder\'s subfolders first.');
        abort_if($folder->assets()->exists(), 422, 'Move or delete this folder\'s assets first.');

        $folder->delete();

        return response()->noContent();
    }
}
