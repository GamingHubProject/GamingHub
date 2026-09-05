import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Breadcrumbs } from './Breadcrumbs';
import { Sidebar, useIsNarrowViewport } from './Sidebar';
import type { SidebarBehavior, SidebarWidth } from './Sidebar';
import { useSiteChrome } from '../providers/ThemeProvider';

/**
 * The shell: an optional sidebar column beside the main column.
 *
 * Which regions exist is a theme decision (see ThemeBundle's nav_position)
 * — the links inside them are site data. Defaults are top-nav-only with no
 * sidebar, so an install that has never touched this renders exactly as it
 * always did.
 *
 * The open/closed state lives here rather than in Sidebar because the
 * header's menu button toggles it and the two are siblings.
 */
export function Layout() {
  const chrome = useSiteChrome();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const narrow = useIsNarrowViewport();

  const position = chrome.nav_enabled === false ? 'top' : chrome.nav_position ?? 'top';
  const showSidebar = position === 'sidebar' || position === 'both';
  // With the sidebar carrying the links, a top bar would be a duplicate of
  // it — so in sidebar-only mode the header keeps its account controls and
  // drops the nav links.
  const showTopNav = chrome.nav_enabled !== false && (position === 'top' || position === 'both');

  /*
   * The branding appears once. When the sidebar is the *only* nav surface
   * it's the natural home for it — it's where the reference design puts
   * it, and it has the room — so the header defers rather than repeating
   * the site's name a few pixels away. The header still takes it if the
   * sidebar has turned its own off, otherwise the name would vanish
   * entirely.
   *
   * In `both` mode each surface keeps its own toggle, which is what those
   * settings are for.
   */
  const sidebarShowsBranding = showSidebar && chrome.sidebar?.show_branding !== false;
  const headerShowsBranding =
    chrome.header?.show_branding !== false && !(position === 'sidebar' && sidebarShowsBranding);

  /*
   * The toggle button only appears when it would actually do something.
   * `open` is ignored unless the sidebar's effective behaviour is
   * `toggle`: `always` never hides, and `auto-hide` expands on hover
   * instead. A button that visibly refuses to work is worse than no
   * button. Narrow screens force `toggle` regardless of the setting, so
   * they always get one.
   */
  const canToggleSidebar = showSidebar && (narrow || chrome.sidebar?.behavior === 'toggle');

  const sidebar = showSidebar ? (
    <Sidebar
      behavior={(chrome.sidebar?.behavior ?? 'always') as SidebarBehavior}
      width={(chrome.sidebar?.width ?? 'standard') as SidebarWidth}
      region={chrome.sidebar}
      open={sidebarOpen}
      onOpenChange={setSidebarOpen}
    />
  ) : null;

  const header = (
    <Header
      showNavLinks={showTopNav}
      showBranding={headerShowsBranding}
      onToggleSidebar={canToggleSidebar ? () => setSidebarOpen((o) => !o) : undefined}
    />
  );

  const content = (
    // minWidth:0 so a wide child (a table, a grid) shrinks inside this
    // column instead of pushing the sidebar off screen.
    <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
      <Breadcrumbs />
      <main style={{ padding: 'var(--space-section, 24px)', flex: 1 }}>
        <Outlet />
      </main>
    </div>
  );

  /*
   * Two arrangements. By default the sidebar runs the full height and the
   * header sits only over the content — that's what both reference designs
   * do, and it's what makes a sidebar's branding block the top of the page
   * rather than something tucked under a bar.
   *
   * The alternative puts the header across the whole window with the
   * sidebar beneath it, which some sites want and previously had no way to
   * get.
   */
  if (chrome.header?.spans_full_width) {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
        {header}
        <div style={{ display: 'flex', flex: 1, minHeight: 0 }}>
          {sidebar}
          {content}
        </div>
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', minHeight: '100vh' }}>
      {sidebar}
      <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
        {header}
        {content}
      </div>
    </div>
  );
}
