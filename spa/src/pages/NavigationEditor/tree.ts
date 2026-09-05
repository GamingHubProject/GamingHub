/**
 * The pure tree operations behind the navigation editor.
 *
 * Kept out of the component on purpose: "what does dropping A onto B do"
 * is the part with real rules in it (depth caps, dropping a folder into a
 * folder, dropping something onto itself), and it's far easier to be sure
 * of when it's a function taking a tree and returning a tree than when
 * it's tangled up in drag event handlers.
 */
export interface EditorNode {
  /** Absent until saved — a newly added link has no row yet. */
  id?: number;
  type: 'page' | 'link' | 'folder';
  label: string;
  target_type?: string | null;
  target_id?: number | null;
  url?: string | null;
  icon_asset_id?: number | null;
  icon_url?: string | null;
  is_visible?: boolean;
  children: EditorNode[];
  /** Client-only, so a node without an id is still addressable while editing. */
  key: string;
}

/** Where a drop lands relative to the row under the cursor. */
export type DropPosition = 'before' | 'after' | 'inside';

let counter = 0;
export function nodeKey(): string {
  counter += 1;
  return `n${counter}`;
}

/** Attaches the client-only keys to a tree that came from the API. */
export function withKeys(nodes: Omit<EditorNode, 'key'>[]): EditorNode[] {
  return nodes.map((node) => ({
    ...node,
    key: nodeKey(),
    children: withKeys(node.children ?? []),
  }));
}

export function findNode(nodes: EditorNode[], key: string): EditorNode | null {
  for (const node of nodes) {
    if (node.key === key) return node;
    const found = findNode(node.children, key);
    if (found) return found;
  }

  return null;
}

/** True when `key` is `ancestorKey` or sits somewhere beneath it. */
export function isSelfOrDescendant(nodes: EditorNode[], ancestorKey: string, key: string): boolean {
  const ancestor = findNode(nodes, ancestorKey);
  if (!ancestor) return false;
  if (ancestor.key === key) return true;

  return findNode(ancestor.children, key) !== null;
}

export function removeNode(nodes: EditorNode[], key: string): { tree: EditorNode[]; removed: EditorNode | null } {
  let removed: EditorNode | null = null;

  function walk(list: EditorNode[]): EditorNode[] {
    return list.reduce<EditorNode[]>((acc, node) => {
      if (node.key === key) {
        removed = node;
        return acc;
      }
      acc.push({ ...node, children: walk(node.children) });
      return acc;
    }, []);
  }

  return { tree: walk(nodes), removed };
}

/** Depth of `key` — 1 for a top-level node, 2 for a child. */
export function depthOf(nodes: EditorNode[], key: string, depth = 1): number | null {
  for (const node of nodes) {
    if (node.key === key) return depth;
    const found = depthOf(node.children, key, depth + 1);
    if (found) return found;
  }

  return null;
}

/**
 * Whether a drop is allowed, and why not when it isn't. The message is
 * shown to the admin rather than the drop silently doing nothing — a
 * refusal with no explanation reads as a broken feature.
 */
export function dropError(
  nodes: EditorNode[],
  dragKey: string,
  targetKey: string,
  position: DropPosition
): string | null {
  if (dragKey === targetKey) return null; // a no-op, not an error

  if (isSelfOrDescendant(nodes, dragKey, targetKey)) {
    return 'A folder can\'t be moved inside itself.';
  }

  const dragged = findNode(nodes, dragKey);
  const target = findNode(nodes, targetKey);
  if (!dragged || !target) return null;

  if (position === 'inside') {
    if (target.type !== 'folder') return 'Only a dropdown can hold other items.';
    if (dragged.type === 'folder') return 'A dropdown can\'t go inside another dropdown.';
    if (dragged.children.length > 0) return 'Empty this item before moving it inside a dropdown.';

    return null;
  }

  // Dropping beside a child puts the dragged node at that child's depth.
  const targetDepth = depthOf(nodes, targetKey) ?? 1;
  if (targetDepth > 1 && dragged.type === 'folder') {
    return 'A dropdown can only sit at the top level.';
  }

  return null;
}

/**
 * Move `dragKey` relative to `targetKey`. Returns the tree unchanged when
 * the move isn't allowed, so a caller can render the error without having
 * to guard the call.
 */
export function moveNode(
  nodes: EditorNode[],
  dragKey: string,
  targetKey: string,
  position: DropPosition
): EditorNode[] {
  if (dragKey === targetKey) return nodes;
  if (dropError(nodes, dragKey, targetKey, position)) return nodes;

  const { tree, removed } = removeNode(nodes, dragKey);
  if (!removed) return nodes;

  function insert(list: EditorNode[]): EditorNode[] {
    return list.flatMap((node) => {
      if (node.key === targetKey) {
        if (position === 'inside') {
          return [{ ...node, children: [...node.children, removed!] }];
        }

        return position === 'before' ? [removed!, node] : [node, removed!];
      }

      return [{ ...node, children: insert(node.children) }];
    });
  }

  return insert(tree);
}

/** Replace one node in place, by key. */
export function updateNode(nodes: EditorNode[], key: string, patch: Partial<EditorNode>): EditorNode[] {
  return nodes.map((node) =>
    node.key === key
      ? { ...node, ...patch }
      : { ...node, children: updateNode(node.children, key, patch) }
  );
}

/**
 * Move a node one step within its own parent, or across the depth
 * boundary. The keyboard equivalent of a drag — drag-only trees are
 * unusable without a mouse, and for a one-step move this is faster anyway.
 */
export function nudge(nodes: EditorNode[], key: string, direction: 'up' | 'down' | 'in' | 'out'): EditorNode[] {
  const parentList = findParentList(nodes, key);
  if (!parentList) return nodes;

  const index = parentList.list.findIndex((n) => n.key === key);

  if (direction === 'up' || direction === 'down') {
    const next = direction === 'up' ? index - 1 : index + 1;
    if (next < 0 || next >= parentList.list.length) return nodes;

    const reordered = [...parentList.list];
    [reordered[index], reordered[next]] = [reordered[next], reordered[index]];

    return replaceList(nodes, parentList.parentKey, reordered);
  }

  if (direction === 'in') {
    // Nest into the folder immediately above, which is what "indent"
    // means in every file tree.
    const above = parentList.list[index - 1];
    if (!above || above.type !== 'folder') return nodes;

    return moveNode(nodes, key, above.key, 'inside');
  }

  // Out: sit just after the folder it was in. Already top level means
  // there is nowhere further out to go.
  if (!parentList.parentKey) return nodes;

  return moveNode(nodes, key, parentList.parentKey, 'after');
}

function findParentList(
  nodes: EditorNode[],
  key: string,
  parentKey: string | null = null
): { list: EditorNode[]; parentKey: string | null } | null {
  if (nodes.some((n) => n.key === key)) return { list: nodes, parentKey };

  for (const node of nodes) {
    const found = findParentList(node.children, key, node.key);
    if (found) return found;
  }

  return null;
}

function replaceList(nodes: EditorNode[], parentKey: string | null, list: EditorNode[]): EditorNode[] {
  if (parentKey === null) return list;

  return nodes.map((node) =>
    node.key === parentKey
      ? { ...node, children: list }
      : { ...node, children: replaceList(node.children, parentKey, list) }
  );
}

/** Strips the client-only keys for the API. */
export function toPayload(nodes: EditorNode[]): unknown[] {
  return nodes.map((node) => ({
    id: node.id,
    type: node.type,
    label: node.label,
    target_type: node.target_type ?? null,
    target_id: node.target_id ?? null,
    url: node.url ?? null,
    icon_asset_id: node.icon_asset_id ?? null,
    is_visible: node.is_visible ?? true,
    children: toPayload(node.children),
  }));
}
