import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';

/**
 * POSTs straight to Breeze's own /login (unchanged — still does the real
 * credential check, rate limiting, session regen). Success/failure isn't
 * read from that response: Breeze redirects either way (back to /login on
 * failure, to /dashboard on success), and a redirect-following fetch()
 * can't distinguish the two from status/body alone. Refetching
 * /api/v1/user afterward is the reliable signal instead.
 */
export function Login() {
  const api = useApi();
  const { refetch } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      await api.post('/login', { email, password });
    } catch {
      // Ignored — see refetch() below for how success is actually judged.
    }

    const result = await refetch();
    setSubmitting(false);

    if (result.data) {
      navigate('/dashboard');
    } else {
      setError('Those credentials don’t match our records.');
    }
  }

  return (
    <form onSubmit={handleSubmit} style={{ maxWidth: 320 }}>
      <h1>Log in</h1>
      {error && <p style={{ color: 'crimson' }}>{error}</p>}
      <div style={{ marginBottom: 12 }}>
        <label htmlFor="login-email">Email</label>
        <br />
        <input
          id="login-email"
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
          autoComplete="username"
          style={{ width: '100%' }}
        />
      </div>
      <div style={{ marginBottom: 12 }}>
        <label htmlFor="login-password">Password</label>
        <br />
        <input
          id="login-password"
          type="password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          required
          autoComplete="current-password"
          style={{ width: '100%' }}
        />
      </div>
      <button type="submit" disabled={submitting}>
        {submitting ? 'Logging in…' : 'Log in'}
      </button>
    </form>
  );
}
