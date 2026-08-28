import { getPageLayoutWidgetDefinition } from '../widgets/pageLayout/registry';
import type { PageLayoutWidgetContext } from '../widgets/pageLayout/registry';
import { useWidgetStyleDefaults } from '../providers/ThemeProvider';
import { hexWithOpacity, resolveWidgetStyle } from '../widgets/shared/widgetStyle';
import type { PageLayoutWidget } from '../api/types';

/**
 * Always a bordered "card" (per the design brief), but the drag-handle
 * header bar with its label + remove/settings buttons only renders in
 * edit mode — a normal visitor to the page should see clean cards, not
 * admin-tool chrome. The grid itself also disables dragging/resizing
 * outright when not editable (see PageLayoutEditor's isDraggable/
 * isResizable), so this isn't just cosmetic. The settings gear now always
 * appears in edit mode — WidgetStyleSection (universal Border/Text/
 * Background) is available for every widget type, even the ones with no
 * configForm of their own.
 */
export function PageLayoutWidgetContainer({
  widget,
  context,
  editable,
  layered = false,
  onRemove,
  onEdit,
  selectable = false,
  selected = false,
  onToggleSelect,
  dragHandleClassName = 'widget-drag-handle',
}: {
  widget: PageLayoutWidget;
  context: PageLayoutWidgetContext;
  editable: boolean;
  /** True when this widget is currently overlapping the banner (see
   *  PageLayoutEditor's layeredWidgetIds) — drops the card border/background/
   *  scroll so the content floats directly on the banner image instead of
   *  sitting in its own visible box on top of it. */
  layered?: boolean;
  onRemove: () => void;
  onEdit: () => void;
  /** Only ever true for a top-level widget on the page's own grid — a
   *  widget already inside a Group can't be selected into a *different*
   *  grouping without first being ungrouped (confirmed scope boundary),
   *  so PageLayoutEditor never passes this when rendering a group's
   *  children. See PageLayoutEditor's "Group selected" flow. */
  selectable?: boolean;
  selected?: boolean;
  onToggleSelect?: () => void;
  /**
   * react-grid-layout's `draggableHandle` is a bare CSS selector matched
   * by walking up from the mousedown target — it has no concept of "which
   * grid instance" a match belongs to. A top-level widget's header and a
   * Group's own header both need the page grid's own handle class (so the
   * *outer* grid picks up their drag), but a widget rendered *inside* a
   * Group needs a different class entirely — otherwise the outer grid's
   * Draggable also matches a child's header and drags the whole Group
   * instead of the child (confirmed live: this was exactly the bug).
   * GroupWidgetContainer passes its own distinct class for its children;
   * every other caller uses the default, matching the page grid's own
   * draggableHandle in PageLayoutEditor.
   */
  dragHandleClassName?: string;
}) {
  const definition = getPageLayoutWidgetDefinition(widget.widget_type);
  const config = widget.config ?? definition?.defaultConfig ?? {};
  const chromeless = layered || (definition?.chromeless ?? false);
  const globalStyleDefaults = useWidgetStyleDefaults();
  const resolvedStyle = resolveWidgetStyle(widget.config, globalStyleDefaults);

  return (
    <div
      style={
        chromeless
          ? { height: '100%', display: 'flex', flexDirection: 'column' }
          : {
              // A layered/chromeless widget never gets a border or
              // background from here — it's meant to float transparently
              // (on a Picture, or as a page's own unboxed content), and
              // Border/Background overriding that would defeat the point.
              // Only reachable in this branch to begin with, so no extra
              // condition needed on top of resolvedStyle itself.
              border: resolvedStyle.borderEnabled ? `${resolvedStyle.borderThickness}px solid var(--border, #ddd)` : 'none',
              backgroundColor: resolvedStyle.backgroundColor
                ? hexWithOpacity(resolvedStyle.backgroundColor, resolvedStyle.backgroundOpacity)
                : undefined,
              borderRadius: 8,
              height: '100%',
              display: 'flex',
              flexDirection: 'column',
              overflow: 'hidden',
            }
      }
    >
      {editable && (
        <div
          className={dragHandleClassName}
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            padding: '4px 8px',
            borderBottom: '1px solid var(--border, #ddd)',
            cursor: 'move',
            background: 'var(--surface-muted, rgba(0,0,0,0.03))',
          }}
        >
          <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: '0.75rem', opacity: 0.7 }}>
            {selectable && (
              <input
                type="checkbox"
                className="widget-no-drag"
                aria-label="Select for grouping"
                checked={selected}
                onChange={onToggleSelect}
              />
            )}
            {definition?.label ?? widget.widget_type}
          </span>
          {/* widget-no-drag: same draggableCancel mechanism as the
              dashboard grid — without it these buttons start a drag
              instead of firing onClick. */}
          <div style={{ display: 'flex', gap: 4 }}>
            <button aria-label="Widget settings" className="widget-no-drag" onClick={onEdit}>
              ⚙
            </button>
            <button aria-label="Remove widget" className="widget-no-drag" onClick={onRemove}>
              ×
            </button>
          </div>
        </div>
      )}
      {/* overflow is keyed on `layered` alone, not `chromeless` — a
          layered widget's content is meant to escape onto the banner
          beneath it (overflow: visible), but a chromeless widget (e.g.
          game-card's 'all' mode) is still a normal, contained grid cell:
          its content should stay clipped within the widget's own box,
          same as any bordered widget. Conflating the two here previously
          made game-card's grid spill out past its resize handle instead
          of staying resizable as one block — confirmed live, not assumed.
          `hidden` (not `auto`) — a resized-down card scales its content
          via container queries (see widgets/shared/cardScale.ts) instead
          of scrolling, so overflow here means something clipped, not
          something the scale mechanism failed to catch.
          containerType: 'size' makes this box a container-query context
          for any card content inside it — this element has a definite
          height (flex:1 in a height:100% flex column), which is what
          `size` queries need. */}
      <div style={{ flex: 1, overflow: layered ? 'visible' : 'hidden', containerType: layered ? undefined : 'size' }}>
        {definition ? (
          <definition.component context={context} config={config} layered={layered} resolvedStyle={resolvedStyle} />
        ) : (
          <p style={{ padding: 12 }}>Unsupported widget type: {widget.widget_type}</p>
        )}
      </div>
    </div>
  );
}
