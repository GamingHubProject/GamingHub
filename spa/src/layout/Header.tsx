import { Link } from 'react-router-dom';
import { useAuth } from '../providers/AuthProvider';

export function Header() {
  const { user, isLoading } = useAuth();

  return (
    <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 24px', borderBottom: '1px solid var(--border, #ddd)' }}>
      <nav style={{ display: 'flex', gap: 16 }}>
        <Link to="/">Home</Link>
        <Link to="/games">Games</Link>
        {user && <Link to="/dashboard">Dashboard</Link>}
      </nav>
      <div>
        {!isLoading && (user ? <span>{user.name}</span> : <a href="/login">Log in</a>)}
      </div>
    </header>
  );
}
