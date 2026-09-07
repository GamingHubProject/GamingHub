import { RichTextContent } from '../../components/RichText';
import type { PageLayoutWidgetContext } from './registry';

/**
 * What the person wrote about themselves. No config: a bio is edited on
 * the profile editor, not per placement, and a widget that could override
 * it would be a Text widget with extra steps.
 */
export function ProfileBioWidget({ context }: { context: PageLayoutWidgetContext }) {
  const profile = context.profile;
  if (!profile) return null;

  if (!profile.bio) {
    return (
      <p style={{ color: 'var(--muted, #888)', margin: 0 }}>
        {profile.can_edit ? 'Nothing here yet — edit your profile to add a bio.' : 'This person has not written a bio yet.'}
      </p>
    );
  }

  return <RichTextContent html={profile.bio} />;
}
