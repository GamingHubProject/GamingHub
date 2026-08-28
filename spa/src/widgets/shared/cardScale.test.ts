import { describe, expect, it } from 'vitest';
import { cardBodyStyle, cardMetaStyle, cardTitleStyle } from './cardScale';

describe('cardScale text sizing', () => {
  it('multiplies every bound of the clamp() by --card-text-scale, not just the preferred value', () => {
    // Every bound (min/pref/max) must reference the var — multiplying
    // only the preferred value would have no visible effect once a
    // container is small/large enough to hit the min/max instead of the
    // preferred value.
    expect(cardTitleStyle.fontSize).toContain('calc(0.7rem * var(--card-text-scale, 1))');
    expect(cardTitleStyle.fontSize).toContain('calc(9cqh * var(--card-text-scale, 1))');
    expect(cardTitleStyle.fontSize).toContain('calc(1.25rem * var(--card-text-scale, 1))');
  });

  it('defaults to a 1x no-op via the CSS var fallback, reproducing the pre-existing look when nothing overrides it', () => {
    expect(cardTitleStyle.fontSize).toContain('var(--card-text-scale, 1)');
    expect(cardBodyStyle.fontSize).toContain('var(--card-text-scale, 1)');
    expect(cardMetaStyle.fontSize).toContain('var(--card-text-scale, 1)');
  });

  it('keeps each element at its own distinct relative size, so title stays bigger than body/meta at any scale factor', () => {
    expect(cardTitleStyle.fontSize).toContain('1.25rem');
    expect(cardBodyStyle.fontSize).toContain('0.9rem');
    expect(cardMetaStyle.fontSize).toContain('0.8rem');
  });
});
