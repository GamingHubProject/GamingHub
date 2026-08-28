import type { ComponentType } from 'react';
import type { Game, Server } from '../../api/types';
import type { ResolvedWidgetStyle } from '../shared/widgetStyle';

export interface PageLayoutWidgetConfigFormProps<TConfig> {
  config: TConfig;
  onChange: (next: TConfig) => void;
}

/** Every page type that can hold an admin-editable widget layout — see
 *  PageLayoutEditor. 'home' is the singleton main Portal page, 'games-list'
 *  the singleton /games listing page. */
export type PageLayoutSubjectType = 'server' | 'game' | 'home' | 'games-list';

/**
 * What a widget component actually has to work with, beyond its own
 * config. Only the field matching the current page is ever populated
 * (server on a Server page, game on a Game page, neither on Home) — a
 * widget declaring `validFor` trusts that field is present rather than
 * checking for undefined itself, the same way the old server-only
 * components trusted `server` was always there.
 */
export interface PageLayoutWidgetContext {
  subjectType: PageLayoutSubjectType;
  server?: Server;
  game?: Game;
}

/**
 * Not the same shape as widgets/registry.ts's WidgetDefinition — a page
 * layout widget's component receives its subject via `context` instead of
 * fetching its own data via a config.server_id like the dashboard's
 * ServerStatusWidget does (a page layout widget always has exactly one
 * subject in scope, determined by which page it's on).
 */
export interface PageLayoutWidgetDefinition<TConfig = Record<string, unknown>> {
  type: string;
  label: string;
  /** Grouping for the Add Widget picker (see AddPageLayoutWidgetModal) —
   *  purely a UI label, not used for anything else. */
  category: 'Server' | 'Game' | 'General';
  /** Which page types this widget can be added to. Enforced by the Add
   *  Widget picker (it filters the list to the current page's subject
   *  type) — not re-checked server-side, same trust boundary the backend
   *  already has for widget_type in general (an opaque string + config
   *  blob it never validates the meaning of). */
  validFor: PageLayoutSubjectType[];
  /**
   * `layered` is only ever true for a `layerable: true` widget currently
   * overlapping the banner (see PageLayoutEditor's layeredWidgetIds). A
   * component that never expects to be layerable can safely ignore the
   * prop entirely.
   */
  /**
   * `resolvedStyle` is the universal Border/Text/Background result (see
   * widgets/shared/widgetStyle.ts) — Border/Background are already
   * applied centrally by PageLayoutWidgetContainer's own chrome, nothing
   * to do there. Text is passed through instead of also being forced via
   * CSS inheritance: a widget with its own conditional text styling
   * (server-name only applies a custom color while `layered`, for
   * legibility against arbitrary background art) needs the raw resolved
   * value to decide *whether* to apply it, not just inherit it
   * unconditionally. A component that has no text-styling opinion of its
   * own can ignore this prop entirely.
   */
  component: ComponentType<{ context: PageLayoutWidgetContext; config: TConfig; layered?: boolean; resolvedStyle?: ResolvedWidgetStyle }>;
  /** Sensible starting size when an admin adds this widget — types differ
   *  enough (a wide short banner vs. a small status badge) that one
   *  default for all of them would look wrong for most of them. */
  defaultWidth: number;
  defaultHeight: number;
  defaultConfig: TConfig;
  /** Widgets without one just don't show a settings gear at all — there's
   *  nothing to raw-JSON-fallback to like the dashboard's WidgetConfigModal
   *  does, since every field here is meant to be a real toggle, not a
   *  blind textarea. */
  configForm?: ComponentType<PageLayoutWidgetConfigFormProps<TConfig>>;
  /**
   * Overlap guardrail (see PageLayoutEditor's isValidOverlapLayout): the
   * grid runs with allowOverlap=true for the whole page, which would
   * otherwise let any two widgets get dragged on top of each other. A
   * dropped layout is only accepted when every overlapping pair is
   * exactly one `layerable: true` widget over one `layerTarget: true`
   * widget — every other combination (two layerables, a layerable over a
   * non-target, two targets, ...) gets rejected and the drag reverts.
   * Neither flag is set on most widget types, which keeps their normal
   * push/collision behavior unchanged.
   */
  layerable?: boolean;
  layerTarget?: boolean;
  /**
   * Always render without the card border/background (read-only view;
   * edit mode still shows the drag-handle header, same as a layered
   * widget) — for a widget that IS a page's own content rather than a
   * card sitting on the page, e.g. game-card in 'all' mode standing in
   * for what used to be an unboxed, hardcoded games grid. Unlike
   * `layered`, this isn't conditional on overlapping anything; it's a
   * static property of the widget type itself.
   */
  chromeless?: boolean;
  /**
   * This widget already scales its own text proportionally via container
   * queries (see widgets/shared/cardScale.ts) — a fixed Text size/color
   * override from the universal style system would be silently
   * ineffective (an explicit inline style always wins over an inherited
   * one), so WidgetStyleSection disables the Text controls entirely for
   * a widget flagged this way rather than accepting a setting that won't
   * visibly apply.
   */
  selfScaling?: boolean;
}

const registry = new Map<string, PageLayoutWidgetDefinition<any>>();

export function registerPageLayoutWidget<TConfig>(definition: PageLayoutWidgetDefinition<TConfig>): void {
  registry.set(definition.type, definition);
}

export function getPageLayoutWidgetDefinition(type: string): PageLayoutWidgetDefinition | undefined {
  return registry.get(type);
}

export function listPageLayoutWidgetDefinitions(): PageLayoutWidgetDefinition[] {
  return Array.from(registry.values());
}
