import { useState } from 'react';
import { Modal } from './Modal';
import { getServerLayoutWidgetDefinition } from '../widgets/serverLayout/registry';
import type { ServerLayoutWidget } from '../api/types';

/**
 * Only ever mounted when the widget's definition has a configForm (see
 * ServerLayoutWidgetContainer) — unlike the dashboard's WidgetConfigModal,
 * there's no raw-JSON textarea fallback, since every field here is meant
 * to be a real toggle an admin picks from, not blind JSON editing.
 */
export function ServerLayoutWidgetConfigModal({
  widget,
  onClose,
  onSave,
}: {
  widget: ServerLayoutWidget;
  onClose: () => void;
  onSave: (config: Record<string, unknown>) => void;
}) {
  const definition = getServerLayoutWidgetDefinition(widget.widget_type);
  const [config, setConfig] = useState<Record<string, unknown>>(widget.config ?? definition?.defaultConfig ?? {});

  if (!definition?.configForm) return null;

  return (
    <Modal title={`${definition.label} settings`} onClose={onClose}>
      <definition.configForm config={config} onChange={setConfig} />
      <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
        <button type="button" onClick={onClose}>
          Cancel
        </button>
        <button type="button" onClick={() => onSave(config)}>
          Save
        </button>
      </div>
    </Modal>
  );
}
