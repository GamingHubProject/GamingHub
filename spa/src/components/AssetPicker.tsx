import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Modal } from './Modal';
import { useApi } from '../providers/ApiClientProvider';
import type { Asset, AssetFolder, AssetList, AssetTag } from '../api/types';

const DEFAULT_ACCEPT = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];

/**
 * A caller that only kept a snapshot (id + url, e.g. PictureWidget's
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
  // null = "root" (unfiled assets + top-level folders). Folder visibility
  // is enforced server-side (visibleTo) — this component never needs to
  // know why a folder is or isn't in the list, only render what came back,
  // so a non-admin picking an asset naturally only ever sees folders
  // they're allowed into.
  const [folderId, setFolderId] = useState<number | null>(null);
  const [tagId, setTagId] = useState<number | null>(null);

  const { data: folders } = useQuery({
    queryKey: ['asset-folders'],
    queryFn: () => api.get<AssetFolder[]>('/api/v1/asset-folders'),
  });

  const { data: tags } = useQuery({
    queryKey: ['asset-tags'],
    queryFn: () => api.get<AssetTag[]>('/api/v1/asset-tags'),
  });

  const { data: list, isLoading } = useQuery({
    queryKey: ['assets', { folderId, tagId, page }],
    queryFn: () => {
      const params = new URLSearchParams({ page: String(page), folder_id: String(folderId ?? 0) });
      if (tagId) params.set('tag_id', String(tagId));
      return api.get<AssetList>(`/api/v1/assets?${params}`);
    },
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => {
      const formData = new FormData();
      formData.append('file', file);
      if (folderId) formData.append('folder_id', String(folderId));
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

  const currentFolder = folders?.find((f) => f.id === folderId) ?? null;
  const subfolders = (folders ?? []).filter((f) => f.parent_id === folderId);

  function enterFolder(id: number | null) {
    setFolderId(id);
    setPage(1);
  }

  return (
    <Modal title="Choose an image" onClose={onClose}>
      <div style={{ marginBottom: 12, display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
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
        {tags && tags.length > 0 && (
          <select value={tagId ?? ''} onChange={(e) => setTagId(e.target.value ? Number(e.target.value) : null)}>
            <option value="">All tags</option>
            {tags.map((tag) => (
              <option key={tag.id} value={tag.id}>
                {tag.name}
              </option>
            ))}
          </select>
        )}
        {error && <span style={{ color: 'crimson', fontSize: '0.85rem' }}>{error}</span>}
      </div>

      <div style={{ marginBottom: 12, fontSize: '0.85rem', opacity: 0.8, display: 'flex', alignItems: 'center', gap: 4 }}>
        <button type="button" onClick={() => enterFolder(null)} disabled={folderId === null}>
          Library
        </button>
        {currentFolder && (
          <>
            <span>/</span>
            <span>{currentFolder.name}</span>
          </>
        )}
      </div>

      {subfolders.length > 0 && (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
          {subfolders.map((folder) => (
            <button key={folder.id} type="button" onClick={() => enterFolder(folder.id)}>
              📁 {folder.name}
            </button>
          ))}
        </div>
      )}

      {isLoading && <p>Loading…</p>}

      {list && list.items.length === 0 && <p>No assets here yet.</p>}

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
