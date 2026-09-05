import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { isActive, isExternal, useNavigation } from './useNavigation';
import type { NavNode } from './useNavigation';

export type SidebarBehavior = 'always' | 'toggle' | 'auto-hide';

/** Below this the sidebar always behaves as `toggle`, whatever the theme
 *  says — "always visible" on a phone leaves a 200px-wide page. */
const NARROW = 900;

const COLLAPSED_WIDTH = 64;
const EXPANDED_WIDTH = 240;

export function Sidebar({ behavior, open, onOpenChange }: {
  behavior: SidebarBehavior;
  /** Controlled by Layout, which also owns the header's menu button. */
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { nodes } = useNavigation();
  const { pathname } = useLocation();
  const [narrow, setNarrow] = useState(() => window.innerWidth < NARROW);
  const [hovered, setHovered] = useState(false);

  useEffect(() => {
    const onResize = () => setNarrow(window.innerWidth < NARROW);
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  // A sidebar that stays open over the content after a tap is a sidebar
  // covering the thing you just navigated to.
  useEffect(() => {
    if (narrow) onOpenChange(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pathname, narrow]);

  const effective: SidebarBehavior = narrow ? 'toggle' : behavior;
  const expanded = effective === 'always' || (effective === 'auto-hide' ? hovered : open);
  // auto-hide keeps a rail of icons on screen; toggle takes the whole
  // column away, so the main content can use the space.
  const visible = effective !== 'toggle' || open;

  if (nodes.length === 0) return null;

  return (
    <>
      {/* Only on a narrow screen, where the sidebar overlays rather than
          sits beside the content and needs a way out. */}
      {narrow && open && (
        <div
          onClick={() => onOpenChange(false)}
          style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 40 }}
        />
      )}

      <nav
        aria-label="Site"
        data-testid="sidebar"
        onMouseEnter={() => setHovered(true)}
        onMouseLeave={() => setHovered(false)}
        style={{
          width: visible ? (expanded ? EXPANDED_WIDTH : COLLAPSED_WIDTH) : 0,
          flexShrink: 0,
          overflowX: 'hidden',
          overflowY: 'auto',
          background: 'var(--surface, transparent)',
          borderRight: visible ? '1px solid var(--border, #ddd)' : 'none',
          padding: visible ? 'var(--space-normal, 12px) var(--space-tight, 6px)' : 0,
          transition: 'width 150ms ease',
          // On a narrow screen it floats over the content instead of
          // squeezing it into nothing.
          ...(narrow
            ? { position: 'fixed', top: 0, bottom: 0, left: 0, zIndex: 50 }
            : { position: 'sticky', top: 0, alignSelf: 'flex-start', maxHeight: '100vh' }),
        }}
      >
        <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 2 }}>
          {nodes.map((node) => (
            <SidebarNode key={node.id} node={node} pathname={pathname} expanded={expanded} />
          ))}
        </ul>
      </nav>
    </>
  );
}

function SidebarNode({ node, pathname, expanded }: { node: NavNode; pathname: string; expanded: boolean }) {
  // A folder holding the current page starts open, so a visitor never has
  // to hunt for where they already are.
  const holdsCurrent = node.children.some((child) => isActive(child.url, pathname));
  const [open, setOpen] = useState(holdsCurrent);

  useEffect(() => {
    if (holdsCurrent) setOpen(true);
  }, [holdsCurrent]);

  if (node.type === 'folder') {
    return (
      <li>
        <button
          type="button"
          aria-expanded={open}
          onClick={() => setOpen((o) => !o)}
          style={{ ...rowStyle(false), width: '100%', background: 'none', border: 'none' }}
        >
          <NodeIcon node={node} />
          {expanded && (
            <>
              <span style={labelStyle}>{node.label}</span>
              <span aria-hidden="true" style={{ opacity: 0.6, fontSize: '0.7em' }}>{open ? '▾' : '▸'}</span>
            </>
          )}
        </button>
        {open && expanded && (
          <ul style={{ listStyle: 'none', margin: 0, padding: '2px 0 2px 20px', display: 'flex', flexDirection: 'column', gap: 2 }}>
            {node.children.map((child) => (
              <SidebarNode key={child.id} node={child} pathname={pathname} expanded={expanded} />
            ))}
          </ul>
        )}
      </li>
    );
  }

  return (
    <li>
      <NavLeaf node={node} pathname={pathname} showLabel={expanded} />
    </li>
  );
}

export function NavLeaf({ node, pathname, showLabel = true }: { node: NavNode; pathname: string; showLabel?: boolean }) {
  const active = isActive(node.url, pathname);
  const style = { ...rowStyle(active), textDecoration: 'none' };
  const content = (
    <>
      <NodeIcon node={node} />
      {showLabel && <span style={labelStyle}>{node.label}</span>}
    </>
  );

  if (!node.url) return <span style={style}>{content}</span>;

  // An off-site URL isn't a react-router route; handing it to <Link> would
  // try to resolve it against the app's own routes.
  return isExternal(node.url) ? (
    <a href={node.url} style={style} rel="noreferrer noopener" target="_blank" title={showLabel ? undefined : node.label}>
      {content}
    </a>
  ) : (
    <Link to={node.url} style={style} aria-current={active ? 'page' : undefined} title={showLabel ? undefined : node.label}>
      {content}
    </Link>
  );
}

function NodeIcon({ node }: { node: NavNode }) {
  if (!node.icon_url) return null;

  return (
    <img
      src={node.icon_url}
      alt=""
      style={{ width: 20, height: 20, objectFit: 'contain', flexShrink: 0, borderRadius: 'calc(var(--radius, 8px) / 3)' }}
    />
  );
}

function rowStyle(active: boolean) {
  return {
    display: 'flex',
    alignItems: 'center',
    gap: 'var(--space-normal, 12px)',
    padding: 'var(--space-tight, 6px) var(--space-normal, 12px)',
    borderRadius: 'calc(var(--radius, 8px) / 1.5)',
    color: active ? 'var(--accent, inherit)' : 'inherit',
    background: active ? 'var(--surface-muted, rgba(0,0,0,0.05))' : 'transparent',
    font: 'inherit',
    fontWeight: active ? 600 : 400,
    cursor: 'pointer',
    textAlign: 'left' as const,
    whiteSpace: 'nowrap' as const,
  };
}

const labelStyle = { flex: 1, overflow: 'hidden', textOverflow: 'ellipsis' } as const;
