import { registerServerLayoutWidget } from './registry';
import { ServerBannerWidget, ServerBannerWidgetConfigForm, serverBannerWidgetDefaultConfig } from './ServerBannerWidget';
import { ServerStatusWidget, ServerStatusWidgetConfigForm, serverStatusWidgetDefaultConfig } from './ServerStatusWidget';
import { ServerMetricsWidget } from './ServerMetricsWidget';
import { ServerPlayerCountWidget } from './ServerPlayerCountWidget';
import { ServerAllocationsWidget } from './ServerAllocationsWidget';

registerServerLayoutWidget({
  type: 'server-banner',
  label: 'Banner',
  component: ServerBannerWidget,
  configForm: ServerBannerWidgetConfigForm,
  defaultConfig: serverBannerWidgetDefaultConfig,
  defaultWidth: 12,
  defaultHeight: 2,
});

registerServerLayoutWidget({
  type: 'server-status',
  label: 'Status',
  component: ServerStatusWidget,
  configForm: ServerStatusWidgetConfigForm,
  defaultConfig: serverStatusWidgetDefaultConfig,
  defaultWidth: 3,
  defaultHeight: 2,
});

registerServerLayoutWidget({
  type: 'server-metrics',
  label: 'Metrics',
  component: ServerMetricsWidget,
  defaultConfig: {},
  defaultWidth: 4,
  defaultHeight: 3,
});

registerServerLayoutWidget({
  type: 'server-player-count',
  label: 'Player Count',
  component: ServerPlayerCountWidget,
  defaultConfig: {},
  defaultWidth: 3,
  defaultHeight: 2,
});

registerServerLayoutWidget({
  type: 'server-allocations',
  label: 'Allocations',
  component: ServerAllocationsWidget,
  defaultConfig: {},
  defaultWidth: 4,
  defaultHeight: 3,
});

export {
  getServerLayoutWidgetDefinition,
  listServerLayoutWidgetDefinitions,
} from './registry';
