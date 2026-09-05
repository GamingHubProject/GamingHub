import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { isActive, useNavigation } from './useNavigation';
import type { NavNode } from './useNavigation';
import { NavFolderRow, NavLeaf } from './NavRow';
import { SiteBranding } from './SiteBranding';
import { regionAccent, regionCss } from './regionStyle';
import type { RegionStyle } from './regionStyle';

export type SidebarBehavior = 'always' | 'toggle' | 'auto-hide';
export type SidebarWidth = 'compact' | 'standard' | 'wide';

/** Below this the sidebar always behaves as `toggle`, whatever the theme
 *  says — "always visible" on a phone leaves a 200px-wide page. */
const NARROW = 900;

/** Named widths an admin can judge, rather than a raw pixel field. */
const WIDTHS: Record<SidebarWidth, number> = { compact: 200, standard: 240, wide: 300 };

/** The auto-hide rail. Sized by the icon, so it isn't a preference. */
const COLLAPSED_WIDTH = 64;

export function Sidebar({
  behavior,
  width = 'standard',
  region,
  open,
  onOpenChange,
}: {
  behavior: SidebarBehavior;
  width?: SidebarWidth;
  /** Styled independently of the header — see layout/regionStyle. */
  region?: RegionStyle;
  /** Controlled by Layout, which also owns the header's menu button. */
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { nodes } = useNavigation('sidebar');
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
  const accent = regionAccent(region);

  // Unlike before, an empty navigation no longer hides the whole sidebar —
  // the branding block is reason enough for it to exist.
  const showBranding = region?.show_branding !== false;
  if (nodes.length === 0 && !showBranding) return null;

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
          ...regionCss(region, 'right'),
          width: visible ? (expanded ? WIDTHS[width] ?? WIDTHS.standard : COLLAPSED_WIDTH) : 0,
          flexShrink: 0,
          overflowX: 'hidden',
          overflowY: 'auto',
          padding: visible ? 'var(--space-normal, 12px) var(--space-tight, 6px)' : 0,
          transition: 'width 150ms ease',
          display: 'flex',
          flexDirection: 'column',
          gap: 'var(--space-normal, 12px)',
          // On a narrow screen it floats over the content instead of
          // squeezing it into nothing.
          ...(narrow
            ? { position: 'fixed', top: 0, bottom: 0, left: 0, zIndex: 50 }
            : { position: 'sticky', top: 0, alignSelf: 'flex-start', maxHeight: '100vh' }),
        }}
      >
        {showBranding && visible && (
          <>
            {/* Space is not the constraint here, so the sidebar always
                shows the tagline when its branding block is on. */}
            <SiteBranding showTagline compact={!expanded} />
            {nodes.length > 0 && (
              <hr style={{ border: 0, borderTop: '1px solid var(--border, #ddd)', opacity: 0.5, margin: 0, width: '100%' }} />
            )}
          </>
        )}

        <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 2 }}>
          {nodes.map((node) => (
            <SidebarNode key={node.id} node={node} pathname={pathname} expanded={expanded} accent={accent} />
          ))}
        </ul>
      </nav>
    </>
  );
}

function SidebarNode({
  node, pathname, expanded, accent,
}: {
  node: NavNode;
  pathname: string;
  expanded: boolean;
  accent: string;
}) {
  // A folder holding the current page starts open, so a visitor never has
  // to hunt for where they already are — and now highlights too, which the
  // old row couldn't express.
  const holdsCurrent = node.children.some((child) => isActive(child.url, pathname));
  const [open, setOpen] = useState(holdsCurrent);

  useEffect(() => {
    if (holdsCurrent) setOpen(true);
  }, [holdsCurrent]);

  if (node.type === 'folder') {
    return (
      <li>
        <NavFolderRow
          node={node}
          open={open}
          active={holdsCurrent}
          accent={accent}
          showLabel={expanded}
          onToggle={() => setOpen((o) => !o)}
        />
        {open && expanded && (
          <ul style={{ listStyle: 'none', margin: 0, padding: '2px 0 2px 20px', display: 'flex', flexDirection: 'column', gap: 2 }}>
            {node.children.map((child) => (
              <SidebarNode key={child.id} node={child} pathname={pathname} expanded={expanded} accent={accent} />
            ))}
          </ul>
        )}
      </li>
    );
  }

  return (
    <li>
      <NavLeaf node={node} pathname={pathname} accent={accent} showLabel={expanded} />
    </li>
  );
}
