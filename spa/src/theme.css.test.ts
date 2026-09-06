import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * jsdom doesn't apply stylesheets, so nothing in the component tests can
 * notice if this rule disappears — and the components that depend on it
 * no longer say so themselves, because the whole point was to stop
 * repeating it. This reads the file instead: a blunt guard, but it fails
 * loudly if the reset is ever deleted, which is the only failure mode
 * that matters here.
 */
describe('theme.css', () => {
  const css = readFileSync(resolve(__dirname, 'theme.css'), 'utf8');

  it('sets border-box sizing globally', () => {
    expect(css).toMatch(/\*,\s*\*::before,\s*\*::after\s*\{\s*box-sizing:\s*border-box;/);
  });

  it('still resets the body margin, which the reset does not cover', () => {
    // box-sizing says nothing about margins; the body's default 8px is a
    // separate thing this file has always had to undo.
    expect(css).toMatch(/body\s*\{[^}]*margin:\s*0;/);
  });
});
