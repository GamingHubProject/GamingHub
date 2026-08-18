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

function layoutFor(widgets: DashboardWidget[]): Layout[] {
  return [...widgets]
    .sort((a, b) => a.order - b.order)
    .map((widget, index) => ({
      i: String(widget.id),
      x: 0,
      y: index * 4,
      w: GRID_COLS,
      h: 4,
    }));
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

  const addWidgetMutation = useMutation({
    mutationFn: (type: string) => {
      const definition = getWidgetDefinition(type);
      const nextOrder = (activePage?.widgets.length ?? 0) + 1;
      return api.post<DashboardWidget>('/api/v1/dashboard/widgets', {
        dashboard_page_id: activePage?.id,
        widget_type: type,
        config: definition?.defaultConfig ?? {},
        order: nextOrder,
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

  const reorderMutation = useMutation({
    mutationFn: (changes: { id: number; order: number }[]) =>
      Promise.all(changes.map(({ id, order }) => api.patch(`/api/v1/dashboard/widgets/${id}`, { order }))),
    onError: () => {
      queryClient.invalidateQueries({ queryKey: ['dashboard', 'pages'] });
    },
  });

  function handleDragStop(layout: Layout[]) {
    if (!activePage) return;

    const sorted = [...layout].sort((a, b) => a.y - b.y || a.x - b.x);
    const changes: { id: number; order: number }[] = [];

    sorted.forEach((item, index) => {
      const widget = activePage.widgets.find((w) => String(w.id) === item.i);
      if (widget && widget.order !== index) {
        changes.push({ id: widget.id, order: index });
      }
    });

    if (changes.length === 0) return;

    queryClient.setQueryData<DashboardPage[]>(['dashboard', 'pages'], (prev) =>
      prev?.map((page) =>
        page.id !== activePage.id
          ? page
          : {
              ...page,
              widgets: page.widgets.map((w) => {
                const change = changes.find((c) => c.id === w.id);
                return change ? { ...w, order: change.order } : w;
              }),
            }
      )
    );

    reorderMutation.mutate(changes);
  }

  if (authLoading) return <p>Loading…</p>;
  if (!user) return <p>You need to be logged in to view your dashboard.</p>;
  if (pagesLoading) return <p>Loading dashboard…</p>;
  if (!activePage) return <p>No dashboard pages yet.</p>;

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
        onDragStop={handleDragStop}
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
