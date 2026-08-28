import { describe, expect, it } from 'vitest';
import { hexWithOpacity, resolveWidgetStyle } from './widgetStyle';

describe('resolveWidgetStyle', () => {
  it('falls back to the hardcoded baseline (border on, 1px, no text/background) when nothing is set anywhere', () => {
    const resolved = resolveWidgetStyle(null, null);

    expect(resolved).toEqual({
      borderEnabled: true,
      borderThickness: 1,
      textSize: undefined,
      textColor: undefined,
      backgroundColor: undefined,
      backgroundOpacity: 1,
    });
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
