import { useState } from 'react';
import { Modal } from './Modal';
import { listWidgetDefinitions } from '../widgets/registry';

export function AddWidgetModal({
  onClose,
  onAdd,
}: {
  onClose: () => void;
  onAdd: (type: string) => void;
}) {
  const definitions = listWidgetDefinitions();
  const [selected, setSelected] = useState<string>(definitions[0]?.type ?? '');

  return (
    <Modal title="Add widget" onClose={onClose}>
      {definitions.length === 0 ? (
        <p>No widget types are registered.</p>
      ) : (
        <form
          onSubmit={(event) => {
            event.preventDefault();
            if (selected) onAdd(selected);
          }}
        >
          <label>
            Widget type
            <select value={selected} onChange={(event) => setSelected(event.target.value)}>
              {definitions.map((definition) => (
                <option key={definition.type} value={definition.type}>
                  {definition.label}
                </option>
              ))}
            </select>
          </label>
          <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
            <button type="button" onClick={onClose}>
              Cancel
            </button>
            <button type="submit">Add widget</button>
          </div>
        </form>
      )}
    </Modal>
  );
}
