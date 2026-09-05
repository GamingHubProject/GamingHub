<?php

namespace App\Http\Controllers\Api;

use App\Experience\NavigationTargets;
use App\Experience\ThemeResolver;
use App\Http\Controllers\Controller;
use App\Models\NavigationLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The public site's navigation. Read is open — it's what every visitor's
 * header and sidebar render from; writing is Admin-only.
 */
class NavigationController extends Controller
{
    /**
     * The whole tree, with URLs already resolved, in the one shape both
     * the header and the sidebar consume. Same data, two renderings — a
     * folder becomes a dropdown in one and an expandable section in the
     * other, which is a rendering decision rather than a data one.
     */
    public function index(Request $request, NavigationTargets $targets, ThemeResolver $themes): JsonResponse
    {
        $surface = $this->effectiveSurface($request->string('surface', NavigationLink::SURFACE_HEADER), $themes);
        $byParent = NavigationLink::ordered($surface)->groupBy('parent_id');

        return response()->json(['data' => $this->build($byParent, null, $targets)]);
    }

    /**
     * Which surface's rows actually answer for the one being asked about.
     *
     * Mirroring is a pointer, not a copy: while the sidebar follows the
     * header it owns no rows, and asking for the sidebar has to return the
     * header's tree. Resolving that here rather than in the client means
     * every consumer — and every future one — gets it right for free.
     */
    private function effectiveSurface(string $requested, ThemeResolver $themes): string
    {
        $requested = in_array($requested, NavigationLink::SURFACES, true)
            ? $requested
            : NavigationLink::SURFACE_HEADER;

        $mirror = $themes->siteChrome($themes->effectiveTheme())['nav_mirror'] ?? 'none';

        if ($mirror === 'sidebar_follows_header' && $requested === NavigationLink::SURFACE_SIDEBAR) {
            return NavigationLink::SURFACE_HEADER;
        }
        if ($mirror === 'header_follows_sidebar' && $requested === NavigationLink::SURFACE_HEADER) {
            return NavigationLink::SURFACE_SIDEBAR;
        }

        return $requested;
    }

    private function build($byParent, ?int $parentId, NavigationTargets $targets): array
    {
        return $byParent->get($parentId, collect())
            ->filter(fn (NavigationLink $link) => $link->is_visible)
            ->map(function (NavigationLink $link) use ($byParent, $targets) {
                $children = $this->build($byParent, $link->id, $targets);

                return [
                    'id' => $link->id,
                    'type' => $link->type,
                    'label' => $link->label,
                    'url' => $targets->resolve($link),
                    'icon_url' => $link->icon?->url,
                    'children' => $children,
                ];
            })
            // A page link whose target was deleted resolves to null, and
            // rendering it would be a dead link in the site's main nav.
            // Folders legitimately have no URL, so they're kept as long as
            // they still hold something.
            ->reject(fn (array $node) => $node['type'] === NavigationLink::TYPE_FOLDER
                ? $node['children'] === []
                : $node['url'] === null)
            ->values()
            ->all();
    }

    /**
     * The editor's view: the raw tree including hidden links and unresolved
     * targets, which the public endpoint deliberately strips.
     */
    public function edit(Request $request, NavigationTargets $targets, ThemeResolver $themes): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Admin'), 403);

        $requested = $request->string('surface', NavigationLink::SURFACE_HEADER)->toString();
        $requested = in_array($requested, NavigationLink::SURFACES, true) ? $requested : NavigationLink::SURFACE_HEADER;
        $mirror = $themes->siteChrome($themes->effectiveTheme())['nav_mirror'] ?? 'none';
        $effective = $this->effectiveSurface($requested, $themes);

        $byParent = NavigationLink::ordered($effective)->groupBy('parent_id');

        $build = function ($parentId) use (&$build, $byParent) {
            return $byParent->get($parentId, collect())->map(fn (NavigationLink $link) => [
                'id' => $link->id,
                'type' => $link->type,
                'label' => $link->label,
                'target_type' => $link->target_type,
                'target_id' => $link->target_id,
                'url' => $link->url,
                'icon_asset_id' => $link->icon_asset_id,
                'icon_url' => $link->icon?->url,
                'is_visible' => $link->is_visible,
                'children' => $build($link->id),
            ])->values()->all();
        };

        // Deliberately NOT keyed 'data': the SPA's api client unwraps a
        // lone `data` envelope, which would hand the caller the tree and
        // silently discard `targets`. Two things come back here, so
        // neither gets the envelope name.
        return response()->json([
            'tree' => $build(null),
            'surface' => $requested,
            // When these differ the requested surface is a mirror: it owns
            // no rows and is showing the other's. The editor renders it
            // read-only rather than letting an admin type into edits that
            // would be saved against a surface they didn't choose.
            'effective_surface' => $effective,
            'mirror' => $mirror,
            // Bundled with the tree so the editor has everything it needs
            // to render a target picker in one request.
            'targets' => $targets->grouped(),
        ]);
    }

    /**
     * Replace the whole tree. A drag renumbers both the old siblings and
     * the new ones, so a partial update would mean the client computing a
     * diff — this way the editor sends what it has and the server owns the
     * reconciliation.
     */
    public function replace(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Admin'), 403);

        // Every field a node carries has to be listed, not just the
        // structural ones: validate() returns *only* what it validated, so
        // an unlisted `id` would be silently dropped and every link
        // recreated on save — losing the ids a reorder is supposed to
        // preserve.
        $data = $request->validate([
            'tree' => ['present', 'array'],
            ...$this->nodeRules('tree.*'),
            'tree.*.children' => ['sometimes', 'array'],
            ...$this->nodeRules('tree.*.children.*'),
        ]);

        $surface = $request->string('surface', NavigationLink::SURFACE_HEADER)->toString();
        $surface = in_array($surface, NavigationLink::SURFACES, true) ? $surface : NavigationLink::SURFACE_HEADER;

        // Writing to a surface that is currently mirroring another would
        // save rows nothing renders, then silently lose them the next time
        // the leader is edited.
        abort_if(
            $this->effectiveSurface($surface, app(ThemeResolver::class)) !== $surface,
            422,
            'That surface is mirroring the other one. Turn mirroring off before editing it separately.'
        );

        NavigationLink::replaceTree($data['tree'], $surface);

        return $this->edit($request, app(NavigationTargets::class), app(ThemeResolver::class));
    }

    /**
     * One node's rules, at whatever depth. Built for each level rather
     * than written twice, so the two can't drift — the nesting cap is what
     * makes "two levels" a finite list in the first place.
     *
     * @return array<string, array<int, mixed>>
     */
    private function nodeRules(string $prefix): array
    {
        return [
            "{$prefix}.id" => ['sometimes', 'nullable', 'integer', Rule::exists('navigation_links', 'id')],
            "{$prefix}.type" => ['required', Rule::in([
                NavigationLink::TYPE_PAGE, NavigationLink::TYPE_LINK, NavigationLink::TYPE_FOLDER,
            ])],
            "{$prefix}.label" => ['required', 'string', 'max:255'],
            "{$prefix}.target_type" => ['sometimes', 'nullable', 'string', 'max:32'],
            "{$prefix}.target_id" => ['sometimes', 'nullable', 'integer'],
            // A relative path is as valid here as an absolute URL — an
            // admin linking to "/rules" shouldn't be forced to spell out
            // their own domain.
            "{$prefix}.url" => ['sometimes', 'nullable', 'string', 'max:2048'],
            "{$prefix}.icon_asset_id" => ['sometimes', 'nullable', 'integer', Rule::exists('assets', 'id')],
            "{$prefix}.is_visible" => ['sometimes', 'boolean'],
        ];
    }
}
