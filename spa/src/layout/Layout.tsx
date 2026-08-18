import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Breadcrumbs } from './Breadcrumbs';

export function Layout() {
  return (
    <div>
      <Header />
      <Breadcrumbs />
      <main style={{ padding: 24 }}>
        <Outlet />
      </main>
    </div>
  );
}
