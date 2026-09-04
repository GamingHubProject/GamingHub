import { getWidgetDefinition } from '../widgets/registry';
import type { DashboardWidget } from '../api/types';

export function DashboardWidgetContainer({
  widget,
  onEdit,
}: {
  widget: DashboardWidget;
  onEdit: () => void;
}) {
  const definition = getWidgetDefinition(widget.widget_type);

  return (
    <div
      style={{
        border: '1px solid var(--border, #ddd)',
        borderRadius: 'var(--radius, 8px)',
        height: '100%',
        display: 'flex',
        flexDirection: 'column',
      }}
    >
      <div
        className="widget-drag-handle"
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: '6px 10px',
          borderBottom: '1px solid var(--border, #ddd)',
          cursor: 'move',
        }}
      >
        <span style={{ fontSize: '0.8rem', opacity: 0.7 }}>{definition?.label ?? widget.widget_type}</span>
        {/* widget-no-drag: excluded from the grid's draggableCancel selector
            (see Dashboard.tsx) — without it, this button sits inside
            .widget-drag-handle and react-grid-layout's own mousedown
            listener on the handle wins the race against onClick, starting
            a drag instead of opening the config modal. */}
        <button aria-label="Widget settings" className="widget-no-drag" onClick={onEdit}>
          ⚙
        </button>
      </div>
      <div style={{ padding: 10, flex: 1, overflow: 'auto' }}>
        {definition ? (
          <definition.component widgetId={widget.id} config={widget.config ?? definition.defaultConfig} />
        ) : (
          <p>Unsupported widget type: {widget.widget_type}</p>
        )}
      </div>
    </div>
  );
}
