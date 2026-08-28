import { describe, expect, it } from 'vitest';
import { contrastRatio, hexWithOpacity, resolveWidgetStyle } from './widgetStyle';

describe('resolveWidgetStyle', () => {
  it('falls back to the hardcoded baseline (border on, 1px, no color/radius override, no text/background, 1x card scale) when nothing is set anywhere', () => {
    const resolved = resolveWidgetStyle(null, null);

    expect(resolved).toEqual({
      borderEnabled: true,
      borderThickness: 1,
      borderColor: undefined,
      borderRadius: 8,
      textSize: undefined,
      textColor: undefined,
      textScale: 1,
      backgroundColor: undefined,
      backgroundOpacity: 1,
    });
  });

  it('resolves border color/radius and text scale through the same instance -> global -> fallback fallthrough as every other property', () => {
    const resolved = resolveWidgetStyle(
      { style: { border_color: '#123456' } },
      { border_color: '#abcdef', border_radius: 16, text_scale: 1.5 }
    );

    expect(resolved.borderColor).toBe('#123456'); // instance wins
    expect(resolved.borderRadius).toBe(16); // falls through to global
    expect(resolved.textScale).toBe(1.5); // falls through to global
  });

  it('uses the global default when the instance has no override', () => {
    const resolved = resolveWidgetStyle(null, { border_enabled: false, text_color: '#ff0000' });

    expect(resolved.borderEnabled).toBe(false);
    expect(resolved.textColor).toBe('#ff0000');
  });

  it("uses the instance's own override instead of the global default", () => {
    const resolved = resolveWidgetStyle(
      { style: { border_enabled: true, border_thickness: 5 } },
      { border_enabled: false, border_thickness: 2 }
    );

    expect(resolved.borderEnabled).toBe(true);
    expect(resolved.borderThickness).toBe(5);
  });

  it('resolves each property independently — an instance override on one field does not suppress the global default on another', () => {
    const resolved = resolveWidgetStyle(
      { style: { text_color: '#00ff00' } },
      { border_enabled: true, border_thickness: 3, text_color: '#ff0000', background_color: '#0000ff' }
    );

    expect(resolved.textColor).toBe('#00ff00'); // instance wins
    expect(resolved.borderThickness).toBe(3); // falls through to global
    expect(resolved.backgroundColor).toBe('#0000ff'); // falls through to global
  });

  it('treats an instance value of false/0 as a real override, not "unset" (only null/undefined mean sync to global)', () => {
    const resolved = resolveWidgetStyle({ style: { border_enabled: false } }, { border_enabled: true });

    expect(resolved.borderEnabled).toBe(false);
  });

  it('ignores a non-object config.style without throwing', () => {
    const resolved = resolveWidgetStyle({ style: 'not-an-object' } as any, null);

    expect(resolved.borderEnabled).toBe(true);
  });
});

describe('hexWithOpacity', () => {
  it('converts a #rrggbb hex color plus an opacity into rgba()', () => {
    expect(hexWithOpacity('#ff0000', 0.5)).toBe('rgba(255, 0, 0, 0.5)');
  });

  it('accepts a hex color without the leading #', () => {
    expect(hexWithOpacity('00ff00', 1)).toBe('rgba(0, 255, 0, 1)');
  });

  it('returns the input unchanged when it is not a recognizable hex color', () => {
    expect(hexWithOpacity('not-a-color', 0.5)).toBe('not-a-color');
  });
});

describe('contrastRatio', () => {
  it('returns the maximum 21:1 for pure black on pure white', () => {
    expect(contrastRatio('#000000', '#ffffff')).toBeCloseTo(21, 0);
  });

  it('returns 1:1 for identical colors (no contrast at all)', () => {
    expect(contrastRatio('#336699', '#336699')).toBeCloseTo(1, 5);
  });

  it('is symmetric — foreground/background order does not change the ratio', () => {
    const a = contrastRatio('#111111', '#eeeeee')!;
    const b = contrastRatio('#eeeeee', '#111111')!;

    expect(a).toBeCloseTo(b, 5);
  });

  it('returns a low ratio for a genuinely hard-to-read combination (dark gray on black)', () => {
    const ratio = contrastRatio('#222222', '#000000')!;

    expect(ratio).toBeLessThan(4.5);
  });

  it('returns null when either color is not a real hex color', () => {
    expect(contrastRatio('not-a-color', '#ffffff')).toBeNull();
    expect(contrastRatio('#000000', 'also-not-a-color')).toBeNull();
  });
});
