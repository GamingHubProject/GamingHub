import { registerPageLayoutWidget } from './registry';
import { PictureWidget, PictureWidgetConfigForm, pictureWidgetDefaultConfig } from './PictureWidget';
import { ServerStatusWidget, ServerStatusWidgetConfigForm, serverStatusWidgetDefaultConfig } from './ServerStatusWidget';
import { ServerNameWidget, ServerNameWidgetConfigForm, serverNameWidgetDefaultConfig } from './ServerNameWidget';
import { ServerMetricsWidget } from './ServerMetricsWidget';
import { ServerPlayerCountWidget } from './ServerPlayerCountWidget';
import { ServerAllocationsWidget } from './ServerAllocationsWidget';
import { GameCardWidget, GameCardWidgetConfigForm, gameCardWidgetDefaultConfig } from './GameCardWidget';
import { ServerCardWidget, ServerCardWidgetConfigForm, serverCardWidgetDefaultConfig } from './ServerCardWidget';
import { ServerGroupCardWidget, ServerGroupCardWidgetConfigForm, serverGroupCardWidgetDefaultConfig } from './ServerGroupCardWidget';

const ALL_PAGES = ['home', 'games-list', 'game', 'server'] as const;

registerPageLayoutWidget({
  type: 'picture',
  label: 'Picture',
  // No longer Server-specific — a generic background-image widget usable
  // on any page, so 'General' rather than 'Server' (was 'server-banner').
  category: 'General',
  validFor: [...ALL_PAGES],
  component: PictureWidget,
  configForm: PictureWidgetConfigForm,
  defaultConfig: pictureWidgetDefaultConfig,
  defaultWidth: 12,
  defaultHeight: 2,
  // The one widget type other widgets can be layered onto — see
  // registry.ts's layerable/layerTarget docblock. Per-*instance* opt-out
  // lives on the widget's own config (allow_layering) — see
  // PictureWidgetConfig and PageLayoutEditor's isValidOverlapLayout/
  // layeredWidgetIds, which both check the config on top of this flag.
  layerTarget: true,
});

registerPageLayoutWidget({
  type: 'server-status',
  label: 'Status',
  category: 'Server',
  // Genuinely Server-specific — reads live status/metrics off
  // context.server, which only a Server page's context ever populates.
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
  validFor: [...ALL_PAGES],
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
  validFor: [...ALL_PAGES],
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
  validFor: [...ALL_PAGES],
  component: ServerCardWidget,
  configForm: ServerCardWidgetConfigForm,
  defaultConfig: serverCardWidgetDefaultConfig,
  defaultWidth: 4,
  defaultHeight: 2,
});

registerPageLayoutWidget({
  type: 'server-group-card',
  label: 'Server Group Card',
  category: 'Server',
  validFor: [...ALL_PAGES],
  component: ServerGroupCardWidget,
  configForm: ServerGroupCardWidgetConfigForm,
  defaultConfig: serverGroupCardWidgetDefaultConfig,
  defaultWidth: 4,
  defaultHeight: 2,
});

export {
  getPageLayoutWidgetDefinition,
  listPageLayoutWidgetDefinitions,
} from './registry';
