import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';
import { PictureWidget, pictureWidgetDefaultConfig } from './PictureWidget';
import type { PageLayoutWidgetContext } from './registry';

const context: PageLayoutWidgetContext = { subjectType: 'server' };

function renderPicture(config = pictureWidgetDefaultConfig) {
  const { container } = render(<PictureWidget context={context} config={config} />);
  return container.firstElementChild as HTMLElement;
}

describe('PictureWidget', () => {
  it('renders with no background image by default', () => {
    expect(renderPicture().style.backgroundImage).toBeFalsy();
  });

  it('applies the configured background image as a CSS background', () => {
    const config = { ...pictureWidgetDefaultConfig, background_asset_id: 1, background_url: 'http://localhost/storage/picture.png' };

    expect(renderPicture(config)).toHaveStyle({ backgroundImage: 'url(http://localhost/storage/picture.png)' });
  });

  it.each([
    ['cover', 'cover'],
    ['contain', 'contain'],
    ['fill', '100% 100%'],
  ] as const)('maps fit=%s to background-size %s', (fit, expectedSize) => {
    const config = { ...pictureWidgetDefaultConfig, fit };

    expect(renderPicture(config)).toHaveStyle({ backgroundSize: expectedSize });
  });

  it('renders no overlay when overlay_opacity is 0', () => {
    expect(renderPicture().children.length).toBe(0);
  });

  it('renders a dark overlay at the configured opacity', () => {
    const config = { ...pictureWidgetDefaultConfig, overlay_opacity: 0.4 };
    const picture = renderPicture(config);

    expect(picture.children.length).toBe(1);
    expect(picture.children[0]).toHaveStyle({ background: 'rgba(0, 0, 0, 0.4)' });
  });
});
