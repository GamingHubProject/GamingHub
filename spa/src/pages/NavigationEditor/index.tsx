import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useApi } from '../../providers/ApiClientProvider';
import { AssetPicker } from '../../components/AssetPicker';
import type { AssetPreview } from '../../components/AssetPicker';
import { Listbox } from '../../components/Listbox';
import type { Asset } from '../../api/types';
import { dropError, moveNode, nodeKey, nudge, removeNode, toPayload, updateNode, withKeys } from './tree';
import type { DropPosition, EditorNode } from './tree';

interface TargetOption {
  target_type: string;
  target_id: number | null;
  label: string;
  url: string;
}

interface TargetGroup {
  group: string;
  options: TargetOption[];
}

/**
 * The navigation editor: a file tree of the site's links, reordered by
 * dragging or by the arrow buttons on each row.
 *
 * Drag *and* buttons deliberately. A drag-only tree can't be operated
 * without a mouse at all, and for a one-step move the buttons are quicker
 * anyway — so they're the primary control rather than an accessibility
 * afterthought.
 */
export function NavigationEditor() {
  const api = useApi();
  const queryClient = useQueryClient();
  const [nodes, setNodes] = useState<EditorNode[]>([]);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['navigation', 'edit'],
    queryFn: () => api.get<{ tree: Omit<EditorNode, 'key'>[]; targets: TargetGroup[] }>('/api/v1/navigation/edit'),
  });

  useEffect(() => {
    if (data && !dirty) setNodes(withKeys(data.tree));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);

  const save = useMutation({
    mutationFn: () => api.put('/api/v1/navigation/tree', { tree: toPayload(nodes) }),
    onSuccess: () => {
      setDirty(false);
      // Both the editor's own copy and whatever the header/sidebar are
      // rendering from.
      queryClient.invalidateQueries({ queryKey: ['navigation'] });
    },
  });

  function change(next: EditorNode[]) {
    setNodes(next);
    setDirty(true);
    setError(null);
  }

  function add(type: EditorNode['type']) {
    change([
      ...nodes,
      {
        key: nodeKey(),
        type,
        label: type === 'folder' ? 'New dropdown' : 'New link',
        children: [],
        is_visible: true,
        ...(type === 'page' ? { target_type: 'home' } : {}),
      },
    ]);
  }

  function handleDrop(dragKey: string, targetKey: string, position: DropPosition) {
    // Surfaced rather than silently doing nothing — a refusal with no
    // explanation reads as a broken feature.
    const problem = dropError(nodes, dragKey, targetKey, position);
    if (problem) {
      setError(problem);
      return;
    }
    change(moveNode(nodes, dragKey, targetKey, position));
  }

  if (isLoading) return <p>Loading navigation…</p>;

  return (
    <div>
      <h1>Navigation</h1>
      <p style={{ color: 'var(--muted, #666)', maxWidth: '60ch' }}>
        These links appear in the site's top bar, its sidebar, or both — whichever the current theme is set
        to show. Drag a row onto a dropdown to nest it inside.
      </p>

      <div style={{ display: 'flex', gap: 'var(--space-tight, 8px)', margin: 'var(--space-loose, 16px) 0' }}>
        <button type="button" onClick={() => add('page')}>+ Page</button>
        <button type="button" onClick={() => add('link')}>+ External link</button>
        <button type="button" onClick={() => add('folder')}>+ Dropdown</button>
        <span style={{ flex: 1 }} />
        <button type="button" onClick={() => save.mutate()} disabled={!dirty || save.isPending}>
          {save.isPending ? 'Saving…' : 'Save navigation'}
        </button>
      </div>

      {error && <p role="alert" style={{ color: 'var(--accent, crimson)' }}>{error}</p>}
      {save.isError && <p role="alert" style={{ color: 'crimson' }}>Couldn't save. Check the links and try again.</p>}
      {dirty && !save.isPending && <p style={{ color: 'var(--muted, #666)' }}>Unsaved changes.</p>}

      {nodes.length === 0 ? (
        <p style={{ opacity: 0.7 }}>
          No links yet. Until you add some, the site falls back to Home and Games.
        </p>
      ) : (
        <ul style={listStyle}>
          {nodes.map((node) => (
            <Row
              key={node.key}
              node={node}
              depth={1}
              nodes={nodes}
              targets={data?.targets ?? []}
              editing={editing}
              onEdit={setEditing}
              onChange={change}
              onDrop={handleDrop}
            />
          ))}
        </ul>
      )}
    </div>
  );
}

function Row({
  node, depth, nodes, targets, editing, onEdit, onChange, onDrop,
}: {
  node: EditorNode;
  depth: number;
  nodes: EditorNode[];
  targets: TargetGroup[];
  editing: string | null;
  onEdit: (key: string | null) => void;
  onChange: (next: EditorNode[]) => void;
  onDrop: (dragKey: string, targetKey: string, position: DropPosition) => void;
}) {
  const [expanded, setExpanded] = useState(true);
  const [dropHint, setDropHint] = useState<DropPosition | null>(null);
  const isOpen = editing === node.key;

  /**
   * Which third of the row the cursor is over decides the drop: the top
   * and bottom edges reorder, the middle nests. That's the "open a folder"
   * metaphor from a file browser, and it's why the middle band is only
   * offered on a folder.
   */
  function positionFor(event: React.DragEvent<HTMLDivElement>): DropPosition {
    const box = event.currentTarget.getBoundingClientRect();
    const offset = (event.clientY - box.top) / box.height;
    if (node.type === 'folder' && offset > 0.25 && offset < 0.75) return 'inside';

    return offset < 0.5 ? 'before' : 'after';
  }

  return (
    <li>
      <div
        draggable
        onDragStart={(event) => event.dataTransfer.setData('text/plain', node.key)}
        onDragOver={(event) => {
          event.preventDefault();
          setDropHint(positionFor(event));
        }}
        onDragLeave={() => setDropHint(null)}
        onDrop={(event) => {
          event.preventDefault();
          const dragKey = event.dataTransfer.getData('text/plain');
          setDropHint(null);
          if (dragKey && dragKey !== node.key) onDrop(dragKey, node.key, positionFor(event));
        }}
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 'var(--space-normal, 10px)',
          padding: 'var(--space-tight, 6px) var(--space-normal, 10px)',
          marginLeft: (depth - 1) * 24,
          borderRadius: 'calc(var(--radius, 8px) / 1.5)',
          border: '1px solid var(--border, #ddd)',
          background: dropHint === 'inside' ? 'var(--surface-muted, #eee)' : 'var(--surface, transparent)',
          borderTop: dropHint === 'before' ? '2px solid var(--accent, #333)' : undefined,
          borderBottom: dropHint === 'after' ? '2px solid var(--accent, #333)' : undefined,
          cursor: 'grab',
          opacity: node.is_visible === false ? 0.55 : 1,
        }}
      >
        <span aria-hidden="true" style={{ opacity: 0.5 }}>⠿</span>

        {node.type === 'folder' && (
          <button
            type="button"
            aria-label={expanded ? `Collapse ${node.label}` : `Expand ${node.label}`}
            aria-expanded={expanded}
            onClick={() => setExpanded((e) => !e)}
            style={{ border: 'none', background: 'none', padding: 0 }}
          >
            {expanded ? '▾' : '▸'}
          </button>
        )}

        {node.icon_url && <img src={node.icon_url} alt="" style={{ width: 18, height: 18, objectFit: 'contain' }} />}

        <span style={{ flex: 1 }}>
          {node.label}{' '}
          <span style={{ opacity: 0.55, fontSize: '0.85em' }}>
            {node.type === 'folder' ? 'dropdown' : node.type === 'link' ? node.url : node.target_type}
          </span>
        </span>

        {/* The keyboard equivalent of a drag. */}
        <button type="button" aria-label={`Move ${node.label} up`} onClick={() => onChange(nudge(nodes, node.key, 'up'))}>↑</button>
        <button type="button" aria-label={`Move ${node.label} down`} onClick={() => onChange(nudge(nodes, node.key, 'down'))}>↓</button>
        <button type="button" aria-label={`Nest ${node.label}`} onClick={() => onChange(nudge(nodes, node.key, 'in'))}>→</button>
        <button type="button" aria-label={`Move ${node.label} out`} onClick={() => onChange(nudge(nodes, node.key, 'out'))}>←</button>
        <button type="button" onClick={() => onEdit(isOpen ? null : node.key)}>{isOpen ? 'Done' : 'Edit'}</button>
        <button
          type="button"
          aria-label={`Remove ${node.label}`}
          onClick={() => onChange(removeNode(nodes, node.key).tree)}
        >
          ×
        </button>
      </div>

      {isOpen && (
        <NodeForm
          node={node}
          targets={targets}
          onChange={(patch) => onChange(updateNode(nodes, node.key, patch))}
        />
      )}

      {node.children.length > 0 && expanded && (
        <ul style={listStyle}>
          {node.children.map((child) => (
            <Row
              key={child.key}
              node={child}
              depth={depth + 1}
              nodes={nodes}
              targets={targets}
              editing={editing}
              onEdit={onEdit}
              onChange={onChange}
              onDrop={onDrop}
            />
          ))}
        </ul>
      )}
    </li>
  );
}

function NodeForm({
  node, targets, onChange,
}: {
  node: EditorNode;
  targets: TargetGroup[];
  onChange: (patch: Partial<EditorNode>) => void;
}) {
  // Flattened with the group name in the label — the Listbox is a flat
  // list, and "Games — Phantom Galaxies" reads fine without optgroups.
  const options = targets.flatMap((group) =>
    group.options.map((option) => ({
      value: `${option.target_type}:${option.target_id ?? ''}`,
      label: `${group.group} — ${option.label}`,
    }))
  );

  return (
    <div
      style={{
        display: 'flex',
        flexWrap: 'wrap',
        gap: 'var(--space-normal, 12px)',
        alignItems: 'center',
        padding: 'var(--space-normal, 12px)',
        margin: '4px 0 4px 24px',
        border: '1px solid var(--border, #ddd)',
        borderRadius: 'calc(var(--radius, 8px) / 1.5)',
      }}
    >
      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        Label
        <input value={node.label} onChange={(event) => onChange({ label: event.target.value })} />
      </label>

      {node.type === 'page' && (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <span>Goes to</span>
          <Listbox
            label="Destination"
            value={`${node.target_type ?? ''}:${node.target_id ?? ''}`}
            options={options}
            onChange={(value) => {
              const [type, id] = value.split(':');
              onChange({ target_type: type, target_id: id ? Number(id) : null });
            }}
          />
        </div>
      )}

      {node.type === 'link' && (
        <label style={{ display: 'flex', alignItems: 'center', gap: 8, flex: 1 }}>
          URL
          <input
            value={node.url ?? ''}
            placeholder="https://example.com"
            onChange={(event) => onChange({ url: event.target.value })}
            style={{ flex: 1, minWidth: 220 }}
          />
        </label>
      )}

      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <input
          type="checkbox"
          checked={node.is_visible !== false}
          onChange={(event) => onChange({ is_visible: event.target.checked })}
        />
        Visible
      </label>

      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <span>Icon</span>
        <AssetPicker
          value={node.icon_url ? ({ thumbnail_url: node.icon_url, alt_text: null } as AssetPreview) : null}
          onChange={(asset: Asset | null) =>
            onChange({ icon_asset_id: asset?.id ?? null, icon_url: asset?.url ?? null })
          }
        />
      </div>
    </div>
  );
}

const listStyle = { listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 4 } as const;
