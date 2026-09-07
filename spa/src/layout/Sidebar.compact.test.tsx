import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { ThemeProvider } from '../providers/ThemeProvider';
import { Sidebar } from './Sidebar';
import type { SidebarBehavior } from './Sidebar';

function renderSidebar(behavior: SidebarBehavior, extra: Record<string, unknown> = {}) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const client = {
    get: async (path: string) => {
      if (path.startsWith('/api/v1/navigation')) {
        return [{ id: 1, type: 'page', label: 'Home', url: '/', icon_url: null, children: [] }];
      }
      if (path.startsWith('/api/v1/theme')) {
        return { tokens: {}, font: null, widgetStyle: {}, site: {}, branding: { name: 'Hub', tagline: null, logo_url: null } };
      }
      return null;
    },
  };

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <ThemeProvider>
          <MemoryRouter>
            <Sidebar behavior={behavior} region={{}} open onOpenChange={() => {}} {...extra} />
          </MemoryRouter>
        </ThemeProvider>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

const sidebarEl = () => screen.getByTestId('sidebar');

describe('Sidebar icons-only behaviour', () => {
  it('sits at the icon rail width rather than a named sidebar width', async () => {
    renderSidebar('icons');

    await waitFor(() => expect(sidebarEl()).toHaveStyle({ width: '64px' }));
  });

  it('hides the labels, keeping each one as a tooltip', async () => {
    renderSidebar('icons');

    // Wait for the nav itself to arrive, not merely for the sidebar
    // element, which exists before the query resolves.
    await waitFor(() => expect(screen.getByTitle('Home')).toBeInTheDocument());
    // The label is gone as text but still reachable by title, which is the
    // only thing making an icons-only rail usable.
    expect(screen.queryByText('Home')).not.toBeInTheDocument();
  });

  it('stays visible — it is a rail, not a hidden sidebar', async () => {
    renderSidebar('icons');

    await waitFor(() => expect(sidebarEl()).not.toHaveStyle({ width: '0px' }));
  });

  it('does not expand just because something toggled it open', async () => {
    // The old expression fell through to `open` for any behaviour it
    // didn't name, which would have expanded an icons-only sidebar.
    renderSidebar('icons', { open: true });

    await waitFor(() => expect(sidebarEl()).toHaveStyle({ width: '64px' }));
  });

  it('still expands normally when the behaviour is always', async () => {
    renderSidebar('always');

    await waitFor(() => expect(screen.getByText('Home')).toBeInTheDocument());
    expect(sidebarEl()).toHaveStyle({ width: '240px' });
  });
});

describe('Sidebar account slot', () => {
  it('renders the account block when Layout hands it one', async () => {
    renderSidebar('always', { accountSlot: <button type="button">Rose</button> });

    await waitFor(() => expect(screen.getByRole('button', { name: 'Rose' })).toBeInTheDocument());
  });

  it('leaves it out when the sidebar is collapsed to icons', async () => {
    // No room for a name in 64px — Layout keeps it in the header instead,
    // and the sidebar refuses it here as well so the two can't disagree.
    renderSidebar('icons', { accountSlot: <button type="button">Rose</button> });

    await waitFor(() => expect(screen.getByTitle('Home')).toBeInTheDocument());
    expect(screen.queryByRole('button', { name: 'Rose' })).not.toBeInTheDocument();
  });
});
