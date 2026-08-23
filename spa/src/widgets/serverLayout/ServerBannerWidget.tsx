import type { Server } from '../../api/types';

// Plain for now — artwork/background comes once the Asset Library exists.
// Isolating the banner as its own widget (rather than folding the title
// into the status card) means that future work only ever touches this
// file, not server-status/server-metrics/etc.
export function ServerBannerWidget({ server }: { server: Server }) {
  return (
    <div style={{ padding: '16px 20px', height: '100%', display: 'flex', alignItems: 'center' }}>
      <h1 style={{ margin: 0, fontSize: '1.5rem' }}>{server.name}</h1>
    </div>
  );
}
