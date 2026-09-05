import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';

/**
 * The site's navigation tree, fetched once and shared by both surfaces
 * that render it.
 *
 * One hook rather than one per surface because the header and the sidebar
 * show the *same* links — a folder is a dropdown in one and an expandable
 * section in the other, which is a rendering decision, not a data one.
 * Sharing the query key also means "both" mode costs one request, not two.
 */
export interface NavNode {
  id: number;
  type: 'page' | 'link' | 'folder';
  label: string;
  /** Null for a folder, which is a container rather than a destination. */
  url: string | null;
  icon_url: string | null;
  children: NavNode[];
}

export type NavSurface = 'header' | 'sidebar';

/**
 * One surface's navigation tree.
 *
 * Mirroring is resolved server-side, so asking for the sidebar while it
 * follows the header simply returns the header's tree — the client never
 * has to know which surface owns the rows.
 */
export function useNavigation(surface: NavSurface = 'header'): { nodes: NavNode[]; isLoading: boolean } {
  const api = useApi();

  const { data, isLoading } = useQuery({
    queryKey: ['navigation', surface],
    // The client unwraps the `data` envelope, so this resolves to the
    // array itself rather than { data: [...] }.
    queryFn: () => api.get<NavNode[]>(`/api/v1/navigation?surface=${surface}`),
    // The nav changes when an admin edits it, not while someone is
    // browsing — refetching it on every window focus is pure noise.
    staleTime: 5 * 60 * 1000,
  });

  return { nodes: data ?? [], isLoading };
}

/**
 * True when a link should read as the current page. Exact match except
 * for the root, which would otherwise match every path.
 */
export function isActive(url: string | null, pathname: string): boolean {
  if (!url) return false;
  if (url === '/') return pathname === '/';

  return pathname === url || pathname.startsWith(`${url}/`);
}

/** An internal route react-router can handle, vs. something off-site. */
export function isExternal(url: string): boolean {
  return /^[a-z][a-z0-9+.-]*:/i.test(url) || url.startsWith('//');
}
