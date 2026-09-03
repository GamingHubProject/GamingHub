import { describe, expect, it } from 'vitest';
import { BACKGROUND_PATTERNS, getBackgroundPattern, patternBackground } from './backgroundPattern';

describe('backgroundPattern', () => {
  it('draws every built-in pattern in the color it is given, so one pattern serves any palette', () => {
    for (const pattern of BACKGROUND_PATTERNS) {
      expect(pattern.image('#abcdef')).toContain('#abcdef');
    }
  });

  it('builds every pattern out of CSS gradients only — no url() references to files that would need exporting', () => {
    for (const pattern of BACKGROUND_PATTERNS) {
      expect(pattern.image('#000000')).toMatch(/gradient\(/);
      expect(pattern.image('#000000')).not.toContain('url(');
    }
  });

  it('gives every pattern a unique id, since a widget config stores the id alone', () => {
    const ids = BACKGROUND_PATTERNS.map((pattern) => pattern.id);

    expect(new Set(ids).size).toBe(ids.length);
  });

  it('returns the image plus a tile size for a size-based pattern', () => {
    const result = patternBackground('dots', '#ff0000');

    expect(result?.backgroundImage).toContain('radial-gradient');
    expect(result?.backgroundSize).toBe('12px 12px');
  });

  it('omits background-size for a repeating-gradient pattern, which carries its own period', () => {
    const result = patternBackground('diagonal-stripes', '#ff0000');

    expect(result?.backgroundImage).toContain('repeating-linear-gradient');
    expect(result?.backgroundSize).toBeUndefined();
  });

  it('returns null for an unknown id, so an old or hand-edited config degrades instead of throwing', () => {
    expect(patternBackground('nope', '#ff0000')).toBeNull();
    expect(patternBackground(undefined, '#ff0000')).toBeNull();
    expect(getBackgroundPattern('nope')).toBeUndefined();
  });
});
