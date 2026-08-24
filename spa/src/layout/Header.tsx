import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';

export function Header() {
  const { user, isLoading } = useAuth();

  return (
    <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 24px', borderBottom: '1px solid var(--border, #ddd)' }}>
      <nav style={{ display: 'flex', gap: 16 }}>
        <Link to="/">Home</Link>
        <Link to="/games">Games</Link>
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
            borderRadius: 4,
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
