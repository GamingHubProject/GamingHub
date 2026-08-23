// Mirrors the label/color groupings in App\Capabilities\ServerStatusBadge
// (PHP, used by Filament) — kept as three simple buckets here since the
// widget only needs a color signal, not per-state labels. Shared between
// the dashboard's ServerStatusWidget and the server layout's own status
// widget so both ever have exactly one place to update.
const STATUS_COLOR: Record<string, string> = {
  running: '#16a34a',
  online: '#16a34a',
  offline: '#dc2626',
  exited: '#dc2626',
  dead: '#dc2626',
  missing: '#dc2626',
  suspended: '#dc2626',
  install_failed: '#dc2626',
  reinstall_failed: '#dc2626',
  starting: '#ca8a04',
  stopping: '#ca8a04',
  restarting: '#ca8a04',
  paused: '#ca8a04',
  removing: '#ca8a04',
  installing: '#ca8a04',
  restoring_backup: '#ca8a04',
  transferring: '#ca8a04',
  node_maintenance: '#ca8a04',
  maintenance: '#ca8a04',
  created: '#6b7280',
};

export function statusColor(status: string): string {
  return STATUS_COLOR[status] ?? '#6b7280';
}

export function statusLabel(status: string): string {
  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

export function StatusBadge({ status }: { status: string }) {
  const color = statusColor(status);
  return (
    <span
      style={{
        display: 'inline-block',
        padding: '2px 8px',
        borderRadius: 999,
        fontSize: '0.75rem',
        fontWeight: 600,
        color: '#fff',
        background: color,
      }}
    >
      {statusLabel(status)}
    </span>
  );
}
