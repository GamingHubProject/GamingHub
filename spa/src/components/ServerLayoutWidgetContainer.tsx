import { getServerLayoutWidgetDefinition } from '../widgets/serverLayout/registry';
import type { Server, ServerLayoutWidget } from '../api/types';

/**
 * Always a bordered "card" (per the design brief), but the drag-handle
 * header bar with its label + remove button only renders in edit mode —
 * a normal player visiting the server page should see clean cards, not
 * admin-tool chrome. The grid itself also disables dragging/resizing
 * outright when not editable (see ServerDetail.tsx's isDraggable/
 * isResizable), so this isn't just cosmetic.
 */
export function ServerLayoutWidgetContainer({
  widget,
  server,
  editable,
  onRemove,
}: {
  widget: ServerLayoutWidget;
  server: Server;
  editable: boolean;
  onRemove: () => void;
}) {
  const definition = getServerLayoutWidgetDefinition(widget.widget_type);

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
          <span style={{ fontSize: '0.75rem', opacity: 0.7 }}>{definition?.label ?? widget.widget_type}</span>
          {/* widget-no-drag: same draggableCancel mechanism as the
              dashboard grid — without it this button starts a drag
              instead of firing onClick. */}
          <button aria-label="Remove widget" className="widget-no-drag" onClick={onRemove}>
            ×
          </button>
        </div>
      )}
      <div style={{ flex: 1, overflow: 'auto' }}>
        {definition ? (
          <definition.component server={server} />
        ) : (
          <p style={{ padding: 12 }}>Unsupported widget type: {widget.widget_type}</p>
        )}
      </div>
    </div>
  );
}
