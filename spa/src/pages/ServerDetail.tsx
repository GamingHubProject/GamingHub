import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import GridLayout, { WidthProvider, type Layout } from 'react-grid-layout';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import { useThemeScope } from '../providers/ThemeProvider';
import { ServerLayoutWidgetContainer } from '../components/ServerLayoutWidgetContainer';
import { AddServerLayoutWidgetModal } from '../components/AddServerLayoutWidgetModal';
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
        className="layout"
        layout={layoutFor(layout?.widgets ?? [])}
        cols={GRID_COLS}
        rowHeight={ROW_HEIGHT}
        isDraggable={editMode}
        isResizable={editMode}
        draggableHandle=".widget-drag-handle"
        draggableCancel=".widget-no-drag"
        resizeHandles={['se']}
        onDragStop={persistLayout}
        onResizeStop={persistLayout}
      >
        {(layout?.widgets ?? []).map((widget) => (
          <div key={widget.id}>
            <ServerLayoutWidgetContainer
              widget={widget}
              server={server}
              editable={editMode}
              onRemove={() => removeWidgetMutation.mutate(widget.id)}
            />
          </div>
        ))}
      </ResponsiveGridLayout>

      {addingWidget && (
        <AddServerLayoutWidgetModal onClose={() => setAddingWidget(false)} onAdd={(type) => addWidgetMutation.mutate(type)} />
      )}
    </div>
  );
}
