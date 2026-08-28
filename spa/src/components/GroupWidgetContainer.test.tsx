import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
// Registers the real page-layout widget types (side effect) — children
// render via the real PageLayoutWidgetContainer + registry, not a mock.
import '../widgets/pageLayout';
import { GroupWidgetContainer } from './GroupWidgetContainer';
import type { PageLayoutWidgetContext } from '../widgets/pageLayout/registry';
import type { PageLayoutWidget } from '../api/types';

const context: PageLayoutWidgetContext = { subjectType: 'home' };

const noop = () => {};

// server-name (used as a test child below) fetches via useQuery even when
// disabled, so every render needs a QueryClient/ApiClient in scope — see
// ServerNameWidget.test.tsx's renderWidget helper for the same reasoning.
function renderWithProviders(ui: React.ReactElement) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={{ get: async () => { throw new Error('should not fetch'); } } as any}>{ui}</ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('GroupWidgetContainer', () => {
  it('shows an empty-state message when the group has no children', () => {
    renderWithProviders(
      <GroupWidgetContainer
        children={[]}
        context={context}
        editable={false}
        onRemoveGroup={noop}
        onRemoveChild={noop}
        onEditChild={noop}
        onPersistChildren={noop}
        onUngroup={noop}
        onSaveTemplate={noop}
      />
    );

    expect(screen.getByText('Empty group.')).toBeInTheDocument();
  });

  it('renders each child widget inside its own nested grid', () => {
    const children: PageLayoutWidget[] = [
      { id: 1, page_layout_id: 1, group_widget_id: 10, widget_type: 'picture', config: null, position_x: 0, position_y: 0, width: 4, height: 2 },
      { id: 2, page_layout_id: 1, group_widget_id: 10, widget_type: 'server-name', config: null, position_x: 4, position_y: 0, width: 4, height: 1 },
    ];

    renderWithProviders(
      <GroupWidgetContainer
        children={children}
        context={context}
        editable={false}
        onRemoveGroup={noop}
        onRemoveChild={noop}
        onEditChild={noop}
        onPersistChildren={noop}
        onUngroup={noop}
        onSaveTemplate={noop}
      />
    );

    expect(screen.queryByText('Empty group.')).not.toBeInTheDocument();
    // server-name with no context.server and no configured server_id
    // falls back to its own placeholder — confirms the child actually
    // rendered through the real widget component, not a stub.
    expect(screen.getByText('No server selected yet.')).toBeInTheDocument();
  });

  it('hides the header (label, Ungroup, Save as template, remove) when not editable', () => {
    renderWithProviders(
      <GroupWidgetContainer
        children={[]}
        context={context}
        editable={false}
        onRemoveGroup={noop}
        onRemoveChild={noop}
        onEditChild={noop}
        onPersistChildren={noop}
        onUngroup={noop}
        onSaveTemplate={noop}
      />
    );

    expect(screen.queryByText('Group')).not.toBeInTheDocument();
    expect(screen.queryByText('Ungroup')).not.toBeInTheDocument();
    expect(screen.queryByText('Save as template')).not.toBeInTheDocument();
  });

  it('shows the header in edit mode and wires up Ungroup/Save as template/remove', () => {
    let ungrouped = false;
    let savedTemplate = false;
    let removedGroup = false;

    renderWithProviders(
      <GroupWidgetContainer
        children={[]}
        context={context}
        editable={true}
        onRemoveGroup={() => (removedGroup = true)}
        onRemoveChild={noop}
        onEditChild={noop}
        onPersistChildren={noop}
        onUngroup={() => (ungrouped = true)}
        onSaveTemplate={() => (savedTemplate = true)}
      />
    );

    expect(screen.getByText('Group')).toBeInTheDocument();

    screen.getByText('Ungroup').click();
    expect(ungrouped).toBe(true);

    screen.getByText('Save as template').click();
    expect(savedTemplate).toBe(true);

    screen.getByLabelText('Remove widget').click();
    expect(removedGroup).toBe(true);
  });

  it('calls onRemoveChild (not onRemoveGroup) when a childs own remove button is clicked', () => {
    const children: PageLayoutWidget[] = [
      { id: 1, page_layout_id: 1, group_widget_id: 10, widget_type: 'picture', config: null, position_x: 0, position_y: 0, width: 4, height: 2 },
    ];
    let removedChildId: number | null = null;
    let removedGroup = false;

    renderWithProviders(
      <GroupWidgetContainer
        children={children}
        context={context}
        editable={true}
        onRemoveGroup={() => (removedGroup = true)}
        onRemoveChild={(id) => (removedChildId = id)}
        onEditChild={noop}
        onPersistChildren={noop}
        onUngroup={noop}
        onSaveTemplate={noop}
      />
    );

    // Two "Remove widget" buttons exist in edit mode: the group's own and
    // the child's — the child's is the second one in DOM order.
    const removeButtons = screen.getAllByLabelText('Remove widget');
    expect(removeButtons).toHaveLength(2);
    removeButtons[1].click();

    expect(removedChildId).toBe(1);
    expect(removedGroup).toBe(false);
  });

  it("uses a drag-handle class distinct from the page grid's, so dragging a child never gets intercepted by the outer grid", () => {
    // Regression test: the group's own header and a child's header used
    // to share the literal "widget-drag-handle" class with the *page*
    // grid's draggableHandle selector — react-grid-layout matches that
    // selector by walking up from the mousedown target with no concept of
    // "which grid instance" it belongs to, so dragging a child actually
    // dragged the whole Group instead (confirmed live before this fix).
    const children: PageLayoutWidget[] = [
      { id: 1, page_layout_id: 1, group_widget_id: 10, widget_type: 'picture', config: null, position_x: 0, position_y: 0, width: 4, height: 2 },
    ];

    const { container } = renderWithProviders(
      <GroupWidgetContainer
        children={children}
        context={context}
        editable={true}
        onRemoveGroup={noop}
        onRemoveChild={noop}
        onEditChild={noop}
        onPersistChildren={noop}
        onUngroup={noop}
        onSaveTemplate={noop}
      />
    );

    // The group's own header still uses the page grid's class — it's a
    // normal top-level item there, meant to be dragged by that grid.
    expect(container.querySelector('.widget-drag-handle')).not.toBeNull();

    // The child's header uses a different class entirely, and must not
    // also carry the page grid's class (that's exactly what caused the
    // collision).
    const childHandle = container.querySelector('.group-child-drag-handle');
    expect(childHandle).not.toBeNull();
    expect(childHandle).not.toHaveClass('widget-drag-handle');
  });
});
