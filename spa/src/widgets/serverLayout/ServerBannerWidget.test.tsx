import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';
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

function renderBanner(config = serverBannerWidgetDefaultConfig) {
  const { container } = render(<ServerBannerWidget server={server} config={config} />);
  return container.firstElementChild as HTMLElement;
}

describe('ServerBannerWidget', () => {
  it('renders with no background image by default', () => {
    expect(renderBanner().style.backgroundImage).toBeFalsy();
  });

  it('applies the configured background image as a CSS background', () => {
    const config = { ...serverBannerWidgetDefaultConfig, background_asset_id: 1, background_url: 'http://localhost/storage/banner.png' };

    expect(renderBanner(config)).toHaveStyle({ backgroundImage: 'url(http://localhost/storage/banner.png)' });
  });

  it.each([
    ['cover', 'cover'],
    ['contain', 'contain'],
    ['fill', '100% 100%'],
  ] as const)('maps fit=%s to background-size %s', (fit, expectedSize) => {
    const config = { ...serverBannerWidgetDefaultConfig, fit };

    expect(renderBanner(config)).toHaveStyle({ backgroundSize: expectedSize });
  });

  it('renders no overlay when overlay_opacity is 0', () => {
    expect(renderBanner().children.length).toBe(0);
  });

  it('renders a dark overlay at the configured opacity', () => {
    const config = { ...serverBannerWidgetDefaultConfig, overlay_opacity: 0.4 };
    const banner = renderBanner(config);

    expect(banner.children.length).toBe(1);
    expect(banner.children[0]).toHaveStyle({ background: 'rgba(0, 0, 0, 0.4)' });
  });
});
