import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';
import { ServerBannerWidget, serverBannerWidgetDefaultConfig } from './ServerBannerWidget';
import type { PageLayoutWidgetContext } from './registry';

const context: PageLayoutWidgetContext = { subjectType: 'server' };

function renderBanner(config = serverBannerWidgetDefaultConfig) {
  const { container } = render(<ServerBannerWidget context={context} config={config} />);
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
