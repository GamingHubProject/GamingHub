import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
// Registers the real page-layout widget types (side effect).
import '../widgets/pageLayout';
import { PageLayoutEditor } from './PageLayoutEditor';
import type { PageLayout } from '../api/types';

function renderEditor(
  props: { isAdmin: boolean },
  client: {
    get: (path: string) => Promise<unknown>;
    post?: (path: string, body?: unknown) => Promise<unknown>;
    delete?: (path: string) => Promise<unknown>;
    patch?: (path: string, body?: unknown) => Promise<unknown>;
  }
) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const fullClient = {
    get: client.get,
    post: client.post ?? (async () => { throw new Error('post not expected'); }),
    patch: client.patch ?? (async () => { throw new Error('patch not expected'); }),
    delete: client.delete ?? (async () => { throw new Error('delete not expected'); }),
  };

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={fullClient as any}>
        <PageLayoutEditor
          layoutUrl="/api/v1/home/layout"
          queryKey={['page-layout', 'home']}
          context={{ subjectType: 'home' }}
          isAdmin={props.isAdmin}
        />
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('PageLayoutEditor', () => {
  it('renders nothing extra for an empty, non-editing layout', async () => {
    const emptyLayout: PageLayout = { id: 1, subject_type: 'home', subject_id: 0, font_asset_id: null, widgets: [] };

    renderEditor({ isAdmin: false }, { get: async () => emptyLayout });

    await waitFor(() => expect(document.body.textContent).not.toContain('Loading'));
    expect(document.querySelector('.react-grid-layout')).not.toBeInTheDocument();
  });

  it('shows the Edit layout toggle for an admin, hidden for a non-admin', async () => {
    const emptyLayout: PageLayout = { id: 1, subject_type: 'home', subject_id: 0, font_asset_id: null, widgets: [] };

    const { unmount } = renderEditor({ isAdmin: true }, { get: async () => emptyLayout });
    await waitFor(() => expect(screen.getByText('Edit layout')).toBeInTheDocument());
    unmount();

    renderEditor({ isAdmin: false }, { get: async () => emptyLayout });
    await waitFor(() => expect(screen.queryByText('Loading')).not.toBeInTheDocument());
    expect(screen.queryByText('Edit layout')).not.toBeInTheDocument();
  });

  it('shows the grid in edit mode even when the layout is empty, so an admin has somewhere to drop the first widget', async () => {
    const emptyLayout: PageLayout = { id: 1, subject_type: 'home', subject_id: 0, font_asset_id: null, widgets: [] };

    renderEditor({ isAdmin: true }, { get: async () => emptyLayout });

    await waitFor(() => expect(screen.getByText('Edit layout')).toBeInTheDocument());
    screen.getByText('Edit layout').click();

    await waitFor(() => expect(document.querySelector('.react-grid-layout')).toBeInTheDocument());
  });

  it('lets an admin add a widget via the redesigned picker, which posts to the page-layouts widgets endpoint', async () => {
    const emptyLayout: PageLayout = { id: 1, subject_type: 'home', subject_id: 0, font_asset_id: null, widgets: [] };
    let posted: { path: string; body: unknown } | null = null;

    renderEditor(
      { isAdmin: true },
      {
        get: async (path: string) => (path.includes('/games') ? [] : emptyLayout),
        post: async (path: string, body?: unknown) => {
          posted = { path, body };
          return { id: 99, page_layout_id: 1, widget_type: 'game-card', config: null, position_x: 0, position_y: 0, width: 12, height: 4 };
        },
      }
    );

    await waitFor(() => expect(screen.getByText('Edit layout')).toBeInTheDocument());
    screen.getByText('Edit layout').click();

    await waitFor(() => expect(screen.getByText('+ Add widget')).toBeInTheDocument());
    screen.getByText('+ Add widget').click();

    // Home is validFor for both new cross-linking widgets — Game Card is
    // the simpler one to click through without also stubbing a server
    // fetch for the widget's own render after it's added.
    await waitFor(() => expect(screen.getByText('Game Card')).toBeInTheDocument());
    screen.getByText('Game Card').click();

    await waitFor(() => expect(posted).not.toBeNull());
    expect(posted!.path).toBe('/api/v1/page-layouts/1/widgets');
    expect(posted!.body).toMatchObject({ widget_type: 'game-card', width: 12, height: 4 });
  });

  it('lets an admin remove a widget, which deletes it from the layout', async () => {
    const layout: PageLayout = {
      id: 1,
      subject_type: 'home',
      subject_id: 0,
      font_asset_id: null,
      widgets: [{ id: 1, page_layout_id: 1, group_widget_id: null, widget_type: 'picture', config: null, position_x: 0, position_y: 0, width: 12, height: 2 }],
    };
    let deletedPath: string | null = null;

    renderEditor(
      { isAdmin: true },
      {
        get: async () => layout,
        delete: async (path: string) => {
          deletedPath = path;
        },
      }
    );

    await waitFor(() => expect(screen.getByText('Edit layout')).toBeInTheDocument());
    screen.getByText('Edit layout').click();

    await waitFor(() => expect(screen.getByLabelText('Remove widget')).toBeInTheDocument());
    screen.getByLabelText('Remove widget').click();

    await waitFor(() => expect(deletedPath).toBe('/api/v1/page-layout-widgets/1'));
    await waitFor(() => expect(screen.queryByLabelText('Remove widget')).not.toBeInTheDocument());
  });

  // --- Group widgets ---

  it('groups two selected widgets into a new group widget, reparenting both', async () => {
    const layout: PageLayout = {
      id: 1,
      subject_type: 'home',
      subject_id: 0,
      font_asset_id: null,
      widgets: [
        { id: 1, page_layout_id: 1, group_widget_id: null, widget_type: 'picture', config: null, position_x: 0, position_y: 0, width: 3, height: 2 },
        { id: 2, page_layout_id: 1, group_widget_id: null, widget_type: 'server-name', config: null, position_x: 3, position_y: 0, width: 4, height: 3 },
      ],
    };
    const posted: { path: string; body: unknown }[] = [];
    const patched: { path: string; body: unknown }[] = [];

    renderEditor(
      { isAdmin: true },
      {
        get: async () => layout,
        post: async (path: string, body?: unknown) => {
          posted.push({ path, body });
          return { id: 10, page_layout_id: 1, group_widget_id: null, widget_type: 'group', config: null, position_x: 0, position_y: 0, width: 7, height: 3 };
        },
        patch: async (path: string, body?: unknown) => {
          patched.push({ path, body });
          const id = Number(path.split('/').pop());
          const source = layout.widgets.find((w) => w.id === id)!;
          return { ...source, ...(body as object), group_widget_id: 10 };
        },
      }
    );

    await waitFor(() => expect(screen.getByText('Edit layout')).toBeInTheDocument());
    screen.getByText('Edit layout').click();

    const checkboxes = await waitFor(() => screen.getAllByLabelText('Select for grouping'));
    expect(checkboxes).toHaveLength(2);
    checkboxes[0].click();
    checkboxes[1].click();

    await waitFor(() => expect(screen.getByText('Group selected (2)')).toBeInTheDocument());
    screen.getByText('Group selected (2)').click();

    await waitFor(() => expect(posted).toHaveLength(1));
    expect(posted[0].path).toBe('/api/v1/page-layouts/1/widgets');
    expect(posted[0].body).toMatchObject({ widget_type: 'group', position_x: 0, position_y: 0, width: 7, height: 3 });

    await waitFor(() => expect(patched).toHaveLength(2));
    expect(patched.map((p) => p.body)).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ group_widget_id: 10, position_x: 0, position_y: 0 }),
        expect.objectContaining({ group_widget_id: 10, position_x: 3, position_y: 0 }),
      ])
    );

    await waitFor(() => expect(screen.getByText('Group')).toBeInTheDocument());
    expect(screen.getByText('Picture')).toBeInTheDocument();
    expect(screen.getByText('Server Name')).toBeInTheDocument();
    // The "Group selected" toolbar button disappears once selection clears.
    expect(screen.queryByText(/Group selected/)).not.toBeInTheDocument();
  });

  it('ungroups a group widget, translating each childs position back to page-space and deleting the group', async () => {
    const layout: PageLayout = {
      id: 1,
      subject_type: 'home',
      subject_id: 0,
      font_asset_id: null,
      widgets: [
        { id: 10, page_layout_id: 1, group_widget_id: null, widget_type: 'group', config: null, position_x: 2, position_y: 1, width: 7, height: 3 },
        { id: 1, page_layout_id: 1, group_widget_id: 10, widget_type: 'picture', config: null, position_x: 0, position_y: 0, width: 3, height: 2 },
        { id: 2, page_layout_id: 1, group_widget_id: 10, widget_type: 'server-name', config: null, position_x: 3, position_y: 0, width: 4, height: 3 },
      ],
    };
    const patched: { path: string; body: unknown }[] = [];
    let deletedPath: string | null = null;

    renderEditor(
      { isAdmin: true },
      {
        get: async () => layout,
        patch: async (path: string, body?: unknown) => {
          patched.push({ path, body });
          const id = Number(path.split('/').pop());
          const source = layout.widgets.find((w) => w.id === id)!;
          return { ...source, ...(body as object) };
        },
        delete: async (path: string) => {
          deletedPath = path;
        },
      }
    );

    await waitFor(() => expect(screen.getByText('Edit layout')).toBeInTheDocument());
    screen.getByText('Edit layout').click();

    await waitFor(() => expect(screen.getByText('Ungroup')).toBeInTheDocument());
    screen.getByText('Ungroup').click();

    await waitFor(() => expect(patched).toHaveLength(2));
    expect(patched.map((p) => p.body)).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ group_widget_id: null, position_x: 2, position_y: 1 }),
        expect.objectContaining({ group_widget_id: null, position_x: 5, position_y: 1 }),
      ])
    );

    await waitFor(() => expect(deletedPath).toBe('/api/v1/page-layout-widgets/10'));
    await waitFor(() => expect(screen.queryByText('Group')).not.toBeInTheDocument());
    expect(screen.getByText('Picture')).toBeInTheDocument();
    expect(screen.getByText('Server Name')).toBeInTheDocument();
  });

  it('places a group template, merging the created group and children into the layout', async () => {
    const emptyLayout: PageLayout = { id: 1, subject_type: 'home', subject_id: 0, font_asset_id: null, widgets: [] };
    let posted: { path: string } | null = null;

    renderEditor(
      { isAdmin: true },
      {
        get: async (path: string) => (path.includes('group-widget-templates') ? [{ id: 5, name: 'Hero', created_at: '' }] : emptyLayout),
        post: async (path: string) => {
          posted = { path };
          return [
            { id: 10, page_layout_id: 1, group_widget_id: null, widget_type: 'group', config: null, position_x: 0, position_y: 0, width: 8, height: 4 },
            { id: 11, page_layout_id: 1, group_widget_id: 10, widget_type: 'server-name', config: null, position_x: 0, position_y: 0, width: 4, height: 1 },
          ];
        },
      }
    );

    await waitFor(() => expect(screen.getByText('Edit layout')).toBeInTheDocument());
    screen.getByText('Edit layout').click();

    await waitFor(() => expect(screen.getByText('+ Add group from template')).toBeInTheDocument());
    screen.getByText('+ Add group from template').click();

    await waitFor(() => expect(screen.getByText('Hero')).toBeInTheDocument());
    screen.getByText('Hero').click();

    await waitFor(() => expect(posted).not.toBeNull());
    expect(posted!.path).toBe('/api/v1/page-layouts/1/group-widgets/from-template/5');

    await waitFor(() => expect(screen.getByText('Group')).toBeInTheDocument());
    expect(screen.getByText('Server Name')).toBeInTheDocument();
  });
});
