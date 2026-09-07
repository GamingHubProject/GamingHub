import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
// Registers the real page-layout widget types (side effect).
import './index';
import { listPageLayoutWidgetDefinitions } from './registry';

/**
 * Capability lives here; policy lives in PHP as the admin's checkbox list.
 * They are deliberately separate — an admin's list can only ever narrow
 * what the registry says is possible — but the checkboxes still have to
 * name real widgets, in both directions: an option for a widget nobody
 * registers is dead UI, and a profile widget missing from the options is
 * one no admin can turn on.
 */
describe('profile widget capability', () => {
  const capable = listPageLayoutWidgetDefinitions()
    .filter((definition) => definition.validFor.includes('user_profile'))
    .map((definition) => definition.type)
    .sort();

  const php = readFileSync(resolve(__dirname, '../../../../app/Profiles/ProfileWidgets.php'), 'utf8');
  const block = php.match(/public const CAPABLE = \[(.*?)\];/s);
  const offered = Array.from(block?.[1].matchAll(/'([a-z0-9-]+)'\s*=>/g) ?? [], (match) => match[1]).sort();

  it('offers an admin exactly the widgets that can render on a profile', () => {
    expect(offered).toEqual(capable);
  });

  it('starts every capable widget enabled, so the editor is not empty on day one', () => {
    const defaults = php.match(/public const DEFAULT_ENABLED = \[(.*?)\];/s);
    const enabled = Array.from(defaults?.[1].matchAll(/'([a-z0-9-]+)'/g) ?? [], (match) => match[1]).sort();

    expect(enabled).toEqual(capable);
  });
});
