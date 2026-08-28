import { describe, expect, it } from 'vitest';
// Registers the real widget types (side effect) — isValidOverlapLayout
// looks up layerable/layerTarget via the real registry, not a mock.
import '../widgets/pageLayout';
import { isValidOverlapLayout, layeredWidgetIds } from './PageLayoutEditor';
import type { Layout } from 'react-grid-layout';
import type { PageLayoutWidget } from '../api/types';

function widget(
  id: number,
  widget_type: string,
  x: number,
  y: number,
  w: number,
  h: number,
  config: Record<string, unknown> | null = null
): PageLayoutWidget {
  return { id, page_layout_id: 1, widget_type, config, position_x: x, position_y: y, width: w, height: h };
}

function layout(id: number, x: number, y: number, w: number, h: number): Layout {
  return { i: String(id), x, y, w, h };
}

describe('isValidOverlapLayout', () => {
  it('accepts non-overlapping widgets of any type', () => {
    const widgets = [widget(1, 'picture', 0, 0, 12, 2), widget(2, 'server-metrics', 0, 2, 4, 3)];
    const rgl = [layout(1, 0, 0, 12, 2), layout(2, 0, 2, 4, 3)];

    expect(isValidOverlapLayout(rgl, widgets)).toBe(true);
  });

  it('accepts a layerable widget (server-status) dragged onto the picture', () => {
    const widgets = [widget(1, 'picture', 0, 0, 12, 2), widget(2, 'server-status', 0, 0, 3, 2)];
    const rgl = [layout(1, 0, 0, 12, 2), layout(2, 0, 0, 3, 2)];

    expect(isValidOverlapLayout(rgl, widgets)).toBe(true);
  });

  it('accepts server-name dragged onto the picture', () => {
    const widgets = [widget(1, 'picture', 0, 0, 12, 2), widget(2, 'server-name', 0, 0, 4, 1)];
    const rgl = [layout(1, 0, 0, 12, 2), layout(2, 0, 0, 4, 1)];

    expect(isValidOverlapLayout(rgl, widgets)).toBe(true);
  });

  it('rejects two non-layerable widgets overlapping each other', () => {
    const widgets = [widget(1, 'server-metrics', 0, 0, 4, 3), widget(2, 'server-player-count', 0, 0, 3, 2)];
    const rgl = [layout(1, 0, 0, 4, 3), layout(2, 0, 0, 3, 2)];

    expect(isValidOverlapLayout(rgl, widgets)).toBe(false);
  });

  it('rejects a layerable widget overlapping something other than the picture', () => {
    const widgets = [widget(1, 'server-metrics', 0, 0, 4, 3), widget(2, 'server-status', 0, 0, 3, 2)];
    const rgl = [layout(1, 0, 0, 4, 3), layout(2, 0, 0, 3, 2)];

    expect(isValidOverlapLayout(rgl, widgets)).toBe(false);
  });

  it('rejects two layerable widgets overlapping each other (not the picture)', () => {
    const widgets = [widget(1, 'server-status', 0, 0, 3, 2), widget(2, 'server-name', 0, 0, 4, 1)];
    const rgl = [layout(1, 0, 0, 3, 2), layout(2, 0, 0, 4, 1)];

    expect(isValidOverlapLayout(rgl, widgets)).toBe(false);
  });

  it('rejects a picture overlapping another picture', () => {
    const widgets = [widget(1, 'picture', 0, 0, 12, 2), widget(2, 'picture', 0, 0, 12, 2)];
    const rgl = [layout(1, 0, 0, 12, 2), layout(2, 0, 0, 12, 2)];

    expect(isValidOverlapLayout(rgl, widgets)).toBe(false);
  });

  it('rejects a layerable widget dragged onto a picture with allow_layering off', () => {
    const widgets = [
      widget(1, 'picture', 0, 0, 12, 2, { allow_layering: false }),
      widget(2, 'server-name', 0, 0, 4, 1),
    ];
    const rgl = [layout(1, 0, 0, 12, 2), layout(2, 0, 0, 4, 1)];

    expect(isValidOverlapLayout(rgl, widgets)).toBe(false);
  });

  it('accepts a layerable widget dragged onto a picture with no config at all (allow_layering defaults true)', () => {
    const widgets = [widget(1, 'picture', 0, 0, 12, 2, null), widget(2, 'server-name', 0, 0, 4, 1)];
    const rgl = [layout(1, 0, 0, 12, 2), layout(2, 0, 0, 4, 1)];

    expect(isValidOverlapLayout(rgl, widgets)).toBe(true);
  });
});

describe('layeredWidgetIds', () => {
  it('flags a layerable widget overlapping the picture', () => {
    const widgets = [widget(1, 'picture', 0, 0, 12, 2), widget(2, 'server-name', 0, 0, 4, 1)];

    expect(layeredWidgetIds(widgets)).toEqual(new Set([2]));
  });

  it('does not flag a layerable widget that is not currently overlapping the picture', () => {
    const widgets = [widget(1, 'picture', 0, 0, 12, 2), widget(2, 'server-name', 0, 5, 4, 1)];

    expect(layeredWidgetIds(widgets)).toEqual(new Set());
  });

  it('never flags the picture itself or a non-layerable widget', () => {
    const widgets = [
      widget(1, 'picture', 0, 0, 12, 2),
      widget(2, 'server-name', 0, 0, 4, 1),
      widget(3, 'server-metrics', 0, 2, 4, 3),
    ];

    expect(layeredWidgetIds(widgets)).toEqual(new Set([2]));
  });

  it('does not flag a widget overlapping a picture with allow_layering off', () => {
    const widgets = [
      widget(1, 'picture', 0, 0, 12, 2, { allow_layering: false }),
      widget(2, 'server-name', 0, 0, 4, 1),
    ];

    expect(layeredWidgetIds(widgets)).toEqual(new Set());
  });
});
