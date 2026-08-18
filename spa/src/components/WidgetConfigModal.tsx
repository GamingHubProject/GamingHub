import { useState } from 'react';
import { Modal } from './Modal';
import { getWidgetDefinition } from '../widgets/registry';
import type { DashboardWidget } from '../api/types';

export function WidgetConfigModal({
  widget,
  onClose,
  onSave,
}: {
  widget: DashboardWidget;
  onClose: () => void;
  onSave: (config: Record<string, unknown>) => void;
}) {
  const definition = getWidgetDefinition(widget.widget_type);
  const [config, setConfig] = useState<Record<string, unknown>>(widget.config ?? definition?.defaultConfig ?? {});
  const [rawJsonError, setRawJsonError] = useState<string | null>(null);
  const [rawJson, setRawJson] = useState(() => JSON.stringify(config, null, 2));

  return (
    <Modal title="Widget settings" onClose={onClose}>
      {definition?.configForm ? (
        <>
          <definition.configForm config={config} onChange={setConfig} />
          <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
            <button type="button" onClick={onClose}>
              Cancel
            </button>
            <button type="button" onClick={() => onSave(config)}>
              Save
            </button>
          </div>
        </>
      ) : (
        <>
          <p>No form is registered for "{widget.widget_type}" — edit its raw config below.</p>
          <textarea
            rows={8}
            style={{ width: '100%', fontFamily: 'monospace' }}
            value={rawJson}
            onChange={(event) => setRawJson(event.target.value)}
          />
          {rawJsonError && <p style={{ color: 'crimson' }}>{rawJsonError}</p>}
          <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
            <button type="button" onClick={onClose}>
              Cancel
            </button>
            <button
              type="button"
              onClick={() => {
                try {
                  const parsed = JSON.parse(rawJson);
                  setRawJsonError(null);
                  onSave(parsed);
                } catch {
                  setRawJsonError('Invalid JSON.');
                }
              }}
            >
              Save
            </button>
          </div>
        </>
      )}
    </Modal>
  );
}
