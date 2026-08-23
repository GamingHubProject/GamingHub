import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { AssetPicker } from './AssetPicker';
import type { Asset, AssetFolder, AssetList, AssetTag } from '../api/types';

const sampleAsset: Asset = {
  id: 1,
  owner_type: null,
  owner_id: null,
  folder_id: null,
  tags: [],
  url: 'http://localhost/storage/assets/2026/08/abc.png',
  thumbnail_url: 'http://localhost/storage/assets/2026/08/abc-thumb.png',
  mime_type: 'image/png',
  size: 12345,
  width: 800,
  height: 400,
  alt_text: 'A banner',
  uploaded_by: 1,
  created_at: '2026-08-25T00:00:00Z',
};

const emptyFolders: AssetFolder[] = [];
const emptyTags: AssetTag[] = [];

/**
 * The real endpoint is picked from the path — AssetPicker now also queries
 * /asset-folders and /asset-tags alongside /assets, so a single
 * path-blind mock (the old shape) would hand a {items, meta} list back to
 * a folders/tags query expecting an array.
 */
function renderPicker(
  client: {
    get: (path: string) => Promise<unknown>;
    upload?: (path: string, formData: FormData) => Promise<unknown>;
  },
  props: { value?: Asset | null; onChange?: (asset: Asset | null) => void } = {}
) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const fullClient = {
    get: (path: string) => {
      if (path.startsWith('/api/v1/asset-folders')) return Promise.resolve(emptyFolders);
      if (path.startsWith('/api/v1/asset-tags')) return Promise.resolve(emptyTags);
      return client.get(path);
    },
    upload: client.upload ?? (async () => { throw new Error('upload not expected'); }),
  };

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={fullClient as any}>
        <AssetPicker value={props.value ?? null} onChange={props.onChange ?? (() => {})} />
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('AssetPicker', () => {
  it('shows an empty placeholder and "Choose image" when no value is set', () => {
    renderPicker({ get: async () => ({ items: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 24 } }) });

    expect(screen.getByText('none')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Choose image' })).toBeInTheDocument();
  });

  it('shows the selected asset\'s thumbnail and "Change image" when a value is set', () => {
    renderPicker(
      { get: async () => ({ items: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 24 } }) },
      { value: sampleAsset }
    );

    expect(screen.getByRole('img')).toHaveAttribute('src', sampleAsset.thumbnail_url);
    expect(screen.getByRole('button', { name: 'Change image' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Remove' })).toBeInTheDocument();
  });

  it('calls onChange(null) when Remove is clicked', () => {
    let changedTo: Asset | null | undefined;

    renderPicker(
      { get: async () => ({ items: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 24 } }) },
      { value: sampleAsset, onChange: (asset) => (changedTo = asset) }
    );

    screen.getByRole('button', { name: 'Remove' }).click();

    expect(changedTo).toBeNull();
  });

  it('opens the browse modal and lists existing assets as a thumbnail grid', async () => {
    const list: AssetList = { items: [sampleAsset], meta: { current_page: 1, last_page: 1, total: 1, per_page: 24 } };

    renderPicker({ get: async () => list });

    screen.getByRole('button', { name: 'Choose image' }).click();

    await waitFor(() => expect(screen.getAllByRole('img').length).toBeGreaterThan(0));
  });

  it('selecting a grid thumbnail calls onChange with that asset and closes the modal', async () => {
    const list: AssetList = { items: [sampleAsset], meta: { current_page: 1, last_page: 1, total: 1, per_page: 24 } };
    let selected: Asset | null | undefined;

    renderPicker({ get: async () => list }, { onChange: (asset) => (selected = asset) });

    screen.getByRole('button', { name: 'Choose image' }).click();
    await waitFor(() => expect(screen.getAllByRole('img').length).toBeGreaterThan(0));

    screen.getByTitle(sampleAsset.alt_text!).click();

    expect(selected).toEqual(sampleAsset);
    await waitFor(() => expect(screen.queryByText('Cancel')).not.toBeInTheDocument());
  });

  it('uploading a file calls the upload endpoint and selects the resulting asset', async () => {
    const emptyList: AssetList = { items: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 24 } };
    let uploadedPath: string | null = null;
    let selected: Asset | null | undefined;

    renderPicker(
      {
        get: async () => emptyList,
        upload: async (path: string) => {
          uploadedPath = path;
          return sampleAsset;
        },
      },
      { onChange: (asset) => (selected = asset) }
    );

    screen.getByRole('button', { name: 'Choose image' }).click();
    await waitFor(() => expect(screen.getByText('No assets here yet.')).toBeInTheDocument());

    const file = new File(['x'], 'banner.png', { type: 'image/png' });
    const input = document.querySelector('input[type=file]') as HTMLInputElement;
    Object.defineProperty(input, 'files', { value: [file] });
    input.dispatchEvent(new Event('change', { bubbles: true }));

    await waitFor(() => expect(selected).toEqual(sampleAsset));
    expect(uploadedPath).toBe('/api/v1/assets');
  });
});
