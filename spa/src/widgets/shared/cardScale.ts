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

export const cardTitleStyle: CSSProperties = {
  margin: '0 0 clamp(2px, 2cqh, 8px)',
  fontSize: 'clamp(0.7rem, 9cqh, 1.25rem)',
  overflow: 'hidden',
  textOverflow: 'ellipsis',
  whiteSpace: 'nowrap',
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
