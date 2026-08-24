import { registerPageLayoutWidget } from './registry';
import { ServerBannerWidget, ServerBannerWidgetConfigForm, serverBannerWidgetDefaultConfig } from './ServerBannerWidget';
import { ServerStatusWidget, ServerStatusWidgetConfigForm, serverStatusWidgetDefaultConfig } from './ServerStatusWidget';
import { ServerNameWidget, ServerNameWidgetConfigForm, serverNameWidgetDefaultConfig } from './ServerNameWidget';
import { ServerMetricsWidget } from './ServerMetricsWidget';
import { ServerPlayerCountWidget } from './ServerPlayerCountWidget';
import { ServerAllocationsWidget } from './ServerAllocationsWidget';

// Every widget type today is Server-only (validFor: ['server']) — the
// page_layouts generalization added Game/Home as page types that *can*
// hold a layout, but deliberately shipped no new widget types for them
// this pass (see the design discussion). A future Game/General widget
// just adds 'game'/'home' to its own validFor; nothing here needs to
// change for that.

registerPageLayoutWidget({
  type: 'server-banner',
  label: 'Banner',
  category: 'Server',
  validFor: ['server'],
  component: ServerBannerWidget,
  configForm: ServerBannerWidgetConfigForm,
  defaultConfig: serverBannerWidgetDefaultConfig,
  defaultWidth: 12,
  defaultHeight: 2,
  // The one widget type other widgets can be layered onto — see
  // registry.ts's layerable/layerTarget docblock.
  layerTarget: true,
});

registerPageLayoutWidget({
  type: 'server-status',
  label: 'Status',
  category: 'Server',
  validFor: ['server'],
  component: ServerStatusWidget,
  configForm: ServerStatusWidgetConfigForm,
  defaultConfig: serverStatusWidgetDefaultConfig,
  defaultWidth: 3,
  defaultHeight: 2,
  layerable: true,
});

registerPageLayoutWidget({
  type: 'server-name',
  label: 'Server Name',
  category: 'Server',
  validFor: ['server'],
  component: ServerNameWidget,
  configForm: ServerNameWidgetConfigForm,
  defaultConfig: serverNameWidgetDefaultConfig,
  defaultWidth: 4,
  defaultHeight: 1,
  layerable: true,
});

registerPageLayoutWidget({
  type: 'server-metrics',
  label: 'Metrics',
  category: 'Server',
  validFor: ['server'],
  component: ServerMetricsWidget,
  defaultConfig: {},
  defaultWidth: 4,
  defaultHeight: 3,
});

registerPageLayoutWidget({
  type: 'server-player-count',
  label: 'Player Count',
  category: 'Server',
  validFor: ['server'],
  component: ServerPlayerCountWidget,
  defaultConfig: {},
  defaultWidth: 3,
  defaultHeight: 2,
});

registerPageLayoutWidget({
  type: 'server-allocations',
  label: 'Allocations',
  category: 'Server',
  validFor: ['server'],
  component: ServerAllocationsWidget,
  defaultConfig: {},
  defaultWidth: 4,
  defaultHeight: 3,
});

export {
  getPageLayoutWidgetDefinition,
  listPageLayoutWidgetDefinitions,
} from './registry';
