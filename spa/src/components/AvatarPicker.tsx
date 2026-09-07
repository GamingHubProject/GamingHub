import { useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import type { Asset, AssetFolder } from '../api/types';

/**
 * Choosing your own picture — deliberately not AssetPicker.
 *
 * AssetPicker is a library browser: folders, tags, paging, everything an
 * admin needs to find an asset among thousands. None of that belongs in
 * front of someone setting their avatar, and letting an ordinary visitor
 * browse the asset library from their own settings page would be a
 * surprising amount of the site to hand them. So this does exactly one
 * thing: upload a picture of yourself, or clear the one you have.
 *
 * The upload goes to the shared public Avatars folder with the uploader
 * recorded as its owner — the exact shape AssetController allows a
 * non-admin to write (see isOwnAvatarUpload). The folder id is fetched
 * rather than assumed, because that endpoint is also what creates the
 * folder the first time anybody needs it.
 */
export function AvatarPicker({
  userId,
  currentUrl,
  onChange,
}: {
  userId: number;
  currentUrl: string | null;
  onChange: (assetId: number | null, url: string | null) => void;
}) {
  const api = useApi();
  const fileInput = useRef<HTMLInputElement>(null);
  const [error, setError] = useState<string | null>(null);

  const upload = useMutation({
    mutationFn: async (file: File) => {
      const folder = await api.get<AssetFolder>('/api/v1/asset-folders/avatars');

      const formData = new FormData();
      formData.append('file', file);
      formData.append('folder_id', String(folder.id));
      formData.append('owner_type', 'User');
      formData.append('owner_id', String(userId));

      return api.upload<Asset>('/api/v1/assets', formData);
    },
    onSuccess: (asset) => {
      setError(null);
      onChange(asset.id, asset.url);
    },
    onError: () => setError('That picture could not be uploaded. PNG, JPEG or WebP, up to 1 MB.'),
  });

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
      {currentUrl ? (
        <img
          src={currentUrl}
          alt="Your avatar"
          style={{ width: 72, height: 72, borderRadius: '50%', objectFit: 'cover', border: '1px solid var(--border, #ddd)' }}
        />
      ) : (
        <div
          style={{
            width: 72,
            height: 72,
            borderRadius: '50%',
            border: '1px dashed var(--border, #ddd)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: 'var(--muted, #888)',
            fontSize: 12,
          }}
        >
          None
        </div>
      )}

      <input
        ref={fileInput}
        type="file"
        accept="image/png,image/jpeg,image/webp"
        style={{ display: 'none' }}
        onChange={(event) => {
          const file = event.target.files?.[0];
          if (file) upload.mutate(file);
          event.target.value = '';
        }}
      />

      <button type="button" onClick={() => fileInput.current?.click()} disabled={upload.isPending}>
        {upload.isPending ? 'Uploading…' : currentUrl ? 'Change picture' : 'Upload a picture'}
      </button>

      {currentUrl && (
        <button type="button" onClick={() => onChange(null, null)}>
          Remove
        </button>
      )}

      {error && <span style={{ color: 'var(--danger, #b3261e)' }}>{error}</span>}
    </div>
  );
}
