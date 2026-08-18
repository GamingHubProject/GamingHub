import { RouterProvider } from 'react-router-dom';
import { QueryProvider } from './providers/QueryProvider';
import { ApiClientProvider } from './providers/ApiClientProvider';
import { AuthProvider } from './providers/AuthProvider';
import { ThemeProvider } from './providers/ThemeProvider';
import { router } from './router/routes';
import './widgets';

export function App() {
  return (
    <QueryProvider>
      <ApiClientProvider>
        <AuthProvider>
          <ThemeProvider>
            <RouterProvider router={router} />
          </ThemeProvider>
        </AuthProvider>
      </ApiClientProvider>
    </QueryProvider>
  );
}
