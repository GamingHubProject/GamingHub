import type { PageLayoutWidgetContext } from './registry';

// validFor: ['server'] guarantees context.server is set — see registry.ts.
export function ServerAllocationsWidget({ context }: { context: PageLayoutWidgetContext }) {
  const server = context.server!;

  if (server.allocations.length === 0) {
    return (
      <div style={{ padding: 12 }}>
        <p style={{ margin: 0, fontSize: '0.85rem', opacity: 0.7 }}>No allocations.</p>
      </div>
    );
  }

  return (
    <ul style={{ margin: 0, padding: '12px 12px 12px 28px' }}>
      {server.allocations.map((allocation) => (
        <li key={allocation.id} style={{ fontSize: '0.9rem' }}>
          {allocation.ip}:{allocation.port} {allocation.is_default && '(default)'}
        </li>
      ))}
    </ul>
  );
}
