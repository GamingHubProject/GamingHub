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
      widgets: [{ id: 1, page_layout_id: 1, widget_type: 'picture', config: null, position_x: 0, position_y: 0, width: 12, height: 2 }],
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
});
