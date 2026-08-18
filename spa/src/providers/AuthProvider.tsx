import { createContext, useContext, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useApi } from './ApiClientProvider';
import { ApiError } from '../api/client';
import type { User } from '../api/types';

interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  refetch: () => void;
}

const AuthContext = createContext<AuthContextValue>({
  user: null,
  isLoading: true,
  refetch: () => {},
});

/**
 * Bootstraps the Sanctum session by asking /api/v1/user who's logged in.
 * A 401 here means "guest", not an error — swallowed to null rather than
 * surfaced as a query error, since every page needs to render fine for an
 * anonymous visitor.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const api = useApi();

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['auth', 'user'],
    queryFn: async (): Promise<User | null> => {
      try {
        return await api.get<User>('/api/v1/user');
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          return null;
        }
        throw error;
      }
    },
    retry: false,
  });

  return (
    <AuthContext.Provider value={{ user: data ?? null, isLoading, refetch }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  return useContext(AuthContext);
}
