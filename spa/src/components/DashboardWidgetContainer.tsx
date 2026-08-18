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
        borderRadius: 8,
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
        <button aria-label="Widget settings" onClick={onEdit}>
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
