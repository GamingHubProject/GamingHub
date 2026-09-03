import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { ThemeProvider } from '../providers/ThemeProvider';
// Registers the real widget types (side effect) — the same import
// App.tsx does. Without it the registry is empty and every widget falls
// back to "Unsupported widget type".
import '../widgets/pageLayout';
import { PageLayoutWidgetContainer } from './PageLayoutWidgetContainer';
import type { PageLayoutWidgetContext } from '../widgets/pageLayout/registry';
import type { Server, PageLayoutWidget } from '../api/types';

const server: Server = {
  id: 2,
  game_id: 1,
  server_group_id: null,
  name: 'ad',
  slug: 'ad',
  description: null,
  status: 'running',
  max_players: null,
  current_players: null,
  cpu_current: null,
  cpu_limit: null,
  cpu_percent: null,
  memory_current: null,
  memory_limit: null,
  memory_percent: null,
  disk_current: null,
  disk_limit: null,
  disk_percent: null,
  network_rx: null,
  network_tx: null,
  node_name: null,
  supported_features: null,
  game_version: null,
  last_polled_at: null,
  allocations: [],
};

const context: PageLayoutWidgetContext = { subjectType: 'server', server };

const widget: PageLayoutWidget = {
  id: 1,
  page_layout_id: 1,
  group_widget_id: null,
  widget_type: 'server-status',
  config: null,
  position_x: 0,
  position_y: 0,
  width: 3,
  height: 2,
};

describe('PageLayoutWidgetContainer', () => {
  it('renders the card content in read-only mode without a header or remove button', () => {
    render(<PageLayoutWidgetContainer widget={widget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByText('Running')).toBeInTheDocument();
    expect(screen.queryByLabelText('Remove widget')).not.toBeInTheDocument();
  });

  it('shows the header with a remove button in edit mode, excluded from the drag handle', () => {
    render(<PageLayoutWidgetContainer widget={widget} context={context} editable={true} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByLabelText('Remove widget')).toHaveClass('widget-no-drag');
  });

  it('calls onRemove when the remove button is clicked', () => {
    let removed = false;

    render(<PageLayoutWidgetContainer widget={widget} context={context} editable={true} onRemove={() => (removed = true)} onEdit={() => {}} />);
    screen.getByLabelText('Remove widget').click();

    expect(removed).toBe(true);
  });

  it('defaults the drag handle to widget-drag-handle, matching the page grids own draggableHandle selector', () => {
    const { container } = render(
      <PageLayoutWidgetContainer widget={widget} context={context} editable={true} onRemove={() => {}} onEdit={() => {}} />
    );

    expect(container.querySelector('.widget-drag-handle')).not.toBeNull();
  });

  it('uses a custom dragHandleClassName instead, when passed (e.g. a group child)', () => {
    const { container } = render(
      <PageLayoutWidgetContainer
        widget={widget}
        context={context}
        editable={true}
        onRemove={() => {}}
        onEdit={() => {}}
        dragHandleClassName="group-child-drag-handle"
      />
    );

    expect(container.querySelector('.group-child-drag-handle')).not.toBeNull();
    // Must not also carry the page grid's own class — that's the exact
    // collision this prop exists to avoid.
    expect(container.querySelector('.widget-drag-handle')).toBeNull();
  });

  it('does not show a selection checkbox unless selectable is passed', () => {
    render(<PageLayoutWidgetContainer widget={widget} context={context} editable={true} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.queryByLabelText('Select for grouping')).not.toBeInTheDocument();
  });

  it('shows a selection checkbox reflecting `selected` and calls onToggleSelect when clicked', () => {
    let toggled = false;

    render(
      <PageLayoutWidgetContainer
        widget={widget}
        context={context}
        editable={true}
        onRemove={() => {}}
        onEdit={() => {}}
        selectable
        selected
        onToggleSelect={() => (toggled = true)}
      />
    );

    const checkbox = screen.getByLabelText('Select for grouping') as HTMLInputElement;
    expect(checkbox.checked).toBe(true);
    checkbox.click();
    expect(toggled).toBe(true);
  });

  it('renders a graceful fallback for an unregistered widget type', () => {
    const unknownWidget = { ...widget, widget_type: 'some-future-card' };

    render(<PageLayoutWidgetContainer widget={unknownWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByText(/unsupported widget type: some-future-card/i)).toBeInTheDocument();
  });

  it('shows a settings gear in edit mode for a widget type with a configForm (server-status)', () => {
    render(<PageLayoutWidgetContainer widget={widget} context={context} editable={true} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByLabelText('Widget settings')).toHaveClass('widget-no-drag');
  });

  it('still shows the settings gear for a widget type with no configForm of its own (server-allocations) — WidgetStyleSection is universal', () => {
    const allocationsWidget = { ...widget, widget_type: 'server-allocations' };

    render(<PageLayoutWidgetContainer widget={allocationsWidget} context={context} editable={true} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.queryByLabelText('Widget settings')).toBeInTheDocument();
  });

  it('calls onEdit when the settings gear is clicked', () => {
    let edited = false;

    render(<PageLayoutWidgetContainer widget={widget} context={context} editable={true} onRemove={() => {}} onEdit={() => (edited = true)} />);
    screen.getByLabelText('Widget settings').click();

    expect(edited).toBe(true);
  });

  it('hides the node by default on the status card (show_node defaults to false)', () => {
    const withNode: PageLayoutWidgetContext = { subjectType: 'server', server: { ...server, node_name: 'node-1' } };

    render(<PageLayoutWidgetContainer widget={widget} context={withNode} editable={false} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.queryByText(/node:/i)).not.toBeInTheDocument();
  });

  it('shows the node once the widget config turns show_node on', () => {
    const withNode: PageLayoutWidgetContext = { subjectType: 'server', server: { ...server, node_name: 'node-1' } };
    const configuredWidget = { ...widget, config: { show_node: true } };

    render(<PageLayoutWidgetContainer widget={configuredWidget} context={withNode} editable={false} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByText('Node: node-1')).toBeInTheDocument();
  });

  it('renders the server name for the server-name widget type', () => {
    const nameWidget = { ...widget, widget_type: 'server-name', config: null };
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={{ get: async () => { throw new Error('should not fetch'); } } as any}>
          <PageLayoutWidgetContainer widget={nameWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
        </ApiClientProvider>
      </QueryClientProvider>
    );

    expect(screen.getByText('ad')).toBeInTheDocument();
  });

  it('drops the card border/background when layered', () => {
    const { container } = render(
      <PageLayoutWidgetContainer widget={widget} context={context} editable={false} layered onRemove={() => {}} onEdit={() => {}} />
    );

    expect(container.firstElementChild).not.toHaveStyle({ border: '1px solid var(--border, #ddd)' });
  });

  it('keeps the card border/background when not layered', () => {
    const { container } = render(
      <PageLayoutWidgetContainer widget={widget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
    );

    expect(container.firstElementChild).toHaveStyle({ border: '1px solid var(--border, #ddd)' });
  });

  it('drops the card border for a widget type registered chromeless (game-card), even when not layered', () => {
    const gameCardWidget = { ...widget, widget_type: 'game-card', config: null };
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const apiClient = { get: async () => [] };

    const { container } = render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={apiClient as any}>
          <MemoryRouter>
            <PageLayoutWidgetContainer widget={gameCardWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
          </MemoryRouter>
        </ApiClientProvider>
      </QueryClientProvider>
    );

    expect(container.firstElementChild).not.toHaveStyle({ border: '1px solid var(--border, #ddd)' });
  });

  it("still applies a background color to game-card in 'all' mode — only Border is skipped for chromeless widget types, never Background", async () => {
    const gameCardWidget = { ...widget, widget_type: 'game-card', config: null };
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const apiClient = {
      get: async (url: string) =>
        url.startsWith('/api/v1/theme')
          ? { tokens: {}, font: null, widgetStyle: { background_color: '#ff0000', background_opacity: 1 } }
          : [],
    };

    const { container } = render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={apiClient as any}>
          <ThemeProvider>
            <MemoryRouter>
              <PageLayoutWidgetContainer widget={gameCardWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
            </MemoryRouter>
          </ThemeProvider>
        </ApiClientProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(container.firstElementChild).toHaveStyle({ backgroundColor: 'rgba(255, 0, 0, 1)' }));
  });

  it("keeps the container border for game-card in 'single' mode — only 'all' mode's grid of already-bordered cards skips it", () => {
    const singleModeWidget = {
      ...widget,
      widget_type: 'game-card',
      config: { mode: 'single', game_id: null, game_slug: null, show_icon: true, icon_asset_id: null, icon_url: null },
    };
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const apiClient = { get: async () => [] };

    const { container } = render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={apiClient as any}>
          <MemoryRouter>
            <PageLayoutWidgetContainer widget={singleModeWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
          </MemoryRouter>
        </ApiClientProvider>
      </QueryClientProvider>
    );

    expect(container.firstElementChild).toHaveStyle({ border: '1px solid var(--border, #ddd)' });
  });

  it('keeps overflow contained (hidden) for a chromeless-but-not-layered widget, unlike a layered one', () => {
    // Regression test: chromeless and layered used to share one
    // overflow:visible condition, which let game-card's 'all' mode grid
    // spill out past its own resize handle instead of staying clipped to
    // one resizable block — see PageLayoutWidgetContainer's inline
    // comment on the overflow style. `hidden` (not `auto`) since resized
    // content now scales via container queries instead of scrolling.
    const gameCardWidget = { ...widget, widget_type: 'game-card', config: null };
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const apiClient = { get: async () => [] };

    const { container } = render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={apiClient as any}>
          <MemoryRouter>
            <PageLayoutWidgetContainer widget={gameCardWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
          </MemoryRouter>
        </ApiClientProvider>
      </QueryClientProvider>
    );

    const contentDiv = container.firstElementChild?.lastElementChild;
    expect(contentDiv).toHaveStyle({ overflow: 'hidden' });
  });

  it('passes layered through to the widget component', () => {
    const nameWidget = { ...widget, widget_type: 'server-name', config: null };
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={{ get: async () => { throw new Error('should not fetch'); } } as any}>
          <PageLayoutWidgetContainer widget={nameWidget} context={context} editable={false} layered onRemove={() => {}} onEdit={() => {}} />
        </ApiClientProvider>
      </QueryClientProvider>
    );

    expect(screen.getByText('ad')).toHaveStyle({ textShadow: '0 1px 3px rgba(0, 0, 0, 0.8)' });
  });

  // --- Universal widget style (Border/Text/Background) ---

  function renderWithTheme(ui: React.ReactElement, widgetStyle: Record<string, unknown> = {}) {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const apiClient = { get: async () => ({ tokens: {}, font: null, widgetStyle }) };

    return render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={apiClient as any}>
          <ThemeProvider>{ui}</ThemeProvider>
        </ApiClientProvider>
      </QueryClientProvider>
    );
  }

  it('defaults to a 1px border when nothing overrides it anywhere (the pre-existing look, unchanged)', () => {
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={widget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
    );

    expect(container.firstElementChild).toHaveStyle({ border: '1px solid var(--border, #ddd)' });
  });

  it('applies a global default border override fetched from /api/v1/theme', async () => {
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={widget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />,
      { border_enabled: false }
    );

    await waitFor(() => expect(container.firstElementChild).toHaveStyle({ border: 'none' }));
  });

  it("an instance's own style override wins over the global default", async () => {
    const overriddenWidget = { ...widget, config: { style: { border_enabled: true, border_thickness: 4 } } };
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={overriddenWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />,
      { border_enabled: false }
    );

    await waitFor(() => expect(container.firstElementChild).toHaveStyle({ border: '4px solid var(--border, #ddd)' }));
  });

  it('applies a resolved background color with opacity', async () => {
    const bgWidget = { ...widget, config: { style: { background_color: '#ff0000', background_opacity: 0.5 } } };
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={bgWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
    );

    await waitFor(() => expect(container.firstElementChild).toHaveStyle({ backgroundColor: 'rgba(255, 0, 0, 0.5)' }));
  });

  it('never applies a border/background to a chromeless widget (e.g. layered)', async () => {
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={widget} context={context} editable={false} layered onRemove={() => {}} onEdit={() => {}} />,
      { border_enabled: true, background_color: '#ff0000' }
    );
    await waitFor(() => expect(screen.getByText('Running')).toBeInTheDocument());

    expect(container.firstElementChild).not.toHaveStyle({ border: '1px solid var(--border, #ddd)' });
    expect(container.firstElementChild).not.toHaveStyle({ backgroundColor: 'rgba(255, 0, 0, 1)' });
  });

  it('applies a resolved border color instead of the var(--border) default when set', async () => {
    const borderColorWidget = { ...widget, config: { style: { border_enabled: true, border_thickness: 2, border_color: '#123456' } } };
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={borderColorWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
    );

    await waitFor(() => expect(container.firstElementChild).toHaveStyle({ border: '2px solid #123456' }));
  });

  it('applies a resolved border radius instead of the default 8px when set', async () => {
    const radiusWidget = { ...widget, config: { style: { border_radius: 20 } } };
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={radiusWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
    );

    await waitFor(() => expect(container.firstElementChild).toHaveStyle({ borderRadius: '20px' }));
  });

  it('applies a pattern background as a gradient image over the base color', async () => {
    const patternWidget = {
      ...widget,
      config: {
        style: {
          background_type: 'pattern',
          background_color: '#ffffff',
          background_pattern: 'dots',
          background_pattern_color: '#ff0000',
          background_opacity: 1,
        },
      },
    };
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={patternWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
    );

    await waitFor(() =>
      expect((container.firstElementChild as HTMLElement).style.backgroundImage).toContain('radial-gradient')
    );
    expect(container.firstElementChild).toHaveStyle({ backgroundColor: 'rgba(255, 255, 255, 1)' });
  });

  it('applies an image background with the resolved fit', async () => {
    const imageWidget = {
      ...widget,
      config: {
        style: {
          background_type: 'image',
          background_image_url: 'https://example.test/bg.png',
          background_image_fit: 'tile',
        },
      },
    };
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={imageWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
    );

    await waitFor(() => expect(container.firstElementChild).toHaveStyle({ backgroundRepeat: 'repeat' }));
    // The DOM normalizes url() by quoting the argument — assert on the
    // URL being there rather than on the browser's serialization of it.
    expect((container.firstElementChild as HTMLElement).style.backgroundImage).toContain('https://example.test/bg.png');
  });

  it('never paints a pattern background on a layered widget, same as a solid one', async () => {
    const patternWidget = {
      ...widget,
      config: {
        style: { background_type: 'pattern', background_color: '#ffffff', background_pattern: 'dots', background_pattern_color: '#ff0000' },
      },
    };
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={patternWidget} context={context} editable={false} layered onRemove={() => {}} onEdit={() => {}} />
    );
    await waitFor(() => expect(screen.getByText('Running')).toBeInTheDocument());

    expect((container.firstElementChild as HTMLElement).style.backgroundImage).toBe('');
  });

  it('sets --card-text-scale from the resolved textScale, for self-scaling widgets to consume via CSS', async () => {
    const scaledWidget = { ...widget, config: { style: { text_scale: 1.5 } } };
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={scaledWidget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
    );

    await waitFor(() => expect((container.firstElementChild as HTMLElement).style.getPropertyValue('--card-text-scale')).toBe('1.5'));
  });

  it('defaults --card-text-scale to 1 when nothing overrides it anywhere', async () => {
    const { container } = renderWithTheme(
      <PageLayoutWidgetContainer widget={widget} context={context} editable={false} onRemove={() => {}} onEdit={() => {}} />
    );

    await waitFor(() => expect(screen.getByText('Running')).toBeInTheDocument());
    expect((container.firstElementChild as HTMLElement).style.getPropertyValue('--card-text-scale')).toBe('1');
  });
});
