import type { ComponentType } from 'react';
import type { Server } from '../../api/types';

/**
 * Deliberately not the same shape as widgets/registry.ts's WidgetDefinition
 * — a server layout widget has no per-instance config to edit (all 5 types
 * just render a slice of the one Server object the page already loaded),
 * so there's no configForm/defaultConfig concept here, and the component
 * receives the server directly as a prop instead of fetching its own data
 * via a config.server_id like the dashboard's ServerStatusWidget does.
 */
export interface ServerLayoutWidgetDefinition {
  type: string;
  label: string;
  component: ComponentType<{ server: Server }>;
  /** Sensible starting size when an admin adds this widget — types differ
   *  enough (a wide short banner vs. a small status badge) that one
   *  default for all of them would look wrong for most of them. */
  defaultWidth: number;
  defaultHeight: number;
}

const registry = new Map<string, ServerLayoutWidgetDefinition>();

export function registerServerLayoutWidget(definition: ServerLayoutWidgetDefinition): void {
  registry.set(definition.type, definition);
}

export function getServerLayoutWidgetDefinition(type: string): ServerLayoutWidgetDefinition | undefined {
  return registry.get(type);
}

export function listServerLayoutWidgetDefinitions(): ServerLayoutWidgetDefinition[] {
  return Array.from(registry.values());
}
