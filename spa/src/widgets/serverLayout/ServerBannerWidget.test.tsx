import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ServerBannerWidget, serverBannerWidgetDefaultConfig } from './ServerBannerWidget';
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

describe('ServerBannerWidget', () => {
  it('renders with no background image by default', () => {
    render(<ServerBannerWidget server={server} config={serverBannerWidgetDefaultConfig} />);

    expect(screen.getByText('ad').parentElement?.style.backgroundImage).toBeFalsy();
  });

  it('applies the configured background image as a CSS background', () => {
    const config = { ...serverBannerWidgetDefaultConfig, background_asset_id: 1, background_url: 'http://localhost/storage/banner.png' };

    render(<ServerBannerWidget server={server} config={config} />);

    expect(screen.getByText('ad').parentElement).toHaveStyle({ backgroundImage: 'url(http://localhost/storage/banner.png)' });
  });

  it('sizes the inline status badge to its content instead of stretching full-width', () => {
    // Regression: a column flex container's default align-items is
    // 'stretch', which stretches every flex item (including the badge
    // span) to the container's full width regardless of its own
    // display:inline-block — the container needs alignItems:'flex-start'
    // to opt out of that.
    const config = { ...serverBannerWidgetDefaultConfig, show_status: true };

    render(<ServerBannerWidget server={server} config={config} />);

    expect(screen.getByText('ad').parentElement).toHaveStyle({ alignItems: 'flex-start' });
  });
});
