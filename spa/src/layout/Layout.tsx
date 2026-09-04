import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Breadcrumbs } from './Breadcrumbs';

export function Layout() {
  return (
    <div>
      <Header />
      <Breadcrumbs />
      <main style={{ padding: 'calc(var(--spacing, 12px) * 2)' }}>
        <Outlet />
      </main>
    </div>
  );
}
