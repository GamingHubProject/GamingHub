import { Link } from 'react-router-dom';

/**
 * The admin landing page — the entry point the header's "Admin" link goes
 * to, and the one place that knows where everything is.
 *
 * Some destinations are React routes and some are Filament pages. That
 * split is marked rather than hidden: a card that looks identical to an
 * in-app route but triggers a full page load reads as a broken link the
 * first time. Nothing here re-implements a Filament form — duplicating the
 * theme editor would mean two implementations of one schema.
 */
interface AdminDestination {
  title: string;
  description: string;
  href: string;
  /** True for Filament, which is a separate app outside this SPA. */
  external?: boolean;
}

const DESTINATIONS: AdminDestination[] = [
  {
    title: 'Navigation',
    description: 'The links in the top bar and the sidebar, and how they are nested.',
    href: '/admin/navigation',
  },
  {
    title: 'Assets',
    description: 'Images, fonts and icons — everything the site and its themes draw from.',
    href: '/admin/assets',
  },
  {
    title: 'Themes',
    description: 'Colours, fonts, backgrounds and the styling of the header and sidebar.',
    href: '/admin/system/themes',
    external: true,
  },
  {
    title: 'Site Options',
    description: 'The site\'s name, tagline, logo and other details that are not part of a theme.',
    href: '/admin/system/site-options',
    external: true,
  },
  {
    title: 'Games',
    description: 'The games this site is about, and the servers under each of them.',
    href: '/admin/system/games',
    external: true,
  },
  {
    title: 'Full admin panel',
    description: 'Users, roles, packages, the audit log and everything else.',
    href: '/admin/system',
    external: true,
  },
];

export function AdminPlaceholder() {
  return (
    <div>
      <h1>Admin</h1>
      <p style={{ color: 'var(--muted, #666)', maxWidth: '60ch' }}>
        Everything that configures the site. Cards marked{' '}
        <ExternalMark /> open the full admin panel, which is a separate app.
      </p>

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))',
          gap: 'var(--space-loose, 16px)',
          marginTop: 'var(--space-section, 24px)',
        }}
      >
        {DESTINATIONS.map((destination) => (
          <AdminCard key={destination.title} destination={destination} />
        ))}
      </div>
    </div>
  );
}

function AdminCard({ destination }: { destination: AdminDestination }) {
  const body = (
    <>
      <strong style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.02rem' }}>
        {destination.title}
        {destination.external && <ExternalMark />}
      </strong>
      <span style={{ color: 'var(--muted, #666)', fontSize: '0.9rem', lineHeight: 1.45 }}>
        {destination.description}
      </span>
    </>
  );

  const style = {
    display: 'flex',
    flexDirection: 'column' as const,
    gap: 'var(--space-tight, 6px)',
    padding: 'var(--space-loose, 16px)',
    border: '1px solid var(--border, #ddd)',
    borderRadius: 'var(--radius, 8px)',
    background: 'var(--surface, transparent)',
    color: 'inherit',
    textDecoration: 'none',
  };

  // A Filament page isn't a react-router route, so it needs a real
  // navigation rather than a client-side one.
  return destination.external ? (
    <a href={destination.href} style={style}>
      {body}
    </a>
  ) : (
    <Link to={destination.href} style={style}>
      {body}
    </Link>
  );
}

function ExternalMark() {
  return (
    <span
      aria-label="opens the full admin panel"
      title="Opens the full admin panel"
      style={{
        fontSize: '0.7rem',
        padding: '1px 6px',
        borderRadius: 999,
        border: '1px solid var(--border, #ddd)',
        color: 'var(--muted, #666)',
        whiteSpace: 'nowrap',
      }}
    >
      ↗ panel
    </span>
  );
}
