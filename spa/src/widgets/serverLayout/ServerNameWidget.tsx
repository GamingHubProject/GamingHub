import type { Server } from '../../api/types';

/**
 * Split out of what used to be ServerBannerWidget's built-in <h1> — the
 * banner is now purely a background layer (see its own docblock), and the
 * name is its own independent, layerable widget so an admin can place it
 * over the banner (or anywhere else) like any other widget.
 */
export function ServerNameWidget({ server }: { server: Server; config: Record<string, never> }) {
  return (
    <div style={{ padding: '8px 12px', height: '100%', display: 'flex', alignItems: 'center' }}>
      <h1 style={{ margin: 0, fontSize: '1.5rem' }}>{server.name}</h1>
    </div>
  );
}
