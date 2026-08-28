<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PageLayoutWidgetResource;
use App\Models\PageLayout;
use App\Models\PageLayoutWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every mutating action here is Admin-role gated, not ownership gated —
 * same as before the page_layouts generalization. Subject-agnostic: a
 * widget row already points at its PageLayout via page_layout_id, so
 * store() is the only method that needs a subject at all, and even that's
 * just "which layout" — never "which kind of subject". The frontend
 * always fetches the layout (PageLayoutController) before adding a widget,
 * so it already has the real layout id by the time it calls this.
 */
class PageLayoutWidgetController extends Controller
{
    public function store(Request $request, PageLayout $layout): JsonResponse
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $data = $request->validate([
            'widget_type' => ['required', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'position_x' => ['sometimes', 'integer', 'min:0'],
            'position_y' => ['sometimes', 'integer', 'min:0'],
            'width' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'height' => ['sometimes', 'integer', 'min:1'],
            'group_widget_id' => ['sometimes', 'nullable', 'integer', $this->groupWidgetIdRule($layout->id)],
        ]);

        $this->assertGroupNeverNested($data['widget_type'], $data['group_widget_id'] ?? null);

        $data['page_layout_id'] = $layout->id;

        $widget = PageLayoutWidget::create($data);
        // Omitted position/size fields need a refresh to reflect the DB
        // defaults the row actually got, instead of serializing as null.
        $widget->refresh();

        return (new PageLayoutWidgetResource($widget))->response()->setStatusCode(201);
    }

    public function update(Request $request, PageLayoutWidget $widget): PageLayoutWidgetResource
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $data = $request->validate([
            'widget_type' => ['sometimes', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'position_x' => ['sometimes', 'integer', 'min:0'],
            'position_y' => ['sometimes', 'integer', 'min:0'],
            'width' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'height' => ['sometimes', 'integer', 'min:1'],
            'group_widget_id' => ['sometimes', 'nullable', 'integer', $this->groupWidgetIdRule($widget->page_layout_id, exceptId: $widget->id)],
        ]);

        // A partial update might touch only one of the two fields — always
        // resolve against what the row will actually end up as, not just
        // what this one request happened to send.
        $resolvedType = $data['widget_type'] ?? $widget->widget_type;
        $resolvedGroupId = array_key_exists('group_widget_id', $data) ? $data['group_widget_id'] : $widget->group_widget_id;
        $this->assertGroupNeverNested($resolvedType, $resolvedGroupId);

        $widget->update($data);

        return new PageLayoutWidgetResource($widget);
    }

    public function destroy(Request $request, PageLayoutWidget $widget): Response
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $groupId = $widget->group_widget_id;

        $widget->delete();

        // Empty groups auto-delete — an admin removing a group's members
        // one at a time (rather than via the dedicated Ungroup action,
        // which already deletes the group itself) shouldn't leave a
        // dangling, contentless group widget behind.
        if ($groupId && PageLayoutWidget::where('group_widget_id', $groupId)->doesntExist()) {
            PageLayoutWidget::find($groupId)?->delete();
        }

        return response()->noContent();
    }

    /**
     * A group_widget_id must point at a 'group' widget on the *same*
     * layout, which is itself not nested inside another group — the one
     * DB-level guarantee behind "groups can't contain groups". $exceptId
     * stops a widget from somehow being validated as pointing at itself.
     */
    private function groupWidgetIdRule(int $layoutId, ?int $exceptId = null): Exists
    {
        return Rule::exists('page_layout_widgets', 'id')->where(function ($query) use ($layoutId, $exceptId) {
            $query->where('page_layout_id', $layoutId)
                ->where('widget_type', 'group')
                ->whereNull('group_widget_id');

            if ($exceptId) {
                $query->where('id', '!=', $exceptId);
            }
        });
    }

    /**
     * The other half of "groups can't contain groups": a 'group' widget
     * itself can never carry a group_widget_id, regardless of which of
     * the two fields the current request actually touched — see update()'s
     * resolved-value handling above.
     */
    private function assertGroupNeverNested(string $widgetType, ?int $groupWidgetId): void
    {
        if ($widgetType === 'group' && $groupWidgetId !== null) {
            throw ValidationException::withMessages([
                'group_widget_id' => 'A group widget cannot itself be placed inside another group.',
            ]);
        }
    }
}
