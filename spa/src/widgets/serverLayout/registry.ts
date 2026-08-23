import type { ComponentType } from 'react';
import type { Server } from '../../api/types';

export interface ServerLayoutWidgetConfigFormProps<TConfig> {
  config: TConfig;
  onChange: (next: TConfig) => void;
}

/**
 * Not the same shape as widgets/registry.ts's WidgetDefinition — a server
 * layout widget's component receives the server directly as a prop instead
 * of fetching its own data via a config.server_id like the dashboard's
 * ServerStatusWidget does (there's only ever one server on this page).
 * config/configForm are both optional — most of the 5 types still have
 * nothing to configure; only server-status and server-banner define one
 * so far, each a single boolean toggle. Deliberately flat (config is just
 * `Record<string, unknown>`, no grouping/nesting) — see the toggles added
 * for those two types.
 */
export interface ServerLayoutWidgetDefinition<TConfig = Record<string, unknown>> {
  type: string;
  label: string;
  component: ComponentType<{ server: Server; config: TConfig }>;
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
  configForm?: ComponentType<ServerLayoutWidgetConfigFormProps<TConfig>>;
}

const registry = new Map<string, ServerLayoutWidgetDefinition<any>>();

export function registerServerLayoutWidget<TConfig>(definition: ServerLayoutWidgetDefinition<TConfig>): void {
  registry.set(definition.type, definition);
}

export function getServerLayoutWidgetDefinition(type: string): ServerLayoutWidgetDefinition | undefined {
  return registry.get(type);
}

export function listServerLayoutWidgetDefinitions(): ServerLayoutWidgetDefinition[] {
  return Array.from(registry.values());
}
