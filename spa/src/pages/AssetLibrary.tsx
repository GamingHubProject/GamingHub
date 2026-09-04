import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import type { Asset, AssetFolder, AssetFolderVisibility, AssetList, AssetTag } from '../api/types';
import { Listbox } from '../components/Listbox';

const box: React.CSSProperties = { border: '1px solid var(--border, #ddd)', borderRadius: 'var(--radius, 8px)', padding: 12 };

/**
 * The admin-only Asset Library management view — browse/organize every
 * uploaded asset by folder, tag, rename, move, delete. Distinct from
 * AssetPicker (a compact "pick or upload one image" widget embedded in
 * other forms): this page is the full CRUD surface for folders/tags/assets
 * themselves, so it duplicates some browsing UI rather than sharing a
 * component with the picker — the two have very different jobs (select one
 * vs. manage everything).
 */
export function AssetLibrary() {
  const api = useApi();
  const queryClient = useQueryClient();
  const { user, isLoading: authLoading } = useAuth();
  const isAdmin = user?.is_admin ?? false;

  const [folderId, setFolderId] = useState<number | null>(null);
  const [tagId, setTagId] = useState<number | null>(null);
  const [newFolderOpen, setNewFolderOpen] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const { data: folders } = useQuery({
    queryKey: ['asset-folders'],
    queryFn: () => api.get<AssetFolder[]>('/api/v1/asset-folders'),
  });

  const { data: tags } = useQuery({
    queryKey: ['asset-tags'],
    queryFn: () => api.get<AssetTag[]>('/api/v1/asset-tags'),
  });

  const { data: list, isLoading: assetsLoading } = useQuery({
    queryKey: ['assets', { folderId, tagId }],
    queryFn: () => {
      const params = new URLSearchParams({ folder_id: String(folderId ?? 0), per_page: '100' });
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
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['assets'] }),
  });

  const createFolderMutation = useMutation({
    mutationFn: (data: { name: string; visibility: AssetFolderVisibility }) =>
      api.post<AssetFolder>('/api/v1/asset-folders', { ...data, parent_id: folderId }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['asset-folders'] });
      setNewFolderOpen(false);
    },
  });

  const deleteFolderMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/v1/asset-folders/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['asset-folders'] });
      setFolderId(currentFolder?.parent_id ?? null);
    },
  });

  const updateAssetMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Record<string, unknown> }) => api.patch<Asset>(`/api/v1/assets/${id}`, data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['assets'] }),
  });

  const deleteAssetMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/v1/assets/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['assets'] }),
  });

  const createTagMutation = useMutation({
    mutationFn: (name: string) => api.post<AssetTag>('/api/v1/asset-tags', { name }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['asset-tags'] }),
  });

  function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (file) uploadMutation.mutate(file);
    event.target.value = '';
  }

  function toggleTag(asset: Asset, tag: AssetTag) {
    const has = asset.tags.some((t) => t.id === tag.id);
    const nextIds = has ? asset.tags.filter((t) => t.id !== tag.id).map((t) => t.id) : [...asset.tags.map((t) => t.id), tag.id];
    updateAssetMutation.mutate({ id: asset.id, data: { tag_ids: nextIds } });
  }

  if (authLoading) return <p>Loading…</p>;
  if (!isAdmin) return <p>You don't have access to the Asset Library.</p>;

  const currentFolder = folders?.find((f) => f.id === folderId) ?? null;
  const subfolders = (folders ?? []).filter((f) => f.parent_id === folderId);

  return (
    <div>
      <h1>Asset Library</h1>
      <div style={{ display: 'grid', gridTemplateColumns: '220px 1fr', gap: 16, alignItems: 'start' }}>
        <div style={box}>
          <h2 style={{ fontSize: '0.95rem', marginTop: 0 }}>Folders</h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4, marginBottom: 12 }}>
            <button onClick={() => setFolderId(null)} disabled={folderId === null}>
              📁 Library root
            </button>
            {subfolders.map((folder) => (
              <button key={folder.id} onClick={() => setFolderId(folder.id)}>
                📁 {folder.name}
                {folder.visibility !== 'public' && (
                  <span style={{ opacity: 0.6, fontSize: '0.75rem' }}> ({folder.visibility})</span>
                )}
              </button>
            ))}
          </div>

          {currentFolder && (
            <button
              onClick={() => {
                if (confirm(`Delete folder "${currentFolder.name}"? It must be empty.`)) {
                  deleteFolderMutation.mutate(currentFolder.id);
                }
              }}
            >
              Delete this folder
            </button>
          )}

          {newFolderOpen ? (
            <NewFolderForm
              onCancel={() => setNewFolderOpen(false)}
              onCreate={(data) => createFolderMutation.mutate(data)}
              pending={createFolderMutation.isPending}
              error={createFolderMutation.isError ? 'Could not create folder.' : null}
            />
          ) : (
            <button onClick={() => setNewFolderOpen(true)}>+ New folder</button>
          )}

          <h2 style={{ fontSize: '0.95rem' }}>Tags</h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4, marginBottom: 8 }}>
            <button onClick={() => setTagId(null)} disabled={tagId === null}>
              All tags
            </button>
            {tags?.map((tag) => (
              <button key={tag.id} onClick={() => setTagId(tag.id)} disabled={tagId === tag.id}>
                #{tag.name}
              </button>
            ))}
          </div>
          <NewTagForm onCreate={(name) => createTagMutation.mutate(name)} pending={createTagMutation.isPending} />
        </div>

        <div style={box}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
            <span style={{ fontSize: '0.85rem', opacity: 0.8 }}>
              {currentFolder ? currentFolder.path : '/ (unfiled)'}
            </span>
            <div>
              <button onClick={() => fileInputRef.current?.click()} disabled={uploadMutation.isPending}>
                {uploadMutation.isPending ? 'Uploading…' : '+ Upload'}
              </button>
              <input ref={fileInputRef} type="file" onChange={handleFileChange} style={{ display: 'none' }} />
            </div>
          </div>

          {assetsLoading && <p>Loading…</p>}
          {list && list.items.length === 0 && <p>No assets here yet.</p>}

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: 12 }}>
            {list?.items.map((asset) => (
              <AssetCard
                key={asset.id}
                asset={asset}
                folders={folders ?? []}
                tags={tags ?? []}
                onRename={(alt_text) => updateAssetMutation.mutate({ id: asset.id, data: { alt_text } })}
                onMove={(newFolderId) => updateAssetMutation.mutate({ id: asset.id, data: { folder_id: newFolderId } })}
                onToggleTag={(tag) => toggleTag(asset, tag)}
                onDelete={() => {
                  if (confirm('Delete this asset? This cannot be undone.')) deleteAssetMutation.mutate(asset.id);
                }}
              />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

function AssetCard({
  asset,
  folders,
  tags,
  onRename,
  onMove,
  onToggleTag,
  onDelete,
}: {
  asset: Asset;
  folders: AssetFolder[];
  tags: AssetTag[];
  onRename: (altText: string) => void;
  onMove: (folderId: number | null) => void;
  onToggleTag: (tag: AssetTag) => void;
  onDelete: () => void;
}) {
  const [altText, setAltText] = useState(asset.alt_text ?? '');

  return (
    <div style={{ ...box, padding: 8, display: 'flex', flexDirection: 'column', gap: 6 }}>
      <img src={asset.thumbnail_url} alt={asset.alt_text ?? ''} style={{ width: '100%', height: 100, objectFit: 'cover', borderRadius: 4 }} />
      <input
        value={altText}
        onChange={(e) => setAltText(e.target.value)}
        onBlur={() => {
          if (altText !== (asset.alt_text ?? '')) onRename(altText);
        }}
        placeholder="Display name / alt text"
        style={{ fontSize: '0.8rem' }}
      />
      <Listbox
        label="Folder"
        value={asset.folder_id ? String(asset.folder_id) : ''}
        options={[
          { value: '', label: '(unfiled)' },
          ...folders.map((folder) => ({ value: String(folder.id), label: folder.path })),
        ]}
        onChange={(next) => onMove(next ? Number(next) : null)}
      />
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
        {tags.map((tag) => {
          const active = asset.tags.some((t) => t.id === tag.id);
          return (
            <button
              key={tag.id}
              onClick={() => onToggleTag(tag)}
              style={{ fontSize: '0.7rem', opacity: active ? 1 : 0.5, padding: '2px 6px' }}
            >
              #{tag.name}
            </button>
          );
        })}
      </div>
      <button onClick={onDelete} style={{ fontSize: '0.75rem' }}>
        Delete
      </button>
    </div>
  );
}

function NewFolderForm({
  onCreate,
  onCancel,
  pending,
  error,
}: {
  onCreate: (data: { name: string; visibility: AssetFolderVisibility }) => void;
  onCancel: () => void;
  pending: boolean;
  error: string | null;
}) {
  const [name, setName] = useState('');
  const [visibility, setVisibility] = useState<AssetFolderVisibility>('public');

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        if (name.trim()) onCreate({ name: name.trim(), visibility });
      }}
      style={{ display: 'flex', flexDirection: 'column', gap: 4, marginTop: 8, marginBottom: 12 }}
    >
      <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Folder name" autoFocus />
      <Listbox<AssetFolderVisibility>
        label="Visibility"
        value={visibility}
        options={[
          { value: 'public', label: 'Public' },
          { value: 'admin_only', label: 'Admin only' },
        ]}
        onChange={setVisibility}
      />
      {error && <span style={{ color: 'crimson', fontSize: '0.75rem' }}>{error}</span>}
      <div style={{ display: 'flex', gap: 4 }}>
        <button type="submit" disabled={pending || !name.trim()}>
          Create
        </button>
        <button type="button" onClick={onCancel}>
          Cancel
        </button>
      </div>
    </form>
  );
}

function NewTagForm({ onCreate, pending }: { onCreate: (name: string) => void; pending: boolean }) {
  const [name, setName] = useState('');

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        if (name.trim()) {
          onCreate(name.trim());
          setName('');
        }
      }}
      style={{ display: 'flex', gap: 4 }}
    >
      <input value={name} onChange={(e) => setName(e.target.value)} placeholder="New tag" style={{ fontSize: '0.8rem' }} />
      <button type="submit" disabled={pending || !name.trim()}>
        Add
      </button>
    </form>
  );
}
