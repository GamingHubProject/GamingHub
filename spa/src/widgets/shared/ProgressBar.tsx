export function ProgressBar({ label, percent }: { label: string; percent: number }) {
  const clamped = Math.max(0, Math.min(100, percent));
  const color = clamped >= 90 ? '#dc2626' : clamped >= 70 ? '#ca8a04' : '#16a34a';

  return (
    <div style={{ marginTop: 6 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', opacity: 0.7 }}>
        <span>{label}</span>
        <span>{Math.round(clamped)}%</span>
      </div>
      <div style={{ height: 6, borderRadius: 3, background: 'var(--border, #ddd)', overflow: 'hidden' }}>
        <div style={{ height: '100%', width: `${clamped}%`, background: color, borderRadius: 3 }} />
      </div>
    </div>
  );
}
