import { useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import { useSiteChrome } from '../providers/ThemeProvider';
import { NavLeaf } from './NavRow';
import { SiteBranding } from './SiteBranding';
import { regionAccent, regionCss } from './regionStyle';
import { useNavigation } from './useNavigation';
import type { NavNode } from './useNavigation';

export function Header({
  showNavLinks = true,
  onToggleSidebar,
}: {
  /** False in sidebar-only mode, where a top bar of links would just
   *  duplicate the sidebar. The account controls always stay. */
  showNavLinks?: boolean;
  /** Provided only when a sidebar exists to toggle. */
  onToggleSidebar?: () => void;
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
        gap: 'var(--space-normal, 12px)',
        // Styled independently of the sidebar — one can be transparent
        // while the other is solid. See layout/regionStyle.
        ...regionCss(region, 'bottom'),
      }}
    >
      <nav aria-label="Main" style={{ display: 'flex', gap: 'var(--space-normal, 16px)', alignItems: 'center' }}>
        {onToggleSidebar && (
          <button type="button" aria-label="Toggle navigation" onClick={onToggleSidebar} style={{ padding: '4px 8px' }}>
            ☰
          </button>
        )}
        {/* Home and Games are the fallback for a site whose admin has not
            built a navigation yet — without them a fresh install would
            have no way to move around at all. Once any link exists, the
            configured navigation replaces them entirely. */}
        {region?.show_branding !== false && <SiteBranding showTagline={region?.show_tagline === true} />}
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
      <div style={{ display: 'flex', gap: 16, alignItems: 'center' }}>
        {/* /admin is the SPA's own (in-progress) admin area and is now the
            primary entry point; Filament at /admin/system is reached via a
            button inside React Admin, not directly from this header.
            Gated on is_admin, not just being logged in — a non-admin user
            has nothing to do there. */}
        {user?.is_admin && <Link to="/admin">Admin</Link>}
        {user && <Link to="/dashboard">Dashboard</Link>}
        {!isLoading && (user ? <UserMenu name={user.name} isAdmin={user.is_admin} /> : <Link to="/login">Log in</Link>)}
      </div>
    </header>
  );
}

function UserMenu({ name, isAdmin }: { name: string; isAdmin: boolean }) {
  const api = useApi();
  const { refetch } = useAuth();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;

    function handleClickOutside(event: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [open]);

  async function handleLogout() {
    setOpen(false);
    try {
      await api.post('/logout');
    } catch {
      // Even if the request itself errors, refetch below reflects the
      // real server-side auth state either way.
    }
    await refetch();
    navigate('/');
  }

  return (
    <div ref={menuRef} style={{ position: 'relative' }}>
      <button type="button" onClick={() => setOpen((value) => !value)} style={{ background: 'none', border: 'none', cursor: 'pointer', font: 'inherit', color: 'inherit' }}>
        {name} ▾
      </button>
      {open && (
        <div
          style={{
            position: 'absolute',
            top: '100%',
            right: 0,
            marginTop: 4,
            background: 'var(--background, #fff)',
            border: '1px solid var(--border, #ddd)',
            borderRadius: 'calc(var(--radius, 8px) / 2)',
            minWidth: 140,
            zIndex: 10,
          }}
        >
          {isAdmin && (
            <Link
              to="/admin/assets"
              onClick={() => setOpen(false)}
              style={{ display: 'block', width: '100%', padding: '8px 12px', color: 'inherit', textDecoration: 'none' }}
            >
              Assets
            </Link>
          )}
          {/* No profile page built yet — placeholder only. */}
          <button type="button" disabled style={{ display: 'block', width: '100%', textAlign: 'left', padding: '8px 12px', background: 'none', border: 'none', color: 'var(--muted, #999)', cursor: 'not-allowed' }}>
            Profile
          </button>
          <button type="button" onClick={handleLogout} style={{ display: 'block', width: '100%', textAlign: 'left', padding: '8px 12px', background: 'none', border: 'none', cursor: 'pointer', font: 'inherit', color: 'inherit' }}>
            Logout
          </button>
        </div>
      )}
    </div>
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
