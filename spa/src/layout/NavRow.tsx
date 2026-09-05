import type { CSSProperties, ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { isActive, isExternal } from './useNavigation';
import type { NavNode } from './useNavigation';

/**
 * One navigation row, shared by every kind the sidebar renders.
 *
 * It exists because three separate row implementations disagreed with each
 * other, which is what made the sidebar read as misaligned:
 *
 * - a folder's expand button spread rowStyle(false) and then overrode
 *   `background: 'none'`, so a dropdown holding the current page could
 *   never highlight and its background differed from a link's;
 * - the folder button set width:100% and links didn't, so adjacent rows
 *   had different hit areas and different highlight widths;
 * - the icon rendered nothing when absent and the flex gap collapsed with
 *   it, so a row with an icon indented its label 32px and one without
 *   indented it 12px.
 *
 * One primitive fixes all three by construction: every row is the same
 * height, the same width, and reserves the icon column whether or not
 * there's an icon in it.
 */
const ICON_SIZE = 20;

export function rowStyle(active: boolean, accent: string): CSSProperties {
  return {
    display: 'flex',
    alignItems: 'center',
    gap: 'var(--space-normal, 12px)',
    // Full width on every row type, so the highlight is the row and not
    // the text inside it.
    width: '100%',
    boxSizing: 'border-box',
    minHeight: 38,
    padding: 'var(--space-tight, 6px) var(--space-normal, 12px)',
    borderRadius: 'calc(var(--radius, 8px) / 1.5)',
    border: 'none',
    color: active ? accent : 'inherit',
    background: active ? 'var(--surface-muted, rgba(127,127,127,0.14))' : 'transparent',
    font: 'inherit',
    fontWeight: active ? 600 : 400,
    cursor: 'pointer',
    textAlign: 'left',
    textDecoration: 'none',
    whiteSpace: 'nowrap',
  };
}

/**
 * The icon column, reserved even when empty. That reservation is the whole
 * point — labels line up down the column whether or not each link happens
 * to have an icon.
 */
export function NavIcon({ url, hidden = false }: { url: string | null; hidden?: boolean }) {
  if (hidden) return null;

  return (
    <span
      aria-hidden={!url}
      style={{ width: ICON_SIZE, height: ICON_SIZE, flexShrink: 0, display: 'inline-flex', alignItems: 'center' }}
    >
      {url && <img src={url} alt="" style={{ width: '100%', height: '100%', objectFit: 'contain' }} />}
    </span>
  );
}

/** A row that navigates: an internal route, or an off-site URL. */
export function NavLeaf({
  node,
  pathname,
  accent,
  showLabel = true,
  reserveIcon = true,
}: {
  node: NavNode;
  pathname: string;
  accent: string;
  showLabel?: boolean;
  reserveIcon?: boolean;
}) {
  const active = isActive(node.url, pathname);
  const style = rowStyle(active, accent);
  const content = (
    <>
      <NavIcon url={node.icon_url} hidden={!reserveIcon} />
      {showLabel && <span style={labelStyle}>{node.label}</span>}
    </>
  );

  if (!node.url) return <span style={style}>{content}</span>;

  // An off-site URL isn't a react-router route; handing it to <Link> would
  // try to resolve it against the app's own routes.
  return isExternal(node.url) ? (
    <a href={node.url} style={style} rel="noreferrer noopener" target="_blank" title={showLabel ? undefined : node.label}>
      {content}
    </a>
  ) : (
    <Link to={node.url} style={style} aria-current={active ? 'page' : undefined} title={showLabel ? undefined : node.label}>
      {content}
    </Link>
  );
}

/** A row that expands: the same shape as a leaf, plus a chevron. */
export function NavFolderRow({
  node,
  open,
  active,
  accent,
  showLabel,
  onToggle,
  children,
}: {
  node: NavNode;
  open: boolean;
  /** True when this folder holds the current page — it highlights like a
   *  link does, which the old implementation couldn't express. */
  active: boolean;
  accent: string;
  showLabel: boolean;
  onToggle: () => void;
  children?: ReactNode;
}) {
  return (
    <button type="button" aria-expanded={open} onClick={onToggle} style={rowStyle(active, accent)}>
      <NavIcon url={node.icon_url} />
      {showLabel && (
        <>
          <span style={labelStyle}>{node.label}</span>
          <span aria-hidden="true" style={{ opacity: 0.6, fontSize: '0.7em' }}>{open ? '▾' : '▸'}</span>
        </>
      )}
      {children}
    </button>
  );
}

const labelStyle: CSSProperties = { flex: 1, overflow: 'hidden', textOverflow: 'ellipsis' };
