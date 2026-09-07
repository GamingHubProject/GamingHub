import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';

/**
 * The signed-in visitor's own controls, in one component because they now
 * appear in two different places (see AccountPlacement).
 *
 * Everything that used to sit loose in the header — Admin, Dashboard — is
 * inside the menu. Those were two links competing with the site's own
 * navigation for the same strip of header, and they are not navigation:
 * they are things *this* visitor can do, which is exactly what a menu
 * under their own name is for. A visitor who isn't an admin never saw
 * Admin anyway, so for most people the header simply gets quieter.
 *
 * `direction` exists because the same menu opens downward from a header
 * and upward from the bottom of a sidebar — a menu that opens off the
 * bottom of the window is not a menu.
 */
export function AccountMenu({
  name,
  isAdmin,
  direction = 'down',
  full = false,
  placement,
  canRelocate = false,
}: {
  name: string;
  isAdmin: boolean;
  /** Which way the panel opens. Up when anchored to the bottom of a sidebar. */
  direction?: 'up' | 'down';
  /** Fill the available width, as the sidebar wants and the header does not. */
  full?: boolean;
  /** Where this menu currently is, so it can offer the other place. */
  placement?: 'header' | 'sidebar';
  /**
   * Whether moving it is actually possible right now. False when the
   * layout has no sidebar able to host the menu — offering a move that
   * silently wouldn't happen is worse than not offering it.
   */
  canRelocate?: boolean;
}) {
  const api = useApi();
  const { refetch } = useAuth();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [moving, setMoving] = useState(false);
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

  /**
   * Move the menu to the other region, for this person only.
   *
   * Saved on the user rather than held in the browser so it follows them
   * between devices — it is a statement about how they want the site, not
   * about this laptop. The refetch is what re-renders it in its new home:
   * the placement is derived from the user, so re-reading the user is the
   * whole update.
   */
  async function handleMove() {
    const target = placement === 'sidebar' ? 'header' : 'sidebar';
    setMoving(true);
    try {
      await api.patch('/api/v1/user/preferences', { account_placement: target });
      await refetch();
      setOpen(false);
    } finally {
      setMoving(false);
    }
  }

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
    <div ref={menuRef} style={{ position: 'relative', width: full ? '100%' : undefined }}>
      <button
        type="button"
        aria-haspopup="true"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
        style={{
          background: 'none',
          border: 'none',
          cursor: 'pointer',
          font: 'inherit',
          color: 'inherit',
          width: full ? '100%' : undefined,
          textAlign: full ? 'left' : undefined,
          display: full ? 'flex' : undefined,
          justifyContent: full ? 'space-between' : undefined,
          alignItems: 'center',
          gap: 'var(--space-tight, 6px)',
        }}
      >
        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{name}</span>
        <span aria-hidden="true" style={{ fontSize: '0.7em', opacity: 0.7 }}>
          {direction === 'up' ? '▴' : '▾'}
        </span>
      </button>
      {open && (
        <div
          style={{
            position: 'absolute',
            ...(direction === 'up' ? { bottom: '100%', marginBottom: 4 } : { top: '100%', marginTop: 4 }),
            right: 0,
            left: full ? 0 : undefined,
            background: 'var(--surface, #fff)',
            border: '1px solid var(--border, #ddd)',
            borderRadius: 'calc(var(--radius, 8px) / 2)',
            minWidth: 160,
            zIndex: 70,
            padding: 'var(--space-tight, 6px)',
            display: 'flex',
            flexDirection: 'column',
            gap: 2,
            boxShadow: '0 4px 16px rgba(0,0,0,0.15)',
          }}
        >
          <MenuLink to="/dashboard" onNavigate={() => setOpen(false)}>
            Dashboard
          </MenuLink>
          {/* Gated on is_admin, not merely on being signed in — a visitor
              who isn't an admin has nothing to do there. */}
          {isAdmin && (
            <MenuLink to="/admin" onNavigate={() => setOpen(false)}>
              Admin
            </MenuLink>
          )}
          {/* No profile page built yet — placeholder only. */}
          <button type="button" disabled style={{ ...itemStyle, color: 'var(--muted, #999)', cursor: 'not-allowed' }}>
            Profile
          </button>
          {canRelocate && placement && (
            <button type="button" onClick={handleMove} disabled={moving} style={{ ...itemStyle, color: 'var(--muted, #666)', fontSize: '0.9em' }}>
              {placement === 'sidebar' ? 'Move to the top bar' : 'Move to the sidebar'}
            </button>
          )}
          <hr style={{ border: 0, borderTop: '1px solid var(--border, #ddd)', opacity: 0.5, margin: '2px 0', width: '100%' }} />
          <button type="button" onClick={handleLogout} style={itemStyle}>
            Logout
          </button>
        </div>
      )}
    </div>
  );
}

const itemStyle = {
  display: 'block',
  width: '100%',
  textAlign: 'left' as const,
  padding: 'var(--space-tight, 6px) var(--space-normal, 12px)',
  background: 'none',
  border: 'none',
  borderRadius: 'calc(var(--radius, 8px) / 2)',
  cursor: 'pointer',
  font: 'inherit',
  color: 'inherit',
  textDecoration: 'none',
};

function MenuLink({ to, onNavigate, children }: { to: string; onNavigate: () => void; children: React.ReactNode }) {
  return (
    <Link to={to} onClick={onNavigate} style={itemStyle}>
      {children}
    </Link>
  );
}
