import type { CSSProperties } from 'react';

/**
 * Shared "scale, don't scroll" mechanism for every card-shaped widget
 * (server-card, server-group-card, game-card). CSS container queries over
 * a JS ResizeObserver — cheaper (no re-render loop) and the platform's
 * native answer to "size this relative to my own box, not the viewport".
 *
 * containerType: 'size' needs a *definite* height on this same element,
 * not just somewhere up the tree — every card root that spreads these
 * styles must sit inside a parent that actually gives it one:
 * PageLayoutWidgetContainer's flex:1 content wrapper for a single card, or
 * an explicit per-row height in an 'all'-mode grid (see GameCardWidget).
 * Without that, the container query context never establishes and the
 * clamp()s just sit at their upper bound.
 */
export const cardContainerStyle: CSSProperties = {
  containerType: 'size',
  height: '100%',
  width: '100%',
  overflow: 'hidden',
  boxSizing: 'border-box',
};

export const cardPaddingStyle: CSSProperties = {
  padding: 'clamp(6px, 6cqh, 16px)',
};

/**
 * The icon-before-name row (see CardIcon) — icon and title share this flex
 * row instead of each sitting in its own block-level element, which is
 * what stacked them vertically before this existed. `minWidth: 0` on the
 * row lets the title's own `text-overflow: ellipsis` (cardTitleStyle)
 * actually kick in inside a flex child, which otherwise refuses to shrink
 * below its content's natural width.
 */
export const cardHeaderRowStyle: CSSProperties = {
  display: 'flex',
  flexDirection: 'row',
  alignItems: 'center',
  gap: 'clamp(4px, 4cqh, 10px)',
  minWidth: 0,
};

/**
 * Every bound in every clamp() below is multiplied by
 * `--card-text-scale` (see PageLayoutWidgetContainer, which sets it from
 * the universal style system's resolved `textScale` — WidgetStyleSection's
 * percentage adjustment for self-scaling widgets). `var(--card-text-scale,
 * 1)` defaults to a no-op 1× when nothing overrides it, so a widget
 * nobody's touched the style of renders at exactly the same size as
 * before this existed. Scaling MIN/PREF/MAX together (not just the
 * preferred value) is what makes the adjustment actually visible at the
 * extremes of the container-query range, not just in the middle —
 * multiplying only the preferred value would have no effect once the
 * container is small/large enough to hit the min/max instead. Each
 * property keeps its own relative min/pref/max, so the title stays
 * proportionally larger than the body/meta text at every scale factor —
 * "nudge the whole card" without breaking that hierarchy.
 */
function scaledClamp(minRem: number, prefCqh: number, maxRem: number): string {
  return `clamp(calc(${minRem}rem * var(--card-text-scale, 1)), calc(${prefCqh}cqh * var(--card-text-scale, 1)), calc(${maxRem}rem * var(--card-text-scale, 1)))`;
}

export const cardTitleStyle: CSSProperties = {
  margin: '0 0 clamp(2px, 2cqh, 8px)',
  fontSize: scaledClamp(0.7, 9, 1.25),
  overflow: 'hidden',
  textOverflow: 'ellipsis',
  whiteSpace: 'nowrap',
  minWidth: 0,
};

export const cardBodyStyle: CSSProperties = {
  fontSize: scaledClamp(0.6, 6, 0.9),
  overflow: 'hidden',
  textOverflow: 'ellipsis',
  whiteSpace: 'nowrap',
};

export const cardMetaStyle: CSSProperties = {
  fontSize: scaledClamp(0.55, 5, 0.8),
  opacity: 0.85,
};
