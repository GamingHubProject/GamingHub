import { useMemo, useState } from 'react';
import { Modal } from './Modal';
import { listPageLayoutWidgetDefinitions } from '../widgets/pageLayout/registry';
import type { PageLayoutSubjectType } from '../widgets/pageLayout/registry';

const CATEGORY_ORDER = ['Server', 'Game', 'General'] as const;

/**
 * Search + category-grouped picker, same pattern as AssetPicker's browse
 * modal — a flat <select> dropdown doesn't scale as more widget types are
 * added (Maps, Shop, Calendar, ...), and a page only ever offers widgets
 * valid for its own subjectType (see registry.ts's validFor docblock), so
 * showing every widget type registered app-wide regardless of page would
 * also be actively wrong, not just cluttered.
 */
export function AddPageLayoutWidgetModal({
  subjectType,
  onClose,
  onAdd,
}: {
  subjectType: PageLayoutSubjectType;
  onClose: () => void;
  onAdd: (type: string) => void;
}) {
  const [search, setSearch] = useState('');

  const validDefinitions = useMemo(
    () => listPageLayoutWidgetDefinitions().filter((definition) => definition.validFor.includes(subjectType)),
    [subjectType]
  );

  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) return validDefinitions;
    return validDefinitions.filter((definition) => definition.label.toLowerCase().includes(query));
  }, [validDefinitions, search]);

  const byCategory = useMemo(() => {
    const groups = new Map<string, typeof filtered>();
    for (const definition of filtered) {
      const existing = groups.get(definition.category) ?? [];
      existing.push(definition);
      groups.set(definition.category, existing);
    }
    return groups;
  }, [filtered]);

  return (
    <Modal title="Add widget" onClose={onClose}>
      {validDefinitions.length === 0 ? (
        <p>No widget types are available on this page yet.</p>
      ) : (
        <>
          <input
            type="text"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search widgets…"
            autoFocus
            style={{ width: '100%', marginBottom: 16, boxSizing: 'border-box' }}
          />

          {filtered.length === 0 && <p>No widgets match "{search}".</p>}

          <div style={{ maxHeight: 360, overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: 16 }}>
            {CATEGORY_ORDER.filter((category) => byCategory.has(category)).map((category) => (
              <div key={category}>
                <h3 style={{ fontSize: '0.8rem', textTransform: 'uppercase', opacity: 0.6, margin: '0 0 8px' }}>{category}</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(140px, 1fr))', gap: 8 }}>
                  {byCategory.get(category)!.map((definition) => (
                    <button
                      key={definition.type}
                      type="button"
                      onClick={() => onAdd(definition.type)}
                      style={{
                        textAlign: 'left',
                        padding: '10px 12px',
                        border: '1px solid var(--border, #ddd)',
                        borderRadius: 'var(--radius, 6px)',
                        cursor: 'pointer',
                        background: 'none',
                        font: 'inherit',
                        color: 'inherit',
                      }}
                    >
                      {definition.label}
                    </button>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </>
      )}

      <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end' }}>
        <button type="button" onClick={onClose}>
          Cancel
        </button>
      </div>
    </Modal>
  );
}
