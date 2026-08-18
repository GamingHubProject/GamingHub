import { createBrowserRouter } from 'react-router-dom';
import { Layout } from '../layout/Layout';
import { Portal } from '../pages/Portal';
import { GamesList } from '../pages/GamesList';
import { GameDetail } from '../pages/GameDetail';
import { ServerDetail } from '../pages/ServerDetail';
import { Dashboard } from '../pages/Dashboard';
import { WebTreePage } from '../pages/WebTreePage';

export const router = createBrowserRouter([
  {
    path: '/',
    element: <Layout />,
    children: [
      { index: true, element: <Portal /> },
      { path: 'games', element: <GamesList /> },
      { path: 'games/:slug', element: <GameDetail /> },
      { path: 'games/:slug/servers/:id', element: <ServerDetail /> },
      { path: 'dashboard', element: <Dashboard /> },
      { path: 'pages/*', element: <WebTreePage /> },
    ],
  },
]);
