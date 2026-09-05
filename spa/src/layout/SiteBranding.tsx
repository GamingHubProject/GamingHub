import { Link } from 'react-router-dom';
import { useSiteChrome } from '../providers/ThemeProvider';

/**
 * The site's logo, name and optional tagline — one component used by both
 * surfaces, because branding that differs between the header and the
 * sidebar isn't branding.
 *
 * The content is the *site's* (Site Options), never the theme's: a theme
 * exported and handed to another community must not arrive carrying
 * someone else's logo. All a theme decides is whether each surface shows
 * this at all.
 */
export function SiteBranding({
  showTagline = false,
  compact = false,
}: {
  showTagline?: boolean;
  /** Icons-only sidebar rail: the logo alone, no text. */
  compact?: boolean;
}) {
  const { branding } = useSiteChrome();

  // Nothing to show at all — an install with no name set is possible
  // (site_name falls back to the app name, but a logo-less, name-less
  // block would just be empty space).
  if (!branding?.name && !branding?.logo_url) return null;

  return (
    <Link
      to="/"
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 'var(--space-normal, 12px)',
        padding: 'var(--space-normal, 12px)',
        color: 'inherit',
        textDecoration: 'none',
        minWidth: 0,
      }}
    >
      {branding.logo_url && (
        <img
          src={branding.logo_url}
          alt=""
          style={{
            width: 28,
            height: 28,
            objectFit: 'contain',
            flexShrink: 0,
            borderRadius: 'calc(var(--radius, 8px) / 2)',
          }}
        />
      )}
      {!compact && (
        <span style={{ display: 'flex', flexDirection: 'column', minWidth: 0, lineHeight: 1.25 }}>
          <strong style={{ fontSize: '1rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
            {branding.name}
          </strong>
          {showTagline && branding.tagline && (
            <span
              style={{
                fontSize: '0.78rem',
                opacity: 0.7,
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap',
              }}
            >
              {branding.tagline}
            </span>
          )}
        </span>
      )}
    </Link>
  );
}
