import { afterEach, describe, expect, it, vi } from 'vitest';
import { api, ApiError } from './client';

function mockFetchResponse(body: unknown, init: { status?: number; contentType?: string } = {}) {
  const status = init.status ?? 200;
  return {
    status,
    ok: status >= 200 && status < 300,
    statusText: 'mock',
    headers: new Headers({ 'content-type': init.contentType ?? 'application/json' }),
    json: async () => body,
    text: async () => String(body),
  } as Response;
}

describe('api client', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('unwraps the {data: T} envelope on GET', async () => {
    const fetchMock = vi.fn().mockResolvedValue(mockFetchResponse({ data: { id: 1, name: 'Ark' } }));
    vi.stubGlobal('fetch', fetchMock);

    const result = await api.get<{ id: number; name: string }>('/api/v1/games/ark');

    expect(result).toEqual({ id: 1, name: 'Ark' });
  });

  it('sends the XSRF-TOKEN cookie as a header on mutating requests', async () => {
    document.cookie = 'XSRF-TOKEN=abc123';
    const fetchMock = vi.fn().mockResolvedValue(mockFetchResponse({ data: {} }));
    vi.stubGlobal('fetch', fetchMock);

    await api.post('/api/v1/dashboard/widgets', { widget_type: 'server-status' });

    // First call is the csrf-cookie bootstrap, second is the actual POST.
    const postCall = fetchMock.mock.calls.find(([, init]) => init?.method === 'POST');
    expect(postCall).toBeDefined();
    const [, init] = postCall!;
    expect((init.headers as Record<string, string>)['X-XSRF-TOKEN']).toBe('abc123');
    expect(init.credentials).toBe('include');
  });

  it('does not attach a CSRF header on GET requests', async () => {
    document.cookie = 'XSRF-TOKEN=abc123';
    const fetchMock = vi.fn().mockResolvedValue(mockFetchResponse({ data: [] }));
    vi.stubGlobal('fetch', fetchMock);

    await api.get('/api/v1/games');

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [, init] = fetchMock.mock.calls[0];
    expect((init.headers as Record<string, string>)['X-XSRF-TOKEN']).toBeUndefined();
  });

  it('throws ApiError with the response status and message on failure', async () => {
    const fetchMock = vi.fn().mockResolvedValue(mockFetchResponse({ message: 'Unauthenticated.' }, { status: 401 }));
    vi.stubGlobal('fetch', fetchMock);

    await expect(api.get('/api/v1/user')).rejects.toMatchObject({
      status: 401,
      message: 'Unauthenticated.',
    });
  });

  it('ApiError instances carry the parsed body', async () => {
    const fetchMock = vi.fn().mockResolvedValue(mockFetchResponse({ message: 'Nope' }, { status: 403 }));
    vi.stubGlobal('fetch', fetchMock);

    try {
      await api.get('/api/v1/user');
      expect.fail('expected ApiError to be thrown');
    } catch (error) {
      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).body).toEqual({ message: 'Nope' });
    }
  });
});
