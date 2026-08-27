/**
 * Shared "icon before name" rendering for Game/Server/ServerGroup cards —
 * one component/pattern instead of three near-identical copies. Renders
 * nothing when show is false or there's no url to show — a card with the
 * toggle on but nothing configured just looks like one with it off, never
 * a broken-image placeholder.
 *
 * Meant to sit alongside the title inside cardScale.ts's
 * cardHeaderRowStyle flex row, not stacked above it — flexShrink: 0 so a
 * long title never squeezes the icon down, no block-level display/margin
 * of its own (that's what stacked icon-then-name vertically before this
 * existed; the row's `gap` handles spacing instead).
 */
export function CardIcon({ url, show }: { url: string | null | undefined; show: boolean }) {
  if (!show || !url) return null;

  return (
    <img
      src={url}
      alt=""
      style={{
        width: 'clamp(16px, 20cqh, 32px)',
        height: 'clamp(16px, 20cqh, 32px)',
        objectFit: 'contain',
        flexShrink: 0,
      }}
    />
  );
}
