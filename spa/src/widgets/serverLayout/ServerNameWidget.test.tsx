import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ServerNameWidget, serverNameWidgetDefaultConfig } from './ServerNameWidget';
import type { Server } from '../../api/types';

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

describe('ServerNameWidget', () => {
  it('renders the server name at the configured font size', () => {
    render(<ServerNameWidget server={server} config={serverNameWidgetDefaultConfig} />);

    expect(screen.getByText('ad')).toHaveStyle({ fontSize: '24' });
  });

  it('does not apply the configured color or a text-shadow when not layered', () => {
    const config = { ...serverNameWidgetDefaultConfig, text_color: '#ff0000' };
    render(<ServerNameWidget server={server} config={config} />);

    const heading = screen.getByText('ad');
    expect(heading).not.toHaveStyle({ color: '#ff0000' });
    expect(heading.style.textShadow).toBe('');
  });

  it('applies the configured color and a text-shadow when layered', () => {
    const config = { ...serverNameWidgetDefaultConfig, text_color: '#ff0000' };
    render(<ServerNameWidget server={server} config={config} layered />);

    const heading = screen.getByText('ad');
    expect(heading).toHaveStyle({ color: '#ff0000', textShadow: '0 1px 3px rgba(0, 0, 0, 0.8)' });
  });
});
