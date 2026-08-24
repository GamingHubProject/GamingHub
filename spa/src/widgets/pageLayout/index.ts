import { registerPageLayoutWidget } from './registry';
import { ServerBannerWidget, ServerBannerWidgetConfigForm, serverBannerWidgetDefaultConfig } from './ServerBannerWidget';
import { ServerStatusWidget, ServerStatusWidgetConfigForm, serverStatusWidgetDefaultConfig } from './ServerStatusWidget';
import { ServerNameWidget, ServerNameWidgetConfigForm, serverNameWidgetDefaultConfig } from './ServerNameWidget';
import { ServerMetricsWidget } from './ServerMetricsWidget';
import { ServerPlayerCountWidget } from './ServerPlayerCountWidget';
import { ServerAllocationsWidget } from './ServerAllocationsWidget';
import { GameCardWidget, GameCardWidgetConfigForm, gameCardWidgetDefaultConfig } from './GameCardWidget';
import { ServerCardWidget, ServerCardWidgetConfigForm, serverCardWidgetDefaultConfig } from './ServerCardWidget';

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

registerPageLayoutWidget({
  type: 'game-card',
  label: 'Game Card',
  category: 'Game',
  validFor: ['home', 'games-list', 'server'],
  component: GameCardWidget,
  configForm: GameCardWidgetConfigForm,
  defaultConfig: gameCardWidgetDefaultConfig,
  defaultWidth: 12,
  defaultHeight: 4,
  // GameCard (both 'all' mode's grid of cards and a single card) already
  // has its own border — the outer widget card chrome would double-box
  // it, and 'all' mode is standing in for what used to be a completely
  // unboxed grid (see PageLayoutController's seeded DEFAULT_WIDGETS), so
  // an unchanged fresh-install look depends on this being chromeless.
  chromeless: true,
});

registerPageLayoutWidget({
  type: 'server-card',
  label: 'Server Card',
  category: 'Server',
  validFor: ['home', 'games-list', 'game'],
  component: ServerCardWidget,
  configForm: ServerCardWidgetConfigForm,
  defaultConfig: serverCardWidgetDefaultConfig,
  defaultWidth: 4,
  defaultHeight: 2,
});

export {
  getPageLayoutWidgetDefinition,
  listPageLayoutWidgetDefinitions,
} from './registry';
