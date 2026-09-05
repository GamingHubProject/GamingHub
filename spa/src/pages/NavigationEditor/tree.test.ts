import { describe, expect, it } from 'vitest';
import { depthOf, dropError, findNode, moveNode, nudge, removeNode, toPayload, updateNode, withKeys } from './tree';
import type { EditorNode } from './tree';

function tree(): EditorNode[] {
  return withKeys([
    { type: 'page', label: 'Home', target_type: 'home', children: [] },
    {
      type: 'folder',
      label: 'Community',
      children: [
        { type: 'link', label: 'Discord', url: 'https://discord.gg/x', children: [] },
        { type: 'link', label: 'Forum', url: 'https://forum.test', children: [] },
      ],
    },
    { type: 'page', label: 'Games', target_type: 'games', children: [] },
    { type: 'folder', label: 'Guides', children: [] },
  ] as Omit<EditorNode, 'key'>[]);
}

const keyOf = (nodes: EditorNode[], label: string): string => {
  const find = (list: EditorNode[]): EditorNode | null => {
    for (const node of list) {
      if (node.label === label) return node;
      const found = find(node.children);
      if (found) return found;
    }
    return null;
  };

  return find(nodes)!.key;
};

const labels = (nodes: EditorNode[]) => nodes.map((n) => n.label);

describe('tree structure', () => {
  it('gives every node a unique key, children included', () => {
    const nodes = tree();
    const all: string[] = [];
    const walk = (list: EditorNode[]) => list.forEach((n) => { all.push(n.key); walk(n.children); });
    walk(nodes);

    expect(new Set(all).size).toBe(all.length);
  });

  it('reports depth so the editor can tell a top-level node from a nested one', () => {
    const nodes = tree();

    expect(depthOf(nodes, keyOf(nodes, 'Home'))).toBe(1);
    expect(depthOf(nodes, keyOf(nodes, 'Discord'))).toBe(2);
  });

  it('removes a node and hands it back, so a move can re-insert it', () => {
    const nodes = tree();
    const { tree: after, removed } = removeNode(nodes, keyOf(nodes, 'Discord'));

    expect(removed?.label).toBe('Discord');
    expect(findNode(after, keyOf(nodes, 'Discord'))).toBeNull();
    expect(after[1].children).toHaveLength(1);
  });
});

describe('drop rules', () => {
  it('refuses to drop a folder inside itself', () => {
    const nodes = tree();
    const folder = keyOf(nodes, 'Community');

    expect(dropError(nodes, folder, keyOf(nodes, 'Discord'), 'after')).toMatch(/inside itself/);
  });

  it('refuses to drop anything inside a non-folder', () => {
    const nodes = tree();

    expect(dropError(nodes, keyOf(nodes, 'Games'), keyOf(nodes, 'Home'), 'inside')).toMatch(/Only a dropdown/);
  });

  it('refuses to nest a folder inside another folder — depth is capped at two', () => {
    const nodes = tree();

    expect(dropError(nodes, keyOf(nodes, 'Guides'), keyOf(nodes, 'Community'), 'inside'))
      .toMatch(/inside another dropdown/);
  });

  it('refuses to place a folder beside a nested item, which would put it at depth two', () => {
    const nodes = tree();

    expect(dropError(nodes, keyOf(nodes, 'Guides'), keyOf(nodes, 'Discord'), 'before'))
      .toMatch(/only sit at the top level/);
  });

  it('allows an ordinary link into a folder', () => {
    const nodes = tree();

    expect(dropError(nodes, keyOf(nodes, 'Games'), keyOf(nodes, 'Community'), 'inside')).toBeNull();
  });

  it('treats dropping a node on itself as a no-op rather than an error', () => {
    const nodes = tree();
    const home = keyOf(nodes, 'Home');

    expect(dropError(nodes, home, home, 'before')).toBeNull();
    expect(moveNode(nodes, home, home, 'before')).toBe(nodes);
  });
});

describe('moveNode', () => {
  it('reorders within a level', () => {
    const nodes = tree();
    const moved = moveNode(nodes, keyOf(nodes, 'Games'), keyOf(nodes, 'Home'), 'before');

    expect(labels(moved)).toEqual(['Games', 'Home', 'Community', 'Guides']);
  });

  it('nests a link into a folder', () => {
    const nodes = tree();
    const moved = moveNode(nodes, keyOf(nodes, 'Games'), keyOf(nodes, 'Community'), 'inside');

    expect(labels(moved)).toEqual(['Home', 'Community', 'Guides']);
    expect(labels(moved[1].children)).toEqual(['Discord', 'Forum', 'Games']);
  });

  it('lifts a nested link back out to the top level', () => {
    const nodes = tree();
    const moved = moveNode(nodes, keyOf(nodes, 'Discord'), keyOf(nodes, 'Home'), 'after');

    expect(labels(moved)).toEqual(['Home', 'Discord', 'Community', 'Games', 'Guides']);
    expect(labels(moved[2].children)).toEqual(['Forum']);
  });

  it('returns the same tree untouched when the move is not allowed', () => {
    const nodes = tree();

    expect(moveNode(nodes, keyOf(nodes, 'Community'), keyOf(nodes, 'Discord'), 'before')).toBe(nodes);
  });
});

describe('keyboard nudges', () => {
  it('moves a node up and down within its level', () => {
    const nodes = tree();
    const down = nudge(nodes, keyOf(nodes, 'Home'), 'down');

    expect(labels(down)).toEqual(['Community', 'Home', 'Games', 'Guides']);
    expect(labels(nudge(down, keyOf(nodes, 'Home'), 'up'))).toEqual(['Home', 'Community', 'Games', 'Guides']);
  });

  it('does nothing at the ends of a level', () => {
    const nodes = tree();

    expect(nudge(nodes, keyOf(nodes, 'Home'), 'up')).toBe(nodes);
    expect(nudge(nodes, keyOf(nodes, 'Guides'), 'down')).toBe(nodes);
  });

  it('indents into the folder immediately above, like every file tree', () => {
    const nodes = tree();
    const indented = nudge(nodes, keyOf(nodes, 'Games'), 'in');

    expect(labels(indented)).toEqual(['Home', 'Community', 'Guides']);
    expect(labels(indented[1].children)).toEqual(['Discord', 'Forum', 'Games']);
  });

  it('refuses to indent when the node above is not a folder', () => {
    const nodes = tree();

    expect(nudge(nodes, keyOf(nodes, 'Community'), 'in')).toBe(nodes);
  });

  it('outdents a nested node to just after its folder', () => {
    const nodes = tree();
    const out = nudge(nodes, keyOf(nodes, 'Discord'), 'out');

    expect(labels(out)).toEqual(['Home', 'Community', 'Discord', 'Games', 'Guides']);
  });

  it('does nothing outdenting something already at the top level', () => {
    const nodes = tree();

    expect(nudge(nodes, keyOf(nodes, 'Home'), 'out')).toBe(nodes);
  });
});

describe('updateNode', () => {
  it('edits a nested node without disturbing its siblings', () => {
    const nodes = tree();
    const updated = updateNode(nodes, keyOf(nodes, 'Forum'), { label: 'Forums' });

    expect(labels(updated[1].children)).toEqual(['Discord', 'Forums']);
  });
});

describe('toPayload', () => {
  it('drops the client-only keys and keeps the nesting', () => {
    const payload = toPayload(tree()) as any[];

    expect(payload[0]).not.toHaveProperty('key');
    expect(payload[1].children).toHaveLength(2);
    expect(payload[1].children[0].label).toBe('Discord');
  });

  it('sends no id for a node that has never been saved', () => {
    const payload = toPayload(tree()) as any[];

    expect(payload[0].id).toBeUndefined();
  });
});
