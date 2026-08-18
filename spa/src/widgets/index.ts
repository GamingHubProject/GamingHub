import { registerWidget } from './registry';
import { ServerStatusWidget, ServerStatusWidgetConfigForm, type ServerStatusWidgetConfig } from './ServerStatusWidget';

registerWidget<ServerStatusWidgetConfig>({
  type: 'server-status',
  label: 'Server Status',
  component: ServerStatusWidget,
  configForm: ServerStatusWidgetConfigForm,
  defaultConfig: { server_id: null },
});

export { getWidgetDefinition, listWidgetDefinitions } from './registry';
