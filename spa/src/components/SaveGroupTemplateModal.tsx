import { useState } from 'react';
import { Modal } from './Modal';

/**
 * Just a name prompt — the actual snapshot capture happens server-side
 * (GroupWidgetTemplateController::store reads the group's *current*
 * children directly), so this modal has nothing else to collect.
 */
export function SaveGroupTemplateModal({ onClose, onSave }: { onClose: () => void; onSave: (name: string) => void }) {
  const [name, setName] = useState('');

  return (
    <Modal title="Save as template" onClose={onClose}>
      <label>
        Template name
        <div style={{ marginTop: 4 }}>
          <input
            type="text"
            value={name}
            onChange={(event) => setName(event.target.value)}
            autoFocus
            style={{ width: '100%', boxSizing: 'border-box' }}
          />
        </div>
      </label>

      <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
        <button type="button" onClick={onClose}>
          Cancel
        </button>
        <button type="button" disabled={!name.trim()} onClick={() => onSave(name.trim())}>
          Save
        </button>
      </div>
    </Modal>
  );
}
