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
    render(<ServerLayoutWidgetContainer widget={widget} server={server} editable={false} onRemove={() => {}} />);

    expect(screen.getByText('Running')).toBeInTheDocument();
    expect(screen.queryByLabelText('Remove widget')).not.toBeInTheDocument();
  });

  it('shows the header with a remove button in edit mode, excluded from the drag handle', () => {
    render(<ServerLayoutWidgetContainer widget={widget} server={server} editable={true} onRemove={() => {}} />);

    expect(screen.getByLabelText('Remove widget')).toHaveClass('widget-no-drag');
  });

  it('calls onRemove when the remove button is clicked', () => {
    let removed = false;

    render(<ServerLayoutWidgetContainer widget={widget} server={server} editable={true} onRemove={() => (removed = true)} />);
    screen.getByLabelText('Remove widget').click();

    expect(removed).toBe(true);
  });

  it('renders a graceful fallback for an unregistered widget type', () => {
    const unknownWidget = { ...widget, widget_type: 'some-future-card' };

    render(<ServerLayoutWidgetContainer widget={unknownWidget} server={server} editable={false} onRemove={() => {}} />);

    expect(screen.getByText(/unsupported widget type: some-future-card/i)).toBeInTheDocument();
  });
});
