import { registerServerLayoutWidget } from './registry';
import { ServerBannerWidget, ServerBannerWidgetConfigForm, serverBannerWidgetDefaultConfig } from './ServerBannerWidget';
import { ServerStatusWidget, ServerStatusWidgetConfigForm, serverStatusWidgetDefaultConfig } from './ServerStatusWidget';
import { ServerNameWidget, ServerNameWidgetConfigForm, serverNameWidgetDefaultConfig } from './ServerNameWidget';
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
  // The one widget type other widgets can be layered onto — see
  // registry.ts's layerable/layerTarget docblock.
  layerTarget: true,
});

registerServerLayoutWidget({
  type: 'server-status',
  label: 'Status',
  component: ServerStatusWidget,
  configForm: ServerStatusWidgetConfigForm,
  defaultConfig: serverStatusWidgetDefaultConfig,
  defaultWidth: 3,
  defaultHeight: 2,
  layerable: true,
});

registerServerLayoutWidget({
  type: 'server-name',
  label: 'Server Name',
  component: ServerNameWidget,
  configForm: ServerNameWidgetConfigForm,
  defaultConfig: serverNameWidgetDefaultConfig,
  defaultWidth: 4,
  defaultHeight: 1,
  layerable: true,
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
