import { useState } from 'react';
import { Modal } from './Modal';
import { WidgetStyleSection } from './WidgetStyleSection';
import { getPageLayoutWidgetDefinition } from '../widgets/pageLayout/registry';
import type { PageLayoutWidget } from '../api/types';

/**
 * The type-specific configForm is optional (several widget types have no
 * fields of their own — e.g. server-allocations) but WidgetStyleSection
 * below is universal, so this modal is now always openable regardless of
 * whether a configForm exists (see PageLayoutWidgetContainer's settings
 * gear, which used to be conditional on configForm and no longer is).
 * Still no raw-JSON textarea fallback, unlike the dashboard's
 * WidgetConfigModal — every field here is meant to be a real toggle an
 * admin picks from, not blind JSON editing.
 */
export function PageLayoutWidgetConfigModal({
  widget,
  onClose,
  onSave,
}: {
  widget: PageLayoutWidget;
  onClose: () => void;
  onSave: (config: Record<string, unknown>) => void;
}) {
  const definition = getPageLayoutWidgetDefinition(widget.widget_type);
  const [config, setConfig] = useState<Record<string, unknown>>(widget.config ?? definition?.defaultConfig ?? {});

  return (
    <Modal title={`${definition?.label ?? widget.widget_type} settings`} onClose={onClose}>
      {definition?.configForm && <definition.configForm config={config} onChange={setConfig} />}
      <WidgetStyleSection widgetType={widget.widget_type} config={config} onChange={setConfig} />
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
