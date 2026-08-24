import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
// Registers the real 5 widget types (side effect) — the same import
// App.tsx does. Without it the registry is empty and every widget falls
// back to "Unsupported widget type".
import '../widgets/serverLayout';
import { ServerLayoutWidgetContainer } from './ServerLayoutWidgetContainer';
import type { Server, ServerLayoutWidget } from '../api/types';

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

const widget: ServerLayoutWidget = {
  id: 1,
  server_layout_id: 1,
  widget_type: 'server-status',
  config: null,
  position_x: 0,
  position_y: 0,
  width: 3,
  height: 2,
};

describe('ServerLayoutWidgetContainer', () => {
  it('renders the card content in read-only mode without a header or remove button', () => {
    render(<ServerLayoutWidgetContainer widget={widget} server={server} editable={false} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByText('Running')).toBeInTheDocument();
    expect(screen.queryByLabelText('Remove widget')).not.toBeInTheDocument();
  });

  it('shows the header with a remove button in edit mode, excluded from the drag handle', () => {
    render(<ServerLayoutWidgetContainer widget={widget} server={server} editable={true} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByLabelText('Remove widget')).toHaveClass('widget-no-drag');
  });

  it('calls onRemove when the remove button is clicked', () => {
    let removed = false;

    render(<ServerLayoutWidgetContainer widget={widget} server={server} editable={true} onRemove={() => (removed = true)} onEdit={() => {}} />);
    screen.getByLabelText('Remove widget').click();

    expect(removed).toBe(true);
  });

  it('renders a graceful fallback for an unregistered widget type', () => {
    const unknownWidget = { ...widget, widget_type: 'some-future-card' };

    render(<ServerLayoutWidgetContainer widget={unknownWidget} server={server} editable={false} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByText(/unsupported widget type: some-future-card/i)).toBeInTheDocument();
  });

  it('shows a settings gear in edit mode for a widget type with a configForm (server-status)', () => {
    render(<ServerLayoutWidgetContainer widget={widget} server={server} editable={true} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByLabelText('Widget settings')).toHaveClass('widget-no-drag');
  });

  it('hides the settings gear for a widget type with no configForm (server-allocations)', () => {
    const allocationsWidget = { ...widget, widget_type: 'server-allocations' };

    render(<ServerLayoutWidgetContainer widget={allocationsWidget} server={server} editable={true} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.queryByLabelText('Widget settings')).not.toBeInTheDocument();
  });

  it('calls onEdit when the settings gear is clicked', () => {
    let edited = false;

    render(<ServerLayoutWidgetContainer widget={widget} server={server} editable={true} onRemove={() => {}} onEdit={() => (edited = true)} />);
    screen.getByLabelText('Widget settings').click();

    expect(edited).toBe(true);
  });

  it('hides the node by default on the status card (show_node defaults to false)', () => {
    const withNode = { ...server, node_name: 'node-1' };

    render(<ServerLayoutWidgetContainer widget={widget} server={withNode} editable={false} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.queryByText(/node:/i)).not.toBeInTheDocument();
  });

  it('shows the node once the widget config turns show_node on', () => {
    const withNode = { ...server, node_name: 'node-1' };
    const configuredWidget = { ...widget, config: { show_node: true } };

    render(<ServerLayoutWidgetContainer widget={configuredWidget} server={withNode} editable={false} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByText('Node: node-1')).toBeInTheDocument();
  });

  it('renders the server name for the server-name widget type', () => {
    const nameWidget = { ...widget, widget_type: 'server-name', config: null };

    render(<ServerLayoutWidgetContainer widget={nameWidget} server={server} editable={false} onRemove={() => {}} onEdit={() => {}} />);

    expect(screen.getByText('ad')).toBeInTheDocument();
  });

  it('drops the card border/background when layered', () => {
    const { container } = render(
      <ServerLayoutWidgetContainer widget={widget} server={server} editable={false} layered onRemove={() => {}} onEdit={() => {}} />
    );

    expect(container.firstElementChild).not.toHaveStyle({ border: '1px solid var(--border, #ddd)' });
  });

  it('keeps the card border/background when not layered', () => {
    const { container } = render(
      <ServerLayoutWidgetContainer widget={widget} server={server} editable={false} onRemove={() => {}} onEdit={() => {}} />
    );

    expect(container.firstElementChild).toHaveStyle({ border: '1px solid var(--border, #ddd)' });
  });

  it('passes layered through to the widget component', () => {
    const nameWidget = { ...widget, widget_type: 'server-name', config: null };

    render(
      <ServerLayoutWidgetContainer widget={nameWidget} server={server} editable={false} layered onRemove={() => {}} onEdit={() => {}} />
    );

    expect(screen.getByText('ad')).toHaveStyle({ textShadow: '0 1px 3px rgba(0, 0, 0, 0.8)' });
  });
});
