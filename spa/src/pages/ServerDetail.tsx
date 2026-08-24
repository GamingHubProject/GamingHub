import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import GridLayout, { WidthProvider, type Layout } from 'react-grid-layout';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import { useThemeScope } from '../providers/ThemeProvider';
import { ServerLayoutWidgetContainer } from '../components/ServerLayoutWidgetContainer';
import { AddServerLayoutWidgetModal } from '../components/AddServerLayoutWidgetModal';
import { ServerLayoutWidgetConfigModal } from '../components/ServerLayoutWidgetConfigModal';
import { getServerLayoutWidgetDefinition } from '../widgets/serverLayout/registry';
import type { Server, ServerLayout, ServerLayoutWidget } from '../api/types';

const ResponsiveGridLayout = WidthProvider(GridLayout);
const GRID_COLS = 12;
const ROW_HEIGHT = 80;

function layoutFor(widgets: ServerLayoutWidget[]): Layout[] {
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
 * The grid runs with allowOverlap (see below) so Name/Status can be
 * dragged onto the Banner — but that flag is grid-wide, not per-widget, so
 * without this check any two widgets could be dragged on top of each
 * other. A dropped layout is only accepted when every overlapping pair is
 * exactly one layerable widget over the layerTarget (server-banner); any
 * other overlapping pair (two layerables, a layerable over a non-target,
 * two non-layerables, ...) rejects the whole drop.
 */
export function isValidOverlapLayout(rglLayout: Layout[], widgets: ServerLayoutWidget[]): boolean {
  const typeById = new Map(widgets.map((w) => [String(w.id), w.widget_type]));

  for (let i = 0; i < rglLayout.length; i++) {
    for (let j = i + 1; j < rglLayout.length; j++) {
      const a = rglLayout[i];
      const b = rglLayout[j];
      if (!rectsOverlap(a, b)) continue;

      const defA = getServerLayoutWidgetDefinition(typeById.get(a.i) ?? '');
      const defB = getServerLayoutWidgetDefinition(typeById.get(b.i) ?? '');
      const aOverBanner = defA?.layerable && defB?.layerTarget;
      const bOverBanner = defB?.layerable && defA?.layerTarget;

      if (!aOverBanner && !bOverBanner) {
        return false;
      }
    }
  }

  return true;
}

/**
 * Which widgets are *currently* sitting on top of the banner — used for
 * chrome-stripping (see ServerLayoutWidgetContainer's `layered` prop), not
 * drag validity (that's isValidOverlapLayout, a separate concern that only
 * runs during a drag). Any layerable widget overlapping any layerTarget
 * counts; isValidOverlapLayout already guarantees that's the *only* kind
 * of overlap that can exist in a committed layout, so no extra pairing
 * check is needed here.
 */
export function layeredWidgetIds(widgets: ServerLayoutWidget[]): Set<number> {
  const rects = widgets.map((w) => ({ widget: w, rect: layoutFor([w])[0] }));
  const targets = rects.filter(({ widget }) => getServerLayoutWidgetDefinition(widget.widget_type)?.layerTarget);
  const ids = new Set<number>();

  for (const { widget, rect } of rects) {
    if (!getServerLayoutWidgetDefinition(widget.widget_type)?.layerable) continue;
    if (targets.some((t) => rectsOverlap(rect, t.rect))) {
      ids.add(widget.id);
    }
  }

  return ids;
}

function nextWidgetPosition(widgets: ServerLayoutWidget[]): { x: number; y: number } {
  const bottom = widgets.reduce((max, w) => Math.max(max, w.position_y + w.height), 0);
  return { x: 0, y: bottom };
}

export function ServerDetail() {
  const { id } = useParams<{ id: string }>();
  const api = useApi();
  const { user } = useAuth();
  const queryClient = useQueryClient();

  const [editMode, setEditMode] = useState(false);
  const [addingWidget, setAddingWidget] = useState(false);
  const [editingWidget, setEditingWidget] = useState<ServerLayoutWidget | null>(null);
  // Bumped to force-remount the grid when a drag is rejected (see
  // persistLayout) — react-grid-layout keeps its own internal layout state
  // once a drag ends, so simply leaving the `layout` prop unchanged
  // wouldn't visually revert the widget the user just dropped in an
  // invalid spot; remounting is the reliable way to discard it.
  const [gridResetKey, setGridResetKey] = useState(0);

  const { data: server, isLoading: serverLoading } = useQuery({
    queryKey: ['server', id],
    queryFn: () => api.get<Server>(`/api/v1/servers/${id}`),
    enabled: !!id,
    refetchInterval: 30_000,
  });

  const { data: layout, isLoading: layoutLoading } = useQuery({
    queryKey: ['server-layout', id],
    queryFn: () => api.get<ServerLayout>(`/api/v1/servers/${id}/layout`),
    enabled: !!id,
  });

  useThemeScope({ gameId: server?.game_id, serverId: server?.id });

  const isAdmin = user?.is_admin ?? false;

  const addWidgetMutation = useMutation({
    mutationFn: (type: string) => {
      const definition = getServerLayoutWidgetDefinition(type);
      const { x, y } = nextWidgetPosition(layout?.widgets ?? []);
      return api.post<ServerLayoutWidget>(`/api/v1/servers/${id}/layout/widgets`, {
        widget_type: type,
        position_x: x,
        position_y: y,
        width: definition?.defaultWidth ?? 6,
        height: definition?.defaultHeight ?? 4,
      });
    },
    onSuccess: (widget) => {
      queryClient.setQueryData<ServerLayout>(['server-layout', id], (prev) =>
        prev ? { ...prev, widgets: [...prev.widgets, widget] } : prev
      );
      setAddingWidget(false);
    },
  });

  const removeWidgetMutation = useMutation({
    mutationFn: (widgetId: number) => api.delete(`/api/v1/server-layout-widgets/${widgetId}`),
    onMutate: async (widgetId) => {
      const previous = queryClient.getQueryData<ServerLayout>(['server-layout', id]);
      queryClient.setQueryData<ServerLayout>(['server-layout', id], (prev) =>
        prev ? { ...prev, widgets: prev.widgets.filter((w) => w.id !== widgetId) } : prev
      );
      return { previous };
    },
    onError: (_error, _vars, context) => {
      if (context?.previous) {
        queryClient.setQueryData(['server-layout', id], context.previous);
      }
    },
  });

  const updateWidgetConfigMutation = useMutation({
    mutationFn: ({ id: widgetId, config }: { id: number; config: Record<string, unknown> }) =>
      api.patch<ServerLayoutWidget>(`/api/v1/server-layout-widgets/${widgetId}`, { config }),
    onMutate: async ({ id: widgetId, config }) => {
      const previous = queryClient.getQueryData<ServerLayout>(['server-layout', id]);
      queryClient.setQueryData<ServerLayout>(['server-layout', id], (prev) =>
        prev ? { ...prev, widgets: prev.widgets.map((w) => (w.id === widgetId ? { ...w, config } : w)) } : prev
      );
      return { previous };
    },
    onError: (_error, _vars, context) => {
      if (context?.previous) {
        queryClient.setQueryData(['server-layout', id], context.previous);
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
          api.patch(`/api/v1/server-layout-widgets/${widgetId}`, { position_x, position_y, width, height })
        )
      ),
    onError: () => {
      queryClient.invalidateQueries({ queryKey: ['server-layout', id] });
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

    queryClient.setQueryData<ServerLayout>(['server-layout', id], (prev) =>
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

  if (serverLoading || layoutLoading) return <p>Loading…</p>;
  if (!server) return <p>Server not found.</p>;

  return (
    <div>
      {isAdmin && (
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginBottom: 12 }}>
          {editMode && <button onClick={() => setAddingWidget(true)}>+ Add card</button>}
          <button onClick={() => setEditMode((value) => !value)}>{editMode ? 'Done editing' : 'Edit layout'}</button>
        </div>
      )}

      <ResponsiveGridLayout
        key={gridResetKey}
        className="layout"
        layout={layoutFor(layout?.widgets ?? [])}
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
          const layeredIds = layeredWidgetIds(layout?.widgets ?? []);
          return (layout?.widgets ?? []).map((widget) => {
            const definition = getServerLayoutWidgetDefinition(widget.widget_type);
            return (
              // The banner (layerTarget) always paints behind everything
              // else, regardless of DOM/add order, so a layered Name/Status
              // widget is never accidentally hidden underneath it.
              <div key={widget.id} style={{ zIndex: definition?.layerTarget ? 0 : 1 }}>
                <ServerLayoutWidgetContainer
                  widget={widget}
                  server={server}
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

      {addingWidget && (
        <AddServerLayoutWidgetModal onClose={() => setAddingWidget(false)} onAdd={(type) => addWidgetMutation.mutate(type)} />
      )}

      {editingWidget && (
        <ServerLayoutWidgetConfigModal
          widget={editingWidget}
          onClose={() => setEditingWidget(null)}
          onSave={(config) => updateWidgetConfigMutation.mutate({ id: editingWidget.id, config })}
        />
      )}
    </div>
  );
}
