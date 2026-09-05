import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Breadcrumbs } from './Breadcrumbs';
import { Sidebar } from './Sidebar';
import type { SidebarBehavior } from './Sidebar';
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

  return (
    <div style={{ display: 'flex', minHeight: '100vh' }}>
      {showSidebar && (
        <Sidebar
          behavior={(chrome.sidebar_behavior ?? 'always') as SidebarBehavior}
          open={sidebarOpen}
          onOpenChange={setSidebarOpen}
        />
      )}

      {/* minWidth:0 so a wide child (a table, a grid) shrinks inside this
          column instead of pushing the sidebar off screen. */}
      <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
        <Header
          showNavLinks={showTopNav}
          onToggleSidebar={showSidebar ? () => setSidebarOpen((o) => !o) : undefined}
        />
        <Breadcrumbs />
        <main style={{ padding: 'var(--space-section, 24px)', flex: 1 }}>
          <Outlet />
        </main>
      </div>
    </div>
  );
}
