import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import GridLayout, { WidthProvider, type Layout } from 'react-grid-layout';

const ResponsiveGridLayout = WidthProvider(GridLayout);
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import { DashboardWidgetContainer } from '../components/DashboardWidgetContainer';
import { AddWidgetModal } from '../components/AddWidgetModal';
import { WidgetConfigModal } from '../components/WidgetConfigModal';
import { getWidgetDefinition } from '../widgets/registry';
import type { DashboardPage, DashboardWidget } from '../api/types';

const GRID_COLS = 12;
const ROW_HEIGHT = 80;
const DEFAULT_WIDGET_WIDTH = 6;
const DEFAULT_WIDGET_HEIGHT = 4;

function layoutFor(widgets: DashboardWidget[]): Layout[] {
  return widgets.map((widget) => ({
    i: String(widget.id),
    x: widget.position_x,
    y: widget.position_y,
    w: widget.width,
    h: widget.height,
  }));
}

// Places a newly added widget below everything already on the page,
// left-aligned — react-grid-layout's own vertical compaction takes it from
// there once the user starts dragging things around.
function nextWidgetPosition(widgets: DashboardWidget[]): { x: number; y: number } {
  const bottom = widgets.reduce((max, w) => Math.max(max, w.position_y + w.height), 0);
  return { x: 0, y: bottom };
}

export function Dashboard() {
  const { user, isLoading: authLoading } = useAuth();
  const api = useApi();
  const queryClient = useQueryClient();

  const [activePageId, setActivePageId] = useState<number | null>(null);
  const [addingWidget, setAddingWidget] = useState(false);
  const [editingWidget, setEditingWidget] = useState<DashboardWidget | null>(null);

  const { data: pages, isLoading: pagesLoading } = useQuery({
    queryKey: ['dashboard', 'pages'],
    queryFn: () => api.get<DashboardPage[]>('/api/v1/dashboard/pages'),
    enabled: !!user,
  });

  const activePage = pages?.find((p) => p.id === activePageId) ?? pages?.[0] ?? null;

  const createPageMutation = useMutation({
    mutationFn: () => api.post<DashboardPage>('/api/v1/dashboard/pages', { title: 'My Dashboard' }),
    onSuccess: (page) => {
      queryClient.setQueryData<DashboardPage[]>(['dashboard', 'pages'], (prev) => [...(prev ?? []), page]);
      setActivePageId(page.id);
    },
  });

  const addWidgetMutation = useMutation({
    mutationFn: (type: string) => {
      const definition = getWidgetDefinition(type);
      const { x, y } = nextWidgetPosition(activePage?.widgets ?? []);
      return api.post<DashboardWidget>('/api/v1/dashboard/widgets', {
        dashboard_page_id: activePage?.id,
        widget_type: type,
        config: definition?.defaultConfig ?? {},
        position_x: x,
        position_y: y,
        width: DEFAULT_WIDGET_WIDTH,
        height: DEFAULT_WIDGET_HEIGHT,
      });
    },
    onSuccess: (widget) => {
      queryClient.setQueryData<DashboardPage[]>(['dashboard', 'pages'], (prev) =>
        prev?.map((page) => (page.id === widget.dashboard_page_id ? { ...page, widgets: [...page.widgets, widget] } : page))
      );
      setAddingWidget(false);
    },
  });

  const updateWidgetConfigMutation = useMutation({
    mutationFn: ({ id, config }: { id: number; config: Record<string, unknown> }) =>
      api.patch<DashboardWidget>(`/api/v1/dashboard/widgets/${id}`, { config }),
    onMutate: async ({ id, config }) => {
      const previous = queryClient.getQueryData<DashboardPage[]>(['dashboard', 'pages']);
      queryClient.setQueryData<DashboardPage[]>(['dashboard', 'pages'], (prev) =>
        prev?.map((page) => ({
          ...page,
          widgets: page.widgets.map((w) => (w.id === id ? { ...w, config } : w)),
        }))
      );
      return { previous };
    },
    onError: (_error, _vars, context) => {
      if (context?.previous) {
        queryClient.setQueryData(['dashboard', 'pages'], context.previous);
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
        changes.map(({ id, position_x, position_y, width, height }) =>
          api.patch(`/api/v1/dashboard/widgets/${id}`, { position_x, position_y, width, height })
        )
      ),
    onError: () => {
      queryClient.invalidateQueries({ queryKey: ['dashboard', 'pages'] });
    },
  });

  // Shared by onDragStop and onResizeStop: react-grid-layout hands back the
  // *entire* layout either way (its own vertical auto-compaction can shift
  // widgets other than the one actually dragged/resized), so every item
  // gets diffed against current state rather than just the one the event
  // was for.
  function persistLayout(layout: Layout[]) {
    if (!activePage) return;

    const changes: LayoutChange[] = [];

    for (const item of layout) {
      const widget = activePage.widgets.find((w) => String(w.id) === item.i);
      if (!widget) continue;

      if (widget.position_x !== item.x || widget.position_y !== item.y || widget.width !== item.w || widget.height !== item.h) {
        changes.push({ id: widget.id, position_x: item.x, position_y: item.y, width: item.w, height: item.h });
      }
    }

    if (changes.length === 0) return;

    queryClient.setQueryData<DashboardPage[]>(['dashboard', 'pages'], (prev) =>
      prev?.map((page) =>
        page.id !== activePage.id
          ? page
          : {
              ...page,
              widgets: page.widgets.map((w) => {
                const change = changes.find((c) => c.id === w.id);
                return change ? { ...w, position_x: change.position_x, position_y: change.position_y, width: change.width, height: change.height } : w;
              }),
            }
      )
    );

    layoutMutation.mutate(changes);
  }

  if (authLoading) return <p>Loading…</p>;
  if (!user) return <p>You need to be logged in to view your dashboard.</p>;
  if (pagesLoading) return <p>Loading dashboard…</p>;

  if (!activePage) {
    return (
      <div>
        <p>No dashboard pages yet.</p>
        <button onClick={() => createPageMutation.mutate()} disabled={createPageMutation.isPending}>
          {createPageMutation.isPending ? 'Creating…' : '+ Create dashboard'}
        </button>
      </div>
    );
  }

  return (
    <div>
      <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
        {pages?.map((page) => (
          <button
            key={page.id}
            onClick={() => setActivePageId(page.id)}
            style={{ fontWeight: page.id === activePage.id ? 'bold' : 'normal' }}
          >
            {page.title}
          </button>
        ))}
        <button onClick={() => setAddingWidget(true)}>+ Add widget</button>
      </div>

      <ResponsiveGridLayout
        className="layout"
        layout={layoutFor(activePage.widgets)}
        cols={GRID_COLS}
        rowHeight={ROW_HEIGHT}
        draggableHandle=".widget-drag-handle"
        draggableCancel=".widget-no-drag"
        resizeHandles={['se']}
        onDragStop={persistLayout}
        onResizeStop={persistLayout}
      >
        {activePage.widgets.map((widget) => (
          <div key={widget.id}>
            <DashboardWidgetContainer widget={widget} onEdit={() => setEditingWidget(widget)} />
          </div>
        ))}
      </ResponsiveGridLayout>

      {addingWidget && (
        <AddWidgetModal onClose={() => setAddingWidget(false)} onAdd={(type) => addWidgetMutation.mutate(type)} />
      )}

      {editingWidget && (
        <WidgetConfigModal
          widget={editingWidget}
          onClose={() => setEditingWidget(null)}
          onSave={(config) => updateWidgetConfigMutation.mutate({ id: editingWidget.id, config })}
        />
      )}
    </div>
  );
}
