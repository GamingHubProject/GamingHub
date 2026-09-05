import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Breadcrumbs } from './Breadcrumbs';
import { Sidebar } from './Sidebar';
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

  const position = chrome.nav_enabled === false ? 'top' : chrome.nav_position ?? 'top';
  const showSidebar = position === 'sidebar' || position === 'both';
  // With the sidebar carrying the links, a top bar would be a duplicate of
  // it — so in sidebar-only mode the header keeps its account controls and
  // drops the nav links.
  const showTopNav = chrome.nav_enabled !== false && (position === 'top' || position === 'both');

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
      onToggleSidebar={showSidebar ? () => setSidebarOpen((o) => !o) : undefined}
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
