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

export const cardTitleStyle: CSSProperties = {
  margin: '0 0 clamp(2px, 2cqh, 8px)',
  fontSize: 'clamp(0.7rem, 9cqh, 1.25rem)',
  overflow: 'hidden',
  textOverflow: 'ellipsis',
  whiteSpace: 'nowrap',
  minWidth: 0,
};

export const cardBodyStyle: CSSProperties = {
  fontSize: 'clamp(0.6rem, 6cqh, 0.9rem)',
  overflow: 'hidden',
  textOverflow: 'ellipsis',
  whiteSpace: 'nowrap',
};

export const cardMetaStyle: CSSProperties = {
  fontSize: 'clamp(0.55rem, 5cqh, 0.8rem)',
  opacity: 0.85,
};
