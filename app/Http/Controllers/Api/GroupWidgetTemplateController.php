<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\GroupWidgetTemplateResource;
use App\Http\Resources\Api\PageLayoutWidgetResource;
use App\Models\GroupWidgetTemplate;
use App\Models\PageLayout;
use App\Models\PageLayoutWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin-only, editor-only — templates are a layout-building convenience,
 * never rendered to a visitor directly (unlike Asset Library folders,
 * which a non-admin can browse read-only). Every method here is gated the
 * same way PageLayoutWidgetController's writes are.
 */
class GroupWidgetTemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        return GroupWidgetTemplateResource::collection(
            GroupWidgetTemplate::query()->orderBy('name')->get()
        );
    }

    /**
     * Captures a Group widget's *current* state into a standalone
     * snapshot — see the group_widget_templates migration's docblock for
     * why this is a JSON copy rather than a link back to the group. Once
     * saved, the template is completely decoupled: later edits to the
     * source group (or its deletion) never touch this row.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'group_widget_id' => [
                'required',
                'integer',
                Rule::exists('page_layout_widgets', 'id')->where(fn ($query) => $query->where('widget_type', 'group')),
            ],
        ]);

        $group = PageLayoutWidget::with('children')->findOrFail($data['group_widget_id']);

        $template = GroupWidgetTemplate::create([
            'name' => $data['name'],
            'created_by' => $request->user()->id,
            'snapshot' => [
                'width' => $group->width,
                'height' => $group->height,
                'children' => $group->children->map(fn (PageLayoutWidget $child) => [
                    'widget_type' => $child->widget_type,
                    'config' => $child->config,
                    'position_x' => $child->position_x,
                    'position_y' => $child->position_y,
                    'width' => $child->width,
                    'height' => $child->height,
                ])->all(),
            ],
        ]);

        return (new GroupWidgetTemplateResource($template))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, GroupWidgetTemplate $template): JsonResponse
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $template->delete();

        return response()->json(null, 204);
    }

    /**
     * Instantiates a fresh, independent copy of the template on the given
     * layout: one new 'group' widget plus one new widget per snapshot
     * child, none of them referencing the template or each other's
     * origin. Wrapped in a transaction — a template placement is meant to
     * be a reliable "stamp out a known-good copy" operation, so a partial
     * failure (the group created but only some children) would be worse
     * than the whole thing failing outright.
     */
    public function place(Request $request, PageLayout $layout, GroupWidgetTemplate $template): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $created = DB::transaction(function () use ($layout, $template) {
            $snapshot = $template->snapshot;

            $topLevelBottom = PageLayoutWidget::query()
                ->where('page_layout_id', $layout->id)
                ->whereNull('group_widget_id')
                ->get()
                ->reduce(fn (int $max, PageLayoutWidget $w) => max($max, $w->position_y + $w->height), 0);

            $group = PageLayoutWidget::create([
                'page_layout_id' => $layout->id,
                'widget_type' => 'group',
                'position_x' => 0,
                'position_y' => $topLevelBottom,
                'width' => $snapshot['width'],
                'height' => $snapshot['height'],
            ]);

            $children = collect($snapshot['children'])->map(fn (array $child) => PageLayoutWidget::create([
                'page_layout_id' => $layout->id,
                'group_widget_id' => $group->id,
                'widget_type' => $child['widget_type'],
                'config' => $child['config'],
                'position_x' => $child['position_x'],
                'position_y' => $child['position_y'],
                'width' => $child['width'],
                'height' => $child['height'],
            ]));

            return $children->prepend($group);
        });

        return PageLayoutWidgetResource::collection($created);
    }
}
