import { createContext, useContext, type ReactNode } from 'react';
import { api as defaultApi } from '../api/client';

type ApiClient = typeof defaultApi;

const ApiClientContext = createContext<ApiClient>(defaultApi);

export function ApiClientProvider({
  children,
  client = defaultApi,
}: {
  children: ReactNode;
  client?: ApiClient;
}) {
  return <ApiClientContext.Provider value={client}>{children}</ApiClientContext.Provider>;
}

export function useApi(): ApiClient {
  return useContext(ApiClientContext);
}
