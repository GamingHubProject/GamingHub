import { Link, useLocation } from 'react-router-dom';

function humanize(segment: string): string {
  return segment.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export function Breadcrumbs() {
  const location = useLocation();
  const segments = location.pathname.split('/').filter(Boolean);

  if (segments.length === 0) {
    return null;
  }

  return (
    <nav aria-label="Breadcrumb" style={{ padding: '8px 24px', fontSize: '0.85rem', opacity: 0.8 }}>
      <Link to="/">Home</Link>
      {segments.map((segment, index) => {
        const to = '/' + segments.slice(0, index + 1).join('/');
        return (
          <span key={to}>
            {' / '}
            <Link to={to}>{humanize(segment)}</Link>
          </span>
        );
      })}
    </nav>
  );
}
