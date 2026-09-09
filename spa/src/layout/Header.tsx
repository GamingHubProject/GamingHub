import { useEffect, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../providers/AuthProvider';
import { useSiteChrome } from '../providers/ThemeProvider';
import { AccountMenu } from './AccountMenu';
import { NavLeaf } from './NavRow';
import { SiteBranding } from './SiteBranding';
import { regionAccent, regionCss } from './regionStyle';
import { useNavigation } from './useNavigation';
import type { NavNode } from './useNavigation';

export function Header({
  showNavLinks = true,
  showBranding,
  onToggleSidebar,
  showAccount = true,
  canRelocateAccount = false,
}: {
  /** False in sidebar-only mode, where a top bar of links would just
   *  duplicate the sidebar. The account controls always stay. */
  showNavLinks?: boolean;
  /** Layout decides this, not the theme alone — the branding should appear
   *  once, and in sidebar-only mode the sidebar is where it belongs. */
  showBranding?: boolean;
  /** Provided only when the sidebar is actually hideable. */
  onToggleSidebar?: () => void;
  /** False when the sidebar is hosting the account menu instead. */
  showAccount?: boolean;
  /** True when a sidebar exists that could host the account menu instead. */
  canRelocateAccount?: boolean;
} = {}) {
  const { user, isLoading } = useAuth();
  const chrome = useSiteChrome();
  const region = chrome.header;
  const { nodes } = useNavigation('header');
  const { pathname } = useLocation();
  const accent = regionAccent(region);

  return (
    <header
      style={{
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: 'var(--space-normal, 12px) var(--space-section, 24px)',
        // The toggle belongs to the sidebar, so it sits close to it rather
        // than a full section-gap away, where it read as an unrelated
        // control floating in the header.
        //
        // Both branches are spelled out: `undefined` here does NOT fall
        // back to the shorthand above — React assigns style properties one
        // by one, so it clears the longhand the shorthand had set and the
        // header loses its left padding entirely.
        paddingLeft: onToggleSidebar ? 'var(--space-normal, 12px)' : 'var(--space-section, 24px)',
        gap: 'var(--space-normal, 12px)',
        // Styled independently of the sidebar — one can be transparent
        // while the other is solid. See layout/regionStyle.
        ...regionCss(region, 'bottom'),
      }}
    >
      {/* minWidth:0 makes this the element that compresses when the header
          is tight, rather than the branding or the account controls. */}
      <nav
        aria-label="Main"
        style={{
          display: 'flex',
          gap: 'var(--space-normal, 16px)',
          alignItems: 'center',
          minWidth: 0,
          overflow: 'hidden',
        }}
      >
        {onToggleSidebar && (
          <button type="button" aria-label="Toggle navigation" onClick={onToggleSidebar} style={{ padding: '4px 8px' }}>
            ☰
          </button>
        )}
        {/* Home and Games are the fallback for a site whose admin has not
            built a navigation yet — without them a fresh install would
            have no way to move around at all. Once any link exists, the
            configured navigation replaces them entirely. */}
        {(showBranding ?? region?.show_branding !== false) && (
          <SiteBranding showTagline={region?.show_tagline === true} />
        )}
        {showNavLinks &&
          (nodes.length > 0 ? (
            nodes.map((node) => <TopNavNode key={node.id} node={node} pathname={pathname} accent={accent} />)
          ) : (
            <>
              <Link to="/">Home</Link>
              <Link to="/games">Games</Link>
            </>
          ))}
      </nav>
      <div style={{ display: 'flex', gap: 16, alignItems: 'center', flexShrink: 0, whiteSpace: 'nowrap' }}>
        {/* Dashboard and Admin used to sit loose here, competing with the
            site's own navigation for the same strip of header. They live
            in the account menu now — see AccountMenu. `showAccount` is
            false when the sidebar is hosting that menu instead; the Log in
            link stays regardless, because a signed-out visitor has no
            account menu to find it in. */}
        {!isLoading &&
          (user ? (
            showAccount && (
              <AccountMenu
                name={user.display_name || user.name}
                isAdmin={!!user.is_admin}
                avatarUrl={user.avatar_url}
                placement="header"
                canRelocate={canRelocateAccount}
              />
            )
          ) : (
            <Link to="/login">Log in</Link>
          ))}
      </div>
    </header>
  );
}

/**
 * A top-level navigation entry in the header. A folder becomes a dropdown
 * here and an expandable section in the sidebar — same data, two
 * renderings.
 */
function TopNavNode({ node, pathname, accent }: { node: NavNode; pathname: string; accent: string }) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;

    function onPointerDown(event: PointerEvent) {
      if (!ref.current?.contains(event.target as Node)) setOpen(false);
    }
    document.addEventListener('pointerdown', onPointerDown, true);
    return () => document.removeEventListener('pointerdown', onPointerDown, true);
  }, [open]);

  if (node.type !== 'folder') {
    // No reserved icon column in the top bar: rows sit side by side there,
    // so there's no column of labels to line up and the gap would just be
    // dead space beside a link with no icon.
    return <NavLeaf node={node} pathname={pathname} accent={accent} reserveIcon={false} />;
  }

  return (
    <div ref={ref} style={{ position: 'relative' }}>
      <button
        type="button"
        aria-haspopup="true"
        aria-expanded={open}
        onClick={() => setOpen((o) => !o)}
        onKeyDown={(event) => event.key === 'Escape' && setOpen(false)}
        style={{ background: 'none', border: 'none', font: 'inherit', color: 'inherit', cursor: 'pointer', padding: 0 }}
      >
        {node.label} <span aria-hidden="true" style={{ fontSize: '0.7em', opacity: 0.7 }}>▾</span>
      </button>
      {open && (
        <div
          style={{
            position: 'absolute',
            top: '100%',
            left: 0,
            marginTop: 4,
            minWidth: 180,
            zIndex: 60,
            background: 'var(--surface, #fff)',
            border: '1px solid var(--border, #ddd)',
            borderRadius: 'calc(var(--radius, 8px) / 1.5)',
            padding: 'var(--space-tight, 6px)',
            display: 'flex',
            flexDirection: 'column',
            gap: 2,
            boxShadow: '0 4px 16px rgba(0,0,0,0.15)',
          }}
        >
          {node.children.map((child) => (
            <NavLeaf key={child.id} node={child} pathname={pathname} accent={accent} />
          ))}
        </div>
      )}
    </div>
  );
}
