import { getPageLayoutWidgetDefinition } from '../widgets/pageLayout/registry';
import type { PageLayoutWidgetContext } from '../widgets/pageLayout/registry';
import type { PageLayoutWidget } from '../api/types';

/**
 * Always a bordered "card" (per the design brief), but the drag-handle
 * header bar with its label + remove/settings buttons only renders in
 * edit mode — a normal visitor to the page should see clean cards, not
 * admin-tool chrome. The grid itself also disables dragging/resizing
 * outright when not editable (see PageLayoutEditor's isDraggable/
 * isResizable), so this isn't just cosmetic. The settings gear only
 * appears for a widget type that actually has a configForm — most widget
 * types have nothing to configure.
 */
export function PageLayoutWidgetContainer({
  widget,
  context,
  editable,
  layered = false,
  onRemove,
  onEdit,
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
}) {
  const definition = getPageLayoutWidgetDefinition(widget.widget_type);
  const config = widget.config ?? definition?.defaultConfig ?? {};
  const chromeless = layered || (definition?.chromeless ?? false);

  return (
    <div
      style={
        chromeless
          ? { height: '100%', display: 'flex', flexDirection: 'column' }
          : {
              border: '1px solid var(--border, #ddd)',
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
          <span style={{ fontSize: '0.75rem', opacity: 0.7 }}>{definition?.label ?? widget.widget_type}</span>
          {/* widget-no-drag: same draggableCancel mechanism as the
              dashboard grid — without it these buttons start a drag
              instead of firing onClick. */}
          <div style={{ display: 'flex', gap: 4 }}>
            {definition?.configForm && (
              <button aria-label="Widget settings" className="widget-no-drag" onClick={onEdit}>
                ⚙
              </button>
            )}
            <button aria-label="Remove widget" className="widget-no-drag" onClick={onRemove}>
              ×
            </button>
          </div>
        </div>
      )}
      <div style={{ flex: 1, overflow: chromeless ? 'visible' : 'auto' }}>
        {definition ? (
          <definition.component context={context} config={config} layered={layered} />
        ) : (
          <p style={{ padding: 12 }}>Unsupported widget type: {widget.widget_type}</p>
        )}
      </div>
    </div>
  );
}
