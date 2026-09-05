import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { ThemeProvider } from '../providers/ThemeProvider';
import { Sidebar } from './Sidebar';
import type { SidebarRegion } from './Sidebar';

function renderSidebar(region: SidebarRegion) {
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
            <Sidebar behavior="always" region={region} open onOpenChange={() => {}} />
          </MemoryRouter>
        </ThemeProvider>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

const sidebarEl = () => screen.getByTestId('sidebar');

describe('Sidebar containment', () => {
  it('stays flush with a right edge only when there is no margin', async () => {
    renderSidebar({ margin: 0 });

    await waitFor(() => expect(sidebarEl()).toBeInTheDocument());
    expect(sidebarEl().style.borderRight).not.toBe('');
    expect(sidebarEl().style.border).toBe('');
    expect(sidebarEl().style.margin).toBe('');
  });

  it('becomes a contained card with a full outline once a margin is set', async () => {
    // A rounded card with a single curved line down one side reads as a
    // rendering bug, so the edge becomes an outline at the same moment the
    // corners round.
    renderSidebar({ margin: 16 });

    await waitFor(() => expect(sidebarEl().style.margin).toBe('16px'));
    expect(sidebarEl().style.border).not.toBe('');
    expect(sidebarEl().style.borderRadius).toBeTruthy();
  });

  it('offsets its sticky position by the margin so it does not overhang', async () => {
    renderSidebar({ margin: 16 });

    await waitFor(() => expect(sidebarEl().style.top).toBe('16px'));
    expect(sidebarEl().style.maxHeight).toBe('calc(100dvh - 32px)');
  });

  it('uses the theme radius unless the sidebar overrides it', async () => {
    renderSidebar({ margin: 16 });
    await waitFor(() => expect(sidebarEl().style.borderRadius).toBe('var(--radius, 8px)'));

    renderSidebar({ margin: 16, radius: 24 });
    await waitFor(() => expect(screen.getAllByTestId('sidebar')[1].style.borderRadius).toBe('24px'));
  });

  it('follows its contents by default, taking no explicit height', async () => {
    renderSidebar({});

    await waitFor(() => expect(sidebarEl()).toBeInTheDocument());
    expect(sidebarEl().style.height).toBe('');
  });

  it('fills the window less the margins when asked to', async () => {
    renderSidebar({ height: 'full', margin: 16 });

    await waitFor(() => expect(sidebarEl().style.height).toBe('calc(100dvh - 32px)'));
  });

  it('takes a fixed height when one is given', async () => {
    renderSidebar({ height: 'fixed', height_px: 480 });

    await waitFor(() => expect(sidebarEl().style.height).toBe('480px'));
  });

  it('ignores a fixed height with no value rather than collapsing', async () => {
    renderSidebar({ height: 'fixed' });

    await waitFor(() => expect(sidebarEl()).toBeInTheDocument());
    expect(sidebarEl().style.height).toBe('');
  });
});

describe('Sidebar nav alignment', () => {
  const list = () => screen.getByRole('list');

  it('leaves the links at the top by default', async () => {
    renderSidebar({ height: 'full' });

    await waitFor(() => expect(list()).toBeInTheDocument());
    // The list resets its own margins, so "top" means those zeros stand.
    expect(list().style.marginTop).toBe('0px');
  });

  it('centres the links within the spare height', async () => {
    renderSidebar({ height: 'full', nav_align: 'center' });

    await waitFor(() => expect(list().style.marginTop).toBe('auto'));
    expect(list().style.marginBottom).toBe('auto');
  });

  it('pushes the links to the bottom', async () => {
    renderSidebar({ height: 'full', nav_align: 'bottom' });

    await waitFor(() => expect(list().style.marginTop).toBe('auto'));
    expect(list().style.marginBottom).toBe('0px');
  });

  it('keeps the branding pinned to the top whatever the links do', async () => {
    // The identity anchors the panel; only the menu moves.
    renderSidebar({ height: 'full', nav_align: 'bottom', show_branding: true });

    await waitFor(() => expect(screen.getByText('Hub')).toBeInTheDocument());
    const branding = screen.getByText('Hub').closest('a')!;
    expect(branding.compareDocumentPosition(list()) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });
});
