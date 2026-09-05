import { describe, expect, it } from 'vitest';
import { regionAccent, regionCss } from './regionStyle';

describe('regionCss', () => {
  it('falls back to the surface token when no background is configured', () => {
    // What the header looked like before it had a background of its own.
    expect(regionCss({}, 'bottom').backgroundColor).toBe('var(--surface, transparent)');
  });

  it('puts the border on the edge the caller names', () => {
    expect(regionCss({}, 'bottom')).toHaveProperty('borderBottom');
    expect(regionCss({}, 'right')).toHaveProperty('borderRight');
    // No "which side" control exists, so the wrong edge can never be set.
    expect(regionCss({}, 'right')).not.toHaveProperty('borderBottom');
  });

  it('drops the background and the edge together when transparent', () => {
    // A divider floating over a page background is the seam transparency
    // exists to remove.
    const css = regionCss({ transparent: true }, 'right');

    expect(css.background).toBe('transparent');
    expect(css.borderRight).toBe('none');
  });

  it('styles two regions independently from the same resolver', () => {
    const header = regionCss({ transparent: true }, 'bottom');
    const sidebar = regionCss({ transparent: false, text_color: '#ff0000' }, 'right');

    expect(header.background).toBe('transparent');
    expect(sidebar.backgroundColor).toBe('var(--surface, transparent)');
    expect(sidebar.color).toBe('#ff0000');
  });

  it('renders a gradient background like anywhere else that draws one', () => {
    const css = regionCss(
      {
        background: {
          type: 'gradient',
          gradient: { kind: 'linear', angle: 90, stops: [{ color: '#000', position: 0 }, { color: '#fff', position: 100 }] },
        },
      },
      'right'
    );

    expect(css.backgroundImage).toContain('linear-gradient(90deg');
  });

  it('omits the edge entirely at zero thickness', () => {
    expect(regionCss({ border: { thickness: 0 } }, 'right').borderRight).toBe('none');
  });

  it('uses the given edge colour, else the border token', () => {
    expect(regionCss({ border: { color: '#123456', thickness: 2 } }, 'right').borderRight).toBe('2px solid #123456');
    expect(regionCss({ border: { thickness: 1 } }, 'right').borderRight).toBe('1px solid var(--border, #ddd)');
  });

  it('applies a named shadow preset and nothing at all for none', () => {
    expect(regionCss({ shadow: 'soft' }, 'right').boxShadow).toBeTruthy();
    expect(regionCss({ shadow: 'strong' }, 'right').boxShadow).not.toBe(regionCss({ shadow: 'soft' }, 'right').boxShadow);
    expect(regionCss({ shadow: 'none' }, 'right')).not.toHaveProperty('boxShadow');
  });

  it('returns nothing for a region that was never configured', () => {
    expect(regionCss(undefined, 'right')).toEqual({});
  });
});

describe('regionAccent', () => {
  it('uses the region colour when set', () => {
    expect(regionAccent({ accent_color: '#ff0000' })).toBe('#ff0000');
  });

  it('falls back to the site accent, so a region needn\'t restate it', () => {
    expect(regionAccent({})).toBe('var(--accent, inherit)');
    expect(regionAccent({ accent_color: null })).toBe('var(--accent, inherit)');
  });
});
