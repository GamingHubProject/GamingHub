import type { ProfileStat } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps, PageLayoutWidgetContext } from './registry';

export interface ProfileStatsWidgetConfig {
  /** Blank shows every stat the person has. A value narrows to one
   *  feature's numbers ('pelican', 'games.rust'), which is what makes this
   *  widget placeable more than once on the same profile. */
  source: string;
  title: string;
}

export const profileStatsWidgetDefaultConfig: ProfileStatsWidgetConfig = { source: '', title: 'Stats' };

/**
 * Reads user_stats. Nothing writes to that table yet — the services exist,
 * the integrations that will call them do not — so this ships rendering an
 * honest empty state rather than being held back until it has numbers.
 * That is deliberate: the schema is the thing features need in order to
 * start recording, and a profile that already has somewhere to show the
 * result is what makes the first one worth writing.
 */
function formatValue(value: number): string {
  // Stats are stored at four decimal places because some of them are
  // fractional hours; showing "47.0000 hours" would be worse than useless.
  return Number.isInteger(value) ? String(value) : String(Math.round(value * 100) / 100);
}

function label(stat: ProfileStat): string {
  const key = stat.key.replace(/[._-]+/g, ' ');
  const subject = stat.subject_type ? ` (${stat.subject_type} ${stat.subject_id})` : '';

  return key.charAt(0).toUpperCase() + key.slice(1) + subject;
}

export function ProfileStatsWidget({
  context,
  config,
}: {
  context: PageLayoutWidgetContext;
  config: ProfileStatsWidgetConfig;
}) {
  const profile = context.profile;
  if (!profile) return null;

  const stats = (profile.stats ?? []).filter((stat) => !config.source || stat.source === config.source);

  return (
    <div style={{ height: '100%', overflowY: 'auto' }}>
      {config.title && <strong style={{ display: 'block', marginBottom: 8 }}>{config.title}</strong>}
      {stats.length === 0 ? (
        <p style={{ color: 'var(--muted, #888)', margin: 0 }}>Nothing recorded yet.</p>
      ) : (
        <dl style={{ display: 'grid', gridTemplateColumns: 'auto auto', gap: '4px 16px', margin: 0, justifyContent: 'start' }}>
          {stats.map((stat) => (
            <div key={`${stat.source}:${stat.subject_type ?? ''}:${stat.subject_id ?? ''}:${stat.key}`} style={{ display: 'contents' }}>
              <dt style={{ color: 'var(--muted, #888)' }}>{label(stat)}</dt>
              <dd style={{ margin: 0, fontVariantNumeric: 'tabular-nums' }}>{formatValue(stat.value)}</dd>
            </div>
          ))}
        </dl>
      )}
    </div>
  );
}

export function ProfileStatsWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<ProfileStatsWidgetConfig>) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <label>
        Title
        <input
          type="text"
          value={config.title ?? ''}
          onChange={(event) => onChange({ ...config, title: event.target.value })}
          style={{ display: 'block', width: '100%', marginTop: 4 }}
        />
      </label>
      <label>
        Only this source
        <input
          type="text"
          value={config.source ?? ''}
          placeholder="Every source"
          onChange={(event) => onChange({ ...config, source: event.target.value })}
          style={{ display: 'block', width: '100%', marginTop: 4 }}
        />
      </label>
    </div>
  );
}
