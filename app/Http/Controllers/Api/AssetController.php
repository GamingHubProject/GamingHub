<?php

namespace App\Http\Controllers\Api;

use App\Assets\AssetThumbnailer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AssetResource;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public read (browsing the library needs no more privilege than browsing
 * games/servers/theme — none of this exposes anything sensitive), writes
 * gated on hasRole('Admin') inline — same pattern as every other mutating
 * endpoint in this app (DashboardWidgetController, ServerLayoutWidgetController),
 * not a narrower assets:*  scope this app has no other use for yet.
 */
class AssetController extends Controller
{
    public function __construct(protected AssetThumbnailer $thumbnailer) {}

    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()->orderByDesc('created_at');

        if ($request->filled('owner_type')) {
            $query->where('owner_type', $request->string('owner_type'));
        }

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->integer('owner_id'));
        }

        if ($request->filled('mime_type')) {
            $query->where('mime_type', $request->string('mime_type'));
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
            'disk_path' => $path,
            'url' => $disk->url($path),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt_text' => $data['alt_text'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return (new AssetResource($asset))->response()->setStatusCode(201);
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
