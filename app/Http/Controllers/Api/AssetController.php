<?php

namespace App\Http\Controllers\Api;

use App\Assets\AssetThumbnailer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AssetResource;
use App\Models\Asset;
use App\Models\AssetFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public read (browsing the library needs no more privilege than browsing
 * games/servers/theme — none of this exposes anything sensitive), writes
 * gated on hasRole('Admin') inline — same pattern as every other mutating
 * endpoint in this app (DashboardWidgetController, ServerLayoutWidgetController),
 * not a narrower assets:*  scope this app has no other use for yet.
 *
 * "Public" is now scoped by folder visibility (visibleTo — see Asset's
 * docblock): an anonymous/non-admin request only ever sees unfiled assets
 * plus assets in folders they're allowed into, so this class-level comment
 * is no longer literally "everyone sees everything" but the auth model
 * (open read, gated write) hasn't changed.
 */
class AssetController extends Controller
{
    public function __construct(protected AssetThumbnailer $thumbnailer) {}

    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()->with('tags')->visibleTo($request->user())->orderByDesc('created_at');

        if ($request->filled('owner_type')) {
            $query->where('owner_type', $request->string('owner_type'));
        }

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->integer('owner_id'));
        }

        if ($request->filled('mime_type')) {
            $query->where('mime_type', $request->string('mime_type'));
        }

        if ($request->has('folder_id')) {
            $query->where('folder_id', $request->integer('folder_id') ?: null);
        }

        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('asset_tags.id', $request->integer('tag_id')));
        }

        $paginator = $query->paginate(min($request->integer('per_page', 24), 100));

        // Not Laravel's default paginated-resource shape: the SPA's shared
        // api.get() unconditionally unwraps one top-level "data" key
        // (client.ts's request()), which would silently drop the
        // paginator's links/meta if this were the top-level response.
        // Nesting items+meta inside "data" survives that unwrap intact.
        return response()->json([
            'data' => [
                'items' => AssetResource::collection($paginator->items()),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', config('assets.allowed_mimes')),
                'max:'.config('assets.max_size_kb'),
            ],
            'owner_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner_id' => ['sometimes', 'nullable', 'integer'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'folder_id' => ['sometimes', 'nullable', 'integer', Rule::exists('asset_folders', 'id')],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('asset_tags', 'id')],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];
        $mimeType = $file->getMimeType();

        [$width, $height] = $this->dimensions($file, $mimeType);

        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $path = 'assets/'.now()->format('Y/m').'/'.Str::random(16).'.'.$extension;
        $bytes = file_get_contents($file->getRealPath());

        // Thumbnail generated before anything touches disk — if it throws
        // (a corrupt-but-validation-passing file), nothing has been
        // written yet, so there's no orphaned original to clean up.
        $thumbnail = $mimeType !== 'image/svg+xml'
            ? $this->thumbnailer->make($bytes, $mimeType, config('assets.thumbnail.max_width'), config('assets.thumbnail.max_height'))
            : null;

        $disk = Storage::disk(config('assets.disk'));
        $disk->put($path, $bytes);

        if ($thumbnail !== null) {
            $disk->put(Asset::thumbnailPathFor($path), $thumbnail);
        }

        $asset = Asset::create([
            'owner_type' => $data['owner_type'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'folder_id' => $data['folder_id'] ?? null,
            'disk_path' => $path,
            'url' => $disk->url($path),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt_text' => $data['alt_text'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        if (! empty($data['tag_ids'])) {
            $asset->tags()->sync($data['tag_ids']);
        }

        return (new AssetResource($asset->load('tags')))->response()->setStatusCode(201);
    }

    /**
     * Covers the Asset Library management view's rename/move/tag actions —
     * split from store() rather than overloading it, since an update never
     * touches the underlying file, only these metadata columns.
     */
    public function update(Request $request, Asset $asset): AssetResource
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $data = $request->validate([
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'folder_id' => ['sometimes', 'nullable', 'integer', Rule::exists('asset_folders', 'id')],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('asset_tags', 'id')],
        ]);

        $asset->fill(collect($data)->except('tag_ids')->all());
        $asset->save();

        if (array_key_exists('tag_ids', $data)) {
            $asset->tags()->sync($data['tag_ids']);
        }

        return new AssetResource($asset->load('tags'));
    }

    public function destroy(Request $request, Asset $asset): Response
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $disk = Storage::disk(config('assets.disk'));
        $disk->delete($asset->disk_path);

        if ($asset->hasThumbnail()) {
            $disk->delete($asset->thumbnailPath());
        }

        $asset->delete();

        return response()->noContent();
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function dimensions(UploadedFile $file, string $mimeType): array
    {
        if ($mimeType === 'image/svg+xml') {
            return [null, null];
        }

        $info = @getimagesize($file->getRealPath());

        if ($info === false) {
            throw ValidationException::withMessages(['file' => 'Could not read image dimensions — the file may be corrupt.']);
        }

        [$width, $height] = $info;
        $max = config('assets.max_dimension_px');

        if ($width > $max || $height > $max) {
            throw ValidationException::withMessages([
                'file' => "Image dimensions ({$width}x{$height}) exceed the maximum allowed ({$max}px).",
            ]);
        }

        return [$width, $height];
    }
}
