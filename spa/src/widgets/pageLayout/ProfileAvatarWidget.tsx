import { Listbox } from '../../components/Listbox';
import type { PageLayoutWidgetConfigFormProps, PageLayoutWidgetContext } from './registry';

export type AvatarShape = 'circle' | 'square';

export interface ProfileAvatarWidgetConfig {
  shape: AvatarShape;
  show_name: boolean;
}

export const profileAvatarWidgetDefaultConfig: ProfileAvatarWidgetConfig = {
  shape: 'circle',
  show_name: true,
};

/**
 * The person's picture, sized to whatever box the grid gives it — a
 * profile is the one page where the subject's own face is the content, so
 * this scales with the widget rather than picking a fixed pixel size an
 * admin would then have to fight.
 *
 * Renders a monogram when there is no picture: an empty box on the
 * default layout of every brand-new account would read as broken.
 */
export function ProfileAvatarWidget({
  context,
  config,
}: {
  context: PageLayoutWidgetContext;
  config: ProfileAvatarWidgetConfig;
}) {
  const profile = context.profile;
  if (!profile) return null;

  const radius = (config.shape ?? 'circle') === 'circle' ? '50%' : '8px';

  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 8, height: '100%', minHeight: 0 }}>
      <div style={{ flex: '1 1 auto', minHeight: 0, aspectRatio: '1 / 1', maxWidth: '100%' }}>
        {profile.avatar_url ? (
          <img
            src={profile.avatar_url}
            alt={profile.display_name}
            style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: radius, display: 'block' }}
          />
        ) : (
          <div
            aria-hidden
            style={{
              width: '100%',
              height: '100%',
              borderRadius: radius,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              background: 'var(--surface-sunk, rgba(127,127,127,0.15))',
              border: '1px solid var(--border, #ddd)',
              fontSize: 'clamp(1rem, 40cqh, 4rem)',
            }}
          >
            {profile.display_name.slice(0, 1).toUpperCase()}
          </div>
        )}
      </div>
      {(config.show_name ?? true) && (
        <strong style={{ textAlign: 'center', overflowWrap: 'anywhere' }}>{profile.display_name}</strong>
      )}
    </div>
  );
}

export function ProfileAvatarWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<ProfileAvatarWidgetConfig>) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <label>
        Shape
        <div style={{ marginTop: 4 }}>
          <Listbox<AvatarShape>
            label="Shape"
            value={config.shape ?? 'circle'}
            options={[
              { value: 'circle', label: 'Circle' },
              { value: 'square', label: 'Rounded square' },
            ]}
            onChange={(shape) => onChange({ ...config, shape })}
          />
        </div>
      </label>
      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <input
          type="checkbox"
          checked={config.show_name ?? true}
          onChange={(event) => onChange({ ...config, show_name: event.target.checked })}
        />
        Show the name underneath
      </label>
    </div>
  );
}
