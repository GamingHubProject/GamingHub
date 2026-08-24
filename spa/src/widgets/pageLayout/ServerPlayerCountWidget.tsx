import type { PageLayoutWidgetContext } from './registry';

// Separate from ServerMetricsWidget on purpose — not every connector
// reports players the same way (or at all), so this can be omitted from a
// layout independently of CPU/RAM. validFor: ['server'] guarantees
// context.server is set — see registry.ts.
export function ServerPlayerCountWidget({ context }: { context: PageLayoutWidgetContext }) {
  const server = context.server!;

  if (server.max_players === null) {
    return (
      <div style={{ padding: 12 }}>
        <p style={{ margin: 0, fontSize: '0.85rem', opacity: 0.7 }}>Player count not reported.</p>
      </div>
    );
  }

  return (
    <div style={{ padding: 12, textAlign: 'center' }}>
      <div style={{ fontSize: '1.5rem', fontWeight: 700 }}>
        {server.current_players ?? 0}/{server.max_players}
      </div>
      <div style={{ fontSize: '0.75rem', opacity: 0.7 }}>Players</div>
    </div>
  );
}
