import { useState } from 'react';
import { Modal } from './Modal';
import { listServerLayoutWidgetDefinitions } from '../widgets/serverLayout/registry';

export function AddServerLayoutWidgetModal({
  onClose,
  onAdd,
}: {
  onClose: () => void;
  onAdd: (type: string) => void;
}) {
  const definitions = listServerLayoutWidgetDefinitions();
  const [selected, setSelected] = useState<string>(definitions[0]?.type ?? '');

  return (
    <Modal title="Add card" onClose={onClose}>
      {definitions.length === 0 ? (
        <p>No card types are registered.</p>
      ) : (
        <form
          onSubmit={(event) => {
            event.preventDefault();
            if (selected) onAdd(selected);
          }}
        >
          <label>
            Card type
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
            <button type="submit">Add card</button>
          </div>
        </form>
      )}
    </Modal>
  );
}
