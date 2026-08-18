import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { ApiError } from '../api/client';
import type { WebPage } from '../api/types';

export function WebTreePage() {
  const params = useParams<{ '*': string }>();
  const path = params['*'] ?? '';
  const api = useApi();

  const { data: page, isLoading, error } = useQuery({
    queryKey: ['page', path],
    queryFn: () => api.get<WebPage>(`/api/v1/pages/${path}`),
    enabled: !!path,
    retry: false,
  });

  if (isLoading) return <p>Loading…</p>;

  if (error) {
    if (error instanceof ApiError && error.status === 404) {
      return <p>Page not found.</p>;
    }
    return <p>Something went wrong loading this page.</p>;
  }

  if (!page) return null;

  return (
    <article>
      <h1>{page.title}</h1>
      <div style={{ whiteSpace: 'pre-wrap' }}>{page.content}</div>
    </article>
  );
}
