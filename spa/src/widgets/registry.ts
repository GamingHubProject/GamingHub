import type { ComponentType } from 'react';

export interface WidgetConfigFormProps<TConfig> {
  config: TConfig;
  onChange: (next: TConfig) => void;
}

export interface WidgetDefinition<TConfig = Record<string, unknown>> {
  type: string;
  label: string;
  component: ComponentType<{ widgetId: number; config: TConfig }>;
  /**
   * Optional per-type config editor. Widgets without one fall back to a
   * raw JSON textarea in the config modal — graceful, not blocking.
   */
  configForm?: ComponentType<WidgetConfigFormProps<TConfig>>;
  defaultConfig: TConfig;
}

const registry = new Map<string, WidgetDefinition<any>>();

export function registerWidget<TConfig>(definition: WidgetDefinition<TConfig>): void {
  registry.set(definition.type, definition);
}

export function getWidgetDefinition(type: string): WidgetDefinition | undefined {
  return registry.get(type);
}

export function listWidgetDefinitions(): WidgetDefinition[] {
  return Array.from(registry.values());
}
