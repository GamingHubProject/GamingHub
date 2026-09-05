import { useEffect, useState, type CSSProperties } from 'react';
import { useLocation } from 'react-router-dom';
import { isActive, useNavigation } from './useNavigation';
import type { NavNode } from './useNavigation';
import { NavFolderRow, NavLeaf } from './NavRow';
import { SiteBranding } from './SiteBranding';
import { regionAccent, regionCss } from './regionStyle';
import type { RegionStyle } from './regionStyle';

export type SidebarBehavior = 'always' | 'toggle' | 'auto-hide';
export type SidebarWidth = 'compact' | 'standard' | 'wide';
export type SidebarHeight = 'auto' | 'full' | 'fixed';
export type NavAlign = 'top' | 'center' | 'bottom';

/**
 * The sidebar's own settings, on top of the styling every region shares.
 * Named here rather than inline in ThemeProvider so the component that
 * consumes them owns their shape.
 */
export interface SidebarRegion extends RegionStyle {
  width?: SidebarWidth;
  behavior?: SidebarBehavior;
  radius?: number | null;
  /** Above zero turns the sidebar from an edge-flush panel into a card. */
  margin?: number;
  height?: SidebarHeight;
  height_px?: number | null;
  nav_align?: NavAlign;
}

/** Where the links sit when the sidebar is taller than they are. */
const NAV_ALIGN_MARGIN: Record<NavAlign, CSSProperties> = {
  top: {},
  center: { marginTop: 'auto', marginBottom: 'auto' },
  bottom: { marginTop: 'auto' },
};

/** Below this the sidebar always behaves as `toggle`, whatever the theme
 *  says — "always visible" on a phone leaves a 200px-wide page. */
const NARROW = 900;

/**
 * Shared so Layout and Sidebar can't disagree about what "narrow" means.
 * Layout needs it to decide whether a toggle button is worth showing at
 * all, which is the same question the sidebar answers when it forces
 * `toggle` behaviour on a small screen.
 */
export function useIsNarrowViewport(): boolean {
  const [narrow, setNarrow] = useState(() => window.innerWidth < NARROW);

  useEffect(() => {
    const onResize = () => setNarrow(window.innerWidth < NARROW);
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  return narrow;
}

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
  region?: SidebarRegion;
  /** Controlled by Layout, which also owns the header's menu button. */
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { nodes } = useNavigation('sidebar');
  const { pathname } = useLocation();
  const narrow = useIsNarrowViewport();
  const [hovered, setHovered] = useState(false);

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

  /*
   * Containment. A margin above zero turns the sidebar from an edge-flush
   * panel into a card floating clear of the viewport — which changes three
   * things together, because any one of them alone looks like a mistake:
   * the border becomes a full outline rather than a right edge, the corners
   * round, and the sticky offset and max height have to account for the
   * gap or the sidebar overhangs the bottom of the window.
   *
   * The narrow-screen drawer opts out of all of it: a panel that slides in
   * from the edge with a gap behind it reads as broken, not as contained.
   */
  const margin = narrow ? 0 : Number(region?.margin ?? 0);
  const contained = margin > 0;
  const heightMode = (region?.height ?? 'auto') as SidebarHeight;
  // dvh rather than vh so a mobile browser's retracting chrome doesn't
  // leave the sidebar hanging below the fold. Same support story as the
  // container queries this app already relies on.
  const viewportHeight = `calc(100dvh - ${margin * 2}px)`;
  const height =
    heightMode === 'full'
      ? viewportHeight
      : heightMode === 'fixed' && region?.height_px
        ? `${region.height_px}px`
        : undefined;

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
          ...regionCss(region, contained ? 'all' : 'right'),
          width: visible ? (expanded ? WIDTHS[width] ?? WIDTHS.standard : COLLAPSED_WIDTH) : 0,
          flexShrink: 0,
          // Without this the height calc is content-box, so the sidebar's
          // own padding and border are added ON TOP of "fill the viewport"
          // and it overhangs by exactly that much (30px, found during
          // verification). There's no global box-sizing reset in this app,
          // so anything doing viewport arithmetic has to say so itself.
          boxSizing: 'border-box',
          overflowX: 'hidden',
          overflowY: 'auto',
          padding: visible ? 'var(--space-normal, 12px) var(--space-tight, 6px)' : 0,
          transition: 'width 150ms ease',
          display: 'flex',
          flexDirection: 'column',
          gap: 'var(--space-normal, 12px)',
          // A hidden sidebar is hidden: without this it still renders its
          // margin and its 1px outline, leaving a thin bordered sliver
          // pinned to the edge of the page.
          ...(visible ? {} : { border: 'none', margin: 0 }),
          ...(contained && visible ? { margin, borderRadius: region?.radius ?? 'var(--radius, 8px)' } : {}),
          ...(height ? { height } : {}),
          // On a narrow screen it floats over the content instead of
          // squeezing it into nothing.
          ...(narrow
            ? { position: 'fixed', top: 0, bottom: 0, left: 0, zIndex: 50 }
            : { position: 'sticky', top: margin, alignSelf: 'flex-start', maxHeight: viewportHeight }),
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

        {/* Branding stays pinned to the top; only the links move, which is
            what the reference design does — the identity anchors the panel
            and the menu sits wherever the theme wants it. */}
        <ul
          style={{
            listStyle: 'none',
            margin: 0,
            padding: 0,
            display: 'flex',
            flexDirection: 'column',
            gap: 2,
            width: '100%',
            ...NAV_ALIGN_MARGIN[(region?.nav_align ?? 'top') as NavAlign],
          }}
        >
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
