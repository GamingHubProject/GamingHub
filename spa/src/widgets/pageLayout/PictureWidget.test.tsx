import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';
import { PictureWidget, pictureWidgetDefaultConfig } from './PictureWidget';
import type { PageLayoutWidgetContext } from './registry';

const context: PageLayoutWidgetContext = { subjectType: 'server' };
const ART = 'http://localhost/storage/picture.png';

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
    // With artwork: the fit modes now resolve through the shared builder
    // (widgets/shared/background.ts), which emits nothing at all when
    // there's no image — a background-size for an image that isn't there
    // was meaningless, and this is the one visible change from the move.
    const config = { ...pictureWidgetDefaultConfig, background_url: ART, fit };

    expect(renderPicture(config)).toHaveStyle({ backgroundSize: expectedSize });
  });

  it('emits no image CSS at all until artwork is picked', () => {
    const picture = renderPicture({ ...pictureWidgetDefaultConfig, fit: 'contain' });

    expect(picture.style.backgroundImage).toBeFalsy();
    expect(picture.style.backgroundSize).toBeFalsy();
  });

  it('centres the artwork by default', () => {
    const config = { ...pictureWidgetDefaultConfig, background_url: ART };

    expect(renderPicture(config)).toHaveStyle({ backgroundPosition: 'center' });
  });

  it('applies the configured position so text beside the picture can clear its subject', () => {
    const config = { ...pictureWidgetDefaultConfig, background_url: ART, position: 'right top' as const };

    expect(renderPicture(config)).toHaveStyle({ backgroundPosition: 'right top' });
  });

  it('centres a picture saved before the position control existed', () => {
    // A stored config reaches the component as-is — PageLayoutWidgetContainer
    // never merges it against defaultConfig — so the missing key has to
    // resolve here, to the behaviour that widget already had.
    const { position, ...legacy } = { ...pictureWidgetDefaultConfig, background_url: ART };

    expect(renderPicture(legacy as typeof pictureWidgetDefaultConfig)).toHaveStyle({ backgroundPosition: 'center' });
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
