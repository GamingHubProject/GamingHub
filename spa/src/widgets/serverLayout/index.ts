import { registerServerLayoutWidget } from './registry';
import { ServerBannerWidget } from './ServerBannerWidget';
import { ServerStatusWidget } from './ServerStatusWidget';
import { ServerMetricsWidget } from './ServerMetricsWidget';
import { ServerPlayerCountWidget } from './ServerPlayerCountWidget';
import { ServerAllocationsWidget } from './ServerAllocationsWidget';

registerServerLayoutWidget({
  type: 'server-banner',
  label: 'Banner',
  component: ServerBannerWidget,
  defaultWidth: 12,
  defaultHeight: 2,
});

registerServerLayoutWidget({
  type: 'server-status',
  label: 'Status',
  component: ServerStatusWidget,
  defaultWidth: 3,
  defaultHeight: 2,
});

registerServerLayoutWidget({
  type: 'server-metrics',
  label: 'Metrics',
  component: ServerMetricsWidget,
  defaultWidth: 4,
  defaultHeight: 3,
});

registerServerLayoutWidget({
  type: 'server-player-count',
  label: 'Player Count',
  component: ServerPlayerCountWidget,
  defaultWidth: 3,
  defaultHeight: 2,
});

registerServerLayoutWidget({
  type: 'server-allocations',
  label: 'Allocations',
  component: ServerAllocationsWidget,
  defaultWidth: 4,
  defaultHeight: 3,
});

export {
  getServerLayoutWidgetDefinition,
  listServerLayoutWidgetDefinitions,
} from './registry';
