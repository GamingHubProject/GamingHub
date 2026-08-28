import GridLayout, { WidthProvider, type Layout } from 'react-grid-layout';
import { PageLayoutWidgetContainer } from './PageLayoutWidgetContainer';
import type { PageLayoutWidgetContext } from '../widgets/pageLayout/registry';
import type { PageLayoutWidget } from '../api/types';

const ResponsiveGridLayout = WidthProvider(GridLayout);
const GRID_COLS = 12;
const ROW_HEIGHT = 80;

function layoutFor(widgets: PageLayoutWidget[]): Layout[] {
  return widgets.map((widget) => ({
    i: String(widget.id),
    x: widget.position_x,
    y: widget.position_y,
    w: widget.width,
    h: widget.height,
  }));
}

export type ChildLayoutChange = { id: number; position_x: number; position_y: number; width: number; height: number };

/**
 * A Group widget's own bounding box is one ordinary row in the *page's*
 * grid (see PageLayoutEditor) — dragging/resizing it there is already
 * free, no special logic needed, since react-grid-layout treats every
 * item atomically. What's special is *inside*: a second, independent
 * ResponsiveGridLayout, reusing the exact same WidthProvider pattern as
 * the page grid, so resizing the group's box reflows every child
 * proportionally (width is percentage-driven, recalculated from the
 * group's current pixel width on every render) with zero custom
 * scale-factor math — the same mechanism that already makes the page grid
 * itself responsive, just nested one level in. Height doesn't get the
 * same treatment (rowHeight is a fixed px value at every nesting level,
 * exactly like the page grid), so a taller group just reveals more rows
 * rather than stretching children — consistent with, not a new exception
 * to, how the top-level grid already behaves.
 *
 * No layering support inside a group (no allowOverlap, no layered/
 * chromeless computation for children) — deliberately out of scope, not
 * an oversight; nesting the overlap-guardrail logic one level in wasn't
 * asked for and meaningfully complicates it for no confirmed use case.
 *
 * Children are rendered via the same PageLayoutWidgetContainer every
 * top-level widget uses — reused, not reimplemented — with `selectable`
 * simply never passed, since a widget already inside a group can't be
 * selected into a different grouping without first being ungrouped (see
 * PageLayoutWidgetContainer's docblock on that prop). Recursion-safe:
 * groups can't contain groups (enforced server-side and in the "Group
 * selected" picker), so a child rendered here is guaranteed not to itself
 * be a 'group' widget.
 */
export function GroupWidgetContainer({
  children,
  context,
  editable,
  onRemoveGroup,
  onRemoveChild,
  onEditChild,
  onPersistChildren,
  onUngroup,
  onSaveTemplate,
}: {
  /** The group's own position/size is owned entirely by the parent page
   *  grid (PageLayoutEditor) — this component only ever renders what's
   *  inside the box it's given, so it has no need for the group widget
   *  row itself, only its children. */
  children: PageLayoutWidget[];
  context: PageLayoutWidgetContext;
  editable: boolean;
  onRemoveGroup: () => void;
  onRemoveChild: (id: number) => void;
  onEditChild: (widget: PageLayoutWidget) => void;
  onPersistChildren: (changes: ChildLayoutChange[]) => void;
  onUngroup: () => void;
  onSaveTemplate: () => void;
}) {
  function persistChildren(rglLayout: Layout[]) {
    const changes: ChildLayoutChange[] = [];

    for (const item of rglLayout) {
      const widget = children.find((w) => String(w.id) === item.i);
      if (!widget) continue;

      if (widget.position_x !== item.x || widget.position_y !== item.y || widget.width !== item.w || widget.height !== item.h) {
        changes.push({ id: widget.id, position_x: item.x, position_y: item.y, width: item.w, height: item.h });
      }
    }

    if (changes.length > 0) onPersistChildren(changes);
  }

  return (
    <div
      style={{
        border: '1px solid var(--border, #ddd)',
        borderRadius: 8,
        height: '100%',
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
      }}
    >
      {editable && (
        <div
          className="widget-drag-handle"
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
          <span style={{ fontSize: '0.75rem', opacity: 0.7 }}>Group</span>
          <div style={{ display: 'flex', gap: 4 }}>
            <button className="widget-no-drag" onClick={onUngroup}>
              Ungroup
            </button>
            <button className="widget-no-drag" onClick={onSaveTemplate}>
              Save as template
            </button>
            <button aria-label="Remove widget" className="widget-no-drag" onClick={onRemoveGroup}>
              ×
            </button>
          </div>
        </div>
      )}

      <div style={{ flex: 1, overflow: 'hidden' }}>
        {children.length === 0 ? (
          <p style={{ padding: 12, opacity: 0.7, fontSize: '0.85rem' }}>Empty group.</p>
        ) : (
          <ResponsiveGridLayout
            className="layout"
            layout={layoutFor(children)}
            cols={GRID_COLS}
            rowHeight={ROW_HEIGHT}
            isDraggable={editable}
            isResizable={editable}
            draggableHandle=".widget-drag-handle"
            draggableCancel=".widget-no-drag"
            resizeHandles={['se']}
            onDragStop={persistChildren}
            onResizeStop={persistChildren}
          >
            {children.map((child) => (
              <div key={child.id}>
                <PageLayoutWidgetContainer
                  widget={child}
                  context={context}
                  editable={editable}
                  onRemove={() => onRemoveChild(child.id)}
                  onEdit={() => onEditChild(child)}
                />
              </div>
            ))}
          </ResponsiveGridLayout>
        )}
      </div>
    </div>
  );
}
