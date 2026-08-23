import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Modal } from './Modal';
import { useApi } from '../providers/ApiClientProvider';
import type { Asset, AssetList } from '../api/types';

const DEFAULT_ACCEPT = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];

/**
 * A caller that only kept a snapshot (id + url, e.g. ServerBannerWidget's
 * config) doesn't have a full Asset — no mime_type/size/uploaded_by. This
 * is the minimum AssetPicker itself actually needs to render a preview.
 */
export type AssetPreview = Pick<Asset, 'thumbnail_url' | 'alt_text'>;

/**
 * One reusable component for every "pick or upload an image" spot in the
 * app (Server Banner's background today; Game cards/Portal hero/Themes
 * later) — a thumbnail + button that opens a browse-grid-or-upload modal.
 * Selecting or uploading both resolve to the same onChange(asset) call, so
 * a caller never needs to know which path produced the Asset it got back.
 */
export function AssetPicker({
  value,
  onChange,
  accept = DEFAULT_ACCEPT,
}: {
  value: AssetPreview | null;
  onChange: (asset: Asset | null) => void;
  accept?: string[];
}) {
  const [open, setOpen] = useState(false);

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
      {value ? (
        <img
          src={value.thumbnail_url}
          alt={value.alt_text ?? ''}
          style={{ width: 56, height: 56, objectFit: 'cover', borderRadius: 4, border: '1px solid var(--border, #ddd)' }}
        />
      ) : (
        <div
          style={{
            width: 56,
            height: 56,
            borderRadius: 4,
            border: '1px dashed var(--border, #ddd)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: '0.7rem',
            opacity: 0.6,
          }}
        >
          none
        </div>
      )}

      <button type="button" onClick={() => setOpen(true)}>
        {value ? 'Change image' : 'Choose image'}
      </button>
      {value && (
        <button type="button" onClick={() => onChange(null)}>
          Remove
        </button>
      )}

      {open && (
        <AssetPickerModal
          accept={accept}
          onClose={() => setOpen(false)}
          onSelect={(asset) => {
            onChange(asset);
            setOpen(false);
          }}
        />
      )}
    </div>
  );
}

function AssetPickerModal({
  accept,
  onClose,
  onSelect,
}: {
  accept: string[];
  onClose: () => void;
  onSelect: (asset: Asset) => void;
}) {
  const api = useApi();
  const queryClient = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);

  const { data: list, isLoading } = useQuery({
    queryKey: ['assets', page],
    queryFn: () => api.get<AssetList>(`/api/v1/assets?page=${page}`),
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => {
      const formData = new FormData();
      formData.append('file', file);
      return api.upload<Asset>('/api/v1/assets', formData);
    },
    onSuccess: (asset) => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ['assets'] });
      onSelect(asset);
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Upload failed.');
    },
  });

  function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (file) {
      uploadMutation.mutate(file);
    }
    event.target.value = '';
  }

  return (
    <Modal title="Choose an image" onClose={onClose}>
      <div style={{ marginBottom: 16, display: 'flex', alignItems: 'center', gap: 8 }}>
        <button type="button" onClick={() => fileInputRef.current?.click()} disabled={uploadMutation.isPending}>
          {uploadMutation.isPending ? 'Uploading…' : '+ Upload new'}
        </button>
        <input
          ref={fileInputRef}
          type="file"
          accept={accept.join(',')}
          onChange={handleFileChange}
          style={{ display: 'none' }}
        />
        {error && <span style={{ color: 'crimson', fontSize: '0.85rem' }}>{error}</span>}
      </div>

      {isLoading && <p>Loading…</p>}

      {list && list.items.length === 0 && <p>No assets uploaded yet.</p>}

      {list && list.items.length > 0 && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(80px, 1fr))', gap: 8 }}>
          {list.items.map((asset) => (
            <button
              key={asset.id}
              type="button"
              onClick={() => onSelect(asset)}
              title={asset.alt_text ?? undefined}
              style={{ padding: 0, border: '1px solid var(--border, #ddd)', borderRadius: 4, overflow: 'hidden', cursor: 'pointer' }}
            >
              <img src={asset.thumbnail_url} alt={asset.alt_text ?? ''} style={{ width: '100%', height: 80, objectFit: 'cover', display: 'block' }} />
            </button>
          ))}
        </div>
      )}

      {list && list.meta.last_page > 1 && (
        <div style={{ marginTop: 16, display: 'flex', justifyContent: 'center', gap: 8 }}>
          <button type="button" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Previous
          </button>
          <span style={{ fontSize: '0.85rem', opacity: 0.7 }}>
            Page {list.meta.current_page} of {list.meta.last_page}
          </span>
          <button type="button" disabled={page >= list.meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Next
          </button>
        </div>
      )}

      <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end' }}>
        <button type="button" onClick={onClose}>
          Cancel
        </button>
      </div>
    </Modal>
  );
}
