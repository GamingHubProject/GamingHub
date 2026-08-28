import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import GridLayout, { WidthProvider, type Layout } from 'react-grid-layout';
import { useApi } from '../providers/ApiClientProvider';
import { PageLayoutWidgetContainer } from './PageLayoutWidgetContainer';
import { AddPageLayoutWidgetModal } from './AddPageLayoutWidgetModal';
import { PageLayoutWidgetConfigModal } from './PageLayoutWidgetConfigModal';
import { getPageLayoutWidgetDefinition } from '../widgets/pageLayout/registry';
import type { PageLayoutWidgetContext } from '../widgets/pageLayout/registry';
import type { AssetFolder, AssetList, PageLayout, PageLayoutWidget } from '../api/types';

const ResponsiveGridLayout = WidthProvider(GridLayout);
const GRID_COLS = 12;
const ROW_HEIGHT = 80;

function layoutFor(widgets: PageLayoutWidget[]): Layout[] {
  return widgets.map((widget) => ({
    i: String(widget.id),
    x: widget.position_x,
    y: widget.position_y,
    w: widget.width,
    h: widget.height,
  }));
}

function rectsOverlap(a: Layout, b: Layout): boolean {
  return a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;
}

/**
 * A widget type being `layerTarget: true` (registry.ts) says the type is
 * *capable* of having other widgets dragged onto it; whether one
 * particular placement actually allows it is a per-instance setting on
 * that widget's own config (e.g. PictureWidgetConfig's allow_layering) —
 * so this checks both. Defaults true when the config doesn't have the key
 * at all (every widget type predating this toggle, or a fresh instance
 * before its config form has been touched), which preserves existing
 * layouts' behavior unchanged.
 */
function isLayerTargetWidget(widget: PageLayoutWidget): boolean {
  if (!getPageLayoutWidgetDefinition(widget.widget_type)?.layerTarget) return false;
  const config = widget.config as { allow_layering?: boolean } | null;
  return config?.allow_layering !== false;
}

/**
 * The grid runs with allowOverlap (see below) so a layerable widget can be
 * dragged onto a layerTarget (e.g. Name/Status onto a Picture) — but that
 * flag is grid-wide, not per-widget, so without this check any two
 * widgets could be dragged on top of each other. A dropped layout is only
 * accepted when every overlapping pair is exactly one layerable widget
 * over a layerTarget; any other overlapping pair (two layerables, a
 * layerable over a non-target, two non-layerables, ...) rejects the whole
 * drop.
 */
export function isValidOverlapLayout(rglLayout: Layout[], widgets: PageLayoutWidget[]): boolean {
  const widgetById = new Map(widgets.map((w) => [String(w.id), w]));

  for (let i = 0; i < rglLayout.length; i++) {
    for (let j = i + 1; j < rglLayout.length; j++) {
      const a = rglLayout[i];
      const b = rglLayout[j];
      if (!rectsOverlap(a, b)) continue;

      const widgetA = widgetById.get(a.i);
      const widgetB = widgetById.get(b.i);
      const defA = getPageLayoutWidgetDefinition(widgetA?.widget_type ?? '');
      const defB = getPageLayoutWidgetDefinition(widgetB?.widget_type ?? '');
      const aOverTarget = defA?.layerable && widgetB && isLayerTargetWidget(widgetB);
      const bOverTarget = defB?.layerable && widgetA && isLayerTargetWidget(widgetA);

      if (!aOverTarget && !bOverTarget) {
        return false;
      }
    }
  }

  return true;
}

/**
 * Which widgets are *currently* sitting on top of a layerTarget — used for
 * chrome-stripping (see PageLayoutWidgetContainer's `layered` prop), not
 * drag validity (that's isValidOverlapLayout, a separate concern that only
 * runs during a drag). Any layerable widget overlapping any layerTarget
 * counts; isValidOverlapLayout already guarantees that's the *only* kind
 * of overlap that can exist in a committed layout, so no extra pairing
 * check is needed here.
 */
export function layeredWidgetIds(widgets: PageLayoutWidget[]): Set<number> {
  const rects = widgets.map((w) => ({ widget: w, rect: layoutFor([w])[0] }));
  const targets = rects.filter(({ widget }) => isLayerTargetWidget(widget));
  const ids = new Set<number>();

  for (const { widget, rect } of rects) {
    if (!getPageLayoutWidgetDefinition(widget.widget_type)?.layerable) continue;
    if (targets.some((t) => rectsOverlap(rect, t.rect))) {
      ids.add(widget.id);
    }
  }

  return ids;
}

function nextWidgetPosition(widgets: PageLayoutWidget[]): { x: number; y: number } {
  const bottom = widgets.reduce((max, w) => Math.max(max, w.position_y + w.height), 0);
  return { x: 0, y: bottom };
}

/**
 * The one field a layout itself has (as opposed to its widgets) — this
 * page's font override. "Sync to global" is the null option, not a
 * separate checkbox — see ThemeResolver::resolveFont's docblock. The
 * Fonts folder is fetched via its own lazily-creating endpoint rather
 * than assumed to already exist (see AssetFolderController::fonts).
 */
function PageFontControl({ layout, queryKey }: { layout: PageLayout; queryKey: unknown[] }) {
  const api = useApi();
  const queryClient = useQueryClient();

  const { data: fontsFolder } = useQuery({
    queryKey: ['asset-folders', 'fonts'],
    queryFn: () => api.get<AssetFolder>('/api/v1/asset-folders/fonts'),
  });

  const { data: fontAssets } = useQuery({
    queryKey: ['assets', { folderId: fontsFolder?.id }],
    queryFn: () => api.get<AssetList>(`/api/v1/assets?folder_id=${fontsFolder!.id}`),
    enabled: !!fontsFolder,
  });

  const updateFontMutation = useMutation({
    mutationFn: (fontAssetId: number | null) =>
      api.patch<PageLayout>(`/api/v1/page-layouts/${layout.id}`, { font_asset_id: fontAssetId }),
    onSuccess: (updated) => {
      queryClient.setQueryData<PageLayout>(queryKey, (prev) => (prev ? { ...prev, font_asset_id: updated.font_asset_id } : prev));
      // ThemeProvider's ['theme', ...] query key is keyed on subjectType/
      // subjectId (which route we're on), not on the layout's own
      // font_asset_id — so it never notices this PATCH on its own.
      // Without this the new/cleared override wouldn't show up until a
      // full reload.
      queryClient.invalidateQueries({ queryKey: ['theme'] });
    },
  });

  return (
    <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: '0.85rem' }}>
      Page font
      <select
        value={layout.font_asset_id ?? ''}
        onChange={(event) => updateFontMutation.mutate(event.target.value ? Number(event.target.value) : null)}
      >
        <option value="">Sync to global</option>
        {fontAssets?.items?.map((asset) => (
          <option key={asset.id} value={asset.id}>
            {asset.alt_text ?? `Font #${asset.id}`}
          </option>
        ))}
      </select>
    </label>
  );
}

/**
 * The one shared "admin-editable widget layout" mechanism for every page
 * type that has one (Server Detail, Game Detail, Home/Portal) — same
 * Edit-layout toggle, same drag/resize/add/remove interaction, same
 * overlap guardrail, parameterized by which page it's on rather than
 * duplicated per page. A caller supplies layoutUrl (which GET endpoint
 * resolves this page's PageLayout — see PageLayoutController, one thin
 * route per subject type) and context (what a widget component gets to
 * read about its subject — e.g. {subjectType:'server', server}); this
 * component never needs to know *which* subject it's editing beyond that.
 */
export function PageLayoutEditor({
  layoutUrl,
  queryKey,
  context,
  isAdmin,
}: {
  layoutUrl: string;
  /** react-query cache key for this page's layout — callers pass their own
   *  so e.g. a Server's and a Game's layouts never collide in the cache. */
  queryKey: unknown[];
  context: PageLayoutWidgetContext;
  isAdmin: boolean;
}) {
  const api = useApi();
  const queryClient = useQueryClient();

  const [editMode, setEditMode] = useState(false);
  const [addingWidget, setAddingWidget] = useState(false);
  const [editingWidget, setEditingWidget] = useState<PageLayoutWidget | null>(null);
  // Bumped to force-remount the grid when a drag is rejected (see
  // persistLayout) — react-grid-layout keeps its own internal layout state
  // once a drag ends, so simply leaving the `layout` prop unchanged
  // wouldn't visually revert the widget the user just dropped in an
  // invalid spot; remounting is the reliable way to discard it.
  const [gridResetKey, setGridResetKey] = useState(0);

  const { data: layout, isLoading } = useQuery({
    queryKey,
    queryFn: () => api.get<PageLayout>(layoutUrl),
  });

  const addWidgetMutation = useMutation({
    mutationFn: (type: string) => {
      const definition = getPageLayoutWidgetDefinition(type);
      const { x, y } = nextWidgetPosition(layout?.widgets ?? []);
      return api.post<PageLayoutWidget>(`/api/v1/page-layouts/${layout!.id}/widgets`, {
        widget_type: type,
        position_x: x,
        position_y: y,
        width: definition?.defaultWidth ?? 6,
        height: definition?.defaultHeight ?? 4,
      });
    },
    onSuccess: (widget) => {
      queryClient.setQueryData<PageLayout>(queryKey, (prev) => (prev ? { ...prev, widgets: [...prev.widgets, widget] } : prev));
      setAddingWidget(false);
    },
  });

  const removeWidgetMutation = useMutation({
    mutationFn: (widgetId: number) => api.delete(`/api/v1/page-layout-widgets/${widgetId}`),
    onMutate: async (widgetId) => {
      const previous = queryClient.getQueryData<PageLayout>(queryKey);
      queryClient.setQueryData<PageLayout>(queryKey, (prev) =>
        prev ? { ...prev, widgets: prev.widgets.filter((w) => w.id !== widgetId) } : prev
      );
      return { previous };
    },
    onError: (_error, _vars, mutationContext) => {
      if (mutationContext?.previous) {
        queryClient.setQueryData(queryKey, mutationContext.previous);
      }
    },
  });

  const updateWidgetConfigMutation = useMutation({
    mutationFn: ({ id: widgetId, config }: { id: number; config: Record<string, unknown> }) =>
      api.patch<PageLayoutWidget>(`/api/v1/page-layout-widgets/${widgetId}`, { config }),
    onMutate: async ({ id: widgetId, config }) => {
      const previous = queryClient.getQueryData<PageLayout>(queryKey);
      queryClient.setQueryData<PageLayout>(queryKey, (prev) =>
        prev ? { ...prev, widgets: prev.widgets.map((w) => (w.id === widgetId ? { ...w, config } : w)) } : prev
      );
      return { previous };
    },
    onError: (_error, _vars, mutationContext) => {
      if (mutationContext?.previous) {
        queryClient.setQueryData(queryKey, mutationContext.previous);
      }
    },
    onSettled: () => {
      setEditingWidget(null);
    },
  });

  type LayoutChange = { id: number; position_x: number; position_y: number; width: number; height: number };

  const layoutMutation = useMutation({
    mutationFn: (changes: LayoutChange[]) =>
      Promise.all(
        changes.map(({ id: widgetId, position_x, position_y, width, height }) =>
          api.patch(`/api/v1/page-layout-widgets/${widgetId}`, { position_x, position_y, width, height })
        )
      ),
    onError: () => {
      queryClient.invalidateQueries({ queryKey });
    },
  });

  // Same reasoning as Dashboard.tsx's persistLayout: react-grid-layout's
  // own auto-compaction can move widgets other than the one actually
  // dragged/resized, so every item is diffed against current state.
  function persistLayout(rglLayout: Layout[]) {
    if (!layout) return;

    if (!isValidOverlapLayout(rglLayout, layout.widgets)) {
      setGridResetKey((key) => key + 1);
      return;
    }

    const changes: LayoutChange[] = [];

    for (const item of rglLayout) {
      const widget = layout.widgets.find((w) => String(w.id) === item.i);
      if (!widget) continue;

      if (widget.position_x !== item.x || widget.position_y !== item.y || widget.width !== item.w || widget.height !== item.h) {
        changes.push({ id: widget.id, position_x: item.x, position_y: item.y, width: item.w, height: item.h });
      }
    }

    if (changes.length === 0) return;

    queryClient.setQueryData<PageLayout>(queryKey, (prev) =>
      prev
        ? {
            ...prev,
            widgets: prev.widgets.map((w) => {
              const change = changes.find((c) => c.id === w.id);
              return change ? { ...w, position_x: change.position_x, position_y: change.position_y, width: change.width, height: change.height } : w;
            }),
          }
        : prev
    );

    layoutMutation.mutate(changes);
  }

  if (isLoading || !layout) return null;

  return (
    <div>
      {isAdmin && (
        <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', gap: 8, marginBottom: 12 }}>
          <PageFontControl layout={layout} queryKey={queryKey} />
          {editMode && <button onClick={() => setAddingWidget(true)}>+ Add widget</button>}
          <button onClick={() => setEditMode((value) => !value)}>{editMode ? 'Done editing' : 'Edit layout'}</button>
        </div>
      )}

      {/* An empty, non-editing layout renders nothing extra — a fresh
          Home/Game page looks exactly as it did before this existed,
          until an admin actually adds a widget. */}
      {(layout.widgets.length > 0 || editMode) && (
        <ResponsiveGridLayout
          key={gridResetKey}
          className="layout"
          layout={layoutFor(layout.widgets)}
          cols={GRID_COLS}
          rowHeight={ROW_HEIGHT}
          isDraggable={editMode}
          isResizable={editMode}
          draggableHandle=".widget-drag-handle"
          draggableCancel=".widget-no-drag"
          resizeHandles={['se']}
          // See isValidOverlapLayout — allowOverlap is a grid-wide switch
          // (nothing native to react-grid-layout scopes it to one pair of
          // widget types), so every other overlapping combination is
          // rejected in persistLayout instead.
          allowOverlap
          onDragStop={persistLayout}
          onResizeStop={persistLayout}
        >
          {(() => {
            const layeredIds = layeredWidgetIds(layout.widgets);
            return layout.widgets.map((widget) => {
              const definition = getPageLayoutWidgetDefinition(widget.widget_type);
              return (
                // A layerTarget always paints behind everything else,
                // regardless of DOM/add order, so a layered widget is
                // never accidentally hidden underneath it.
                <div key={widget.id} style={{ zIndex: definition?.layerTarget ? 0 : 1 }}>
                  <PageLayoutWidgetContainer
                    widget={widget}
                    context={context}
                    editable={editMode}
                    layered={layeredIds.has(widget.id)}
                    onRemove={() => removeWidgetMutation.mutate(widget.id)}
                    onEdit={() => setEditingWidget(widget)}
                  />
                </div>
              );
            });
          })()}
        </ResponsiveGridLayout>
      )}

      {addingWidget && (
        <AddPageLayoutWidgetModal
          subjectType={context.subjectType}
          onClose={() => setAddingWidget(false)}
          onAdd={(type) => addWidgetMutation.mutate(type)}
        />
      )}

      {editingWidget && (
        <PageLayoutWidgetConfigModal
          widget={editingWidget}
          onClose={() => setEditingWidget(null)}
          onSave={(config) => updateWidgetConfigMutation.mutate({ id: editingWidget.id, config })}
        />
      )}
    </div>
  );
}
