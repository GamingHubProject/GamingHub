import { describe, expect, it } from 'vitest';
import { backgroundCss, gradientCss, hexWithOpacity } from './background';
import type { BackgroundSpec } from './background';

function spec(overrides: Partial<BackgroundSpec> = {}): BackgroundSpec {
  return {
    type: 'color',
    color: undefined,
    opacity: 1,
    pattern: undefined,
    patternColor: undefined,
    imageUrl: undefined,
    imageFit: 'cover',
    gradient: undefined,
    ...overrides,
  };
}

describe('gradientCss', () => {
  it('builds a linear gradient from its angle and stops', () => {
    const css = gradientCss({
      kind: 'linear',
      angle: 135,
      stops: [
        { color: '#000000', position: 0 },
        { color: '#ffffff', position: 100 },
      ],
    });

    expect(css).toBe('linear-gradient(135deg, #000000 0%, #ffffff 100%)');
  });

  it('ignores the angle for a radial gradient, which has no direction', () => {
    const css = gradientCss({
      kind: 'radial',
      angle: 135,
      stops: [
        { color: '#000000', position: 0 },
        { color: '#ffffff', position: 100 },
      ],
    });

    expect(css).toContain('radial-gradient');
    expect(css).not.toContain('135deg');
  });

  it('supports a third stop', () => {
    const css = gradientCss({
      kind: 'linear',
      angle: 90,
      stops: [
        { color: '#ff0000', position: 0 },
        { color: '#00ff00', position: 50 },
        { color: '#0000ff', position: 100 },
      ],
    });

    expect(css).toBe('linear-gradient(90deg, #ff0000 0%, #00ff00 50%, #0000ff 100%)');
  });

  it('applies opacity to every stop', () => {
    const css = gradientCss(
      { kind: 'linear', angle: 0, stops: [{ color: '#ff0000', position: 0 }, { color: '#0000ff', position: 100 }] },
      0.5
    );

    expect(css).toContain('rgba(255, 0, 0, 0.5)');
    expect(css).toContain('rgba(0, 0, 255, 0.5)');
  });

  it('refuses to draw a one-stop gradient, which is just a solid colour', () => {
    expect(gradientCss({ kind: 'linear', angle: 0, stops: [{ color: '#ff0000', position: 0 }] })).toBeNull();
    expect(gradientCss(undefined)).toBeNull();
  });
});

describe('backgroundCss', () => {
  it('returns nothing at all when no type is configured', () => {
    expect(backgroundCss(spec())).toEqual({});
  });

  it('renders a solid colour with its opacity', () => {
    expect(backgroundCss(spec({ color: '#ff0000', opacity: 0.5 }))).toEqual({
      backgroundColor: 'rgba(255, 0, 0, 0.5)',
    });
  });

  it('renders a gradient as a background-image over the base colour', () => {
    const css = backgroundCss(
      spec({
        type: 'gradient',
        color: '#000000',
        gradient: { kind: 'linear', angle: 45, stops: [{ color: '#a0a0a0', position: 0 }, { color: '#ffffff', position: 100 }] },
      })
    );

    expect(css.backgroundImage).toContain('linear-gradient(45deg');
    expect(css.backgroundColor).toBe('rgba(0, 0, 0, 1)');
  });

  it('falls back to the base colour when a gradient has too few stops to draw', () => {
    const css = backgroundCss(
      spec({ type: 'gradient', color: '#123456', gradient: { kind: 'linear', angle: 0, stops: [{ color: '#fff', position: 0 }] } })
    );

    expect(css).toEqual({ backgroundColor: 'rgba(18, 52, 86, 1)' });
  });

  it('draws a pattern over the base colour, tinting both by the same opacity', () => {
    const css = backgroundCss(
      spec({ type: 'pattern', color: '#ffffff', pattern: 'dots', patternColor: '#000000', opacity: 0.5 })
    );

    expect(css.backgroundColor).toBe('rgba(255, 255, 255, 0.5)');
    expect(css.backgroundImage).toContain('rgba(0, 0, 0, 0.5)');
    expect(css.backgroundSize).toBe('12px 12px');
  });

  it('degrades an unknown pattern to the base colour rather than throwing', () => {
    const css = backgroundCss(spec({ type: 'pattern', color: '#ffffff', pattern: 'nope', patternColor: '#000' }));

    expect(css).toEqual({ backgroundColor: 'rgba(255, 255, 255, 1)' });
  });

  it('renders a tiled image as a repeat at natural size', () => {
    const css = backgroundCss(spec({ type: 'image', imageUrl: 'https://x.test/t.png', imageFit: 'tile' }));

    expect(css.backgroundSize).toBe('auto');
    expect(css.backgroundRepeat).toBe('repeat');
  });

  it('renders a covering image without repeating it', () => {
    const css = backgroundCss(spec({ type: 'image', imageUrl: 'https://x.test/b.png', imageFit: 'cover' }));

    expect(css.backgroundSize).toBe('cover');
    expect(css.backgroundRepeat).toBe('no-repeat');
    expect(css.backgroundPosition).toBe('center');
  });

  it('falls back to the base colour when image mode has no image yet', () => {
    expect(backgroundCss(spec({ type: 'image', color: '#0000ff' }))).toEqual({
      backgroundColor: 'rgba(0, 0, 255, 1)',
    });
  });
});

describe('hexWithOpacity', () => {
  it('converts hex plus opacity into rgba', () => {
    expect(hexWithOpacity('#ff0000', 0.5)).toBe('rgba(255, 0, 0, 0.5)');
  });

  it('passes through anything that is not a recognisable hex colour', () => {
    expect(hexWithOpacity('not-a-color', 0.5)).toBe('not-a-color');
  });
});
