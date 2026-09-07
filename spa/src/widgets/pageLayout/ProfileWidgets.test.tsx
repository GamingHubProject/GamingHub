import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ProfileAvatarWidget, profileAvatarWidgetDefaultConfig } from './ProfileAvatarWidget';
import { ProfileBioWidget } from './ProfileBioWidget';
import { ProfileStatsWidget, profileStatsWidgetDefaultConfig } from './ProfileStatsWidget';
import { TextWidget } from './TextWidget';
import type { PageLayoutWidgetContext } from './registry';
import type { Profile } from '../../api/types';

const profile: Profile = {
  id: 7,
  display_name: 'Rose',
  avatar_url: null,
  bio: null,
  profile_public: true,
  can_edit: false,
  allowed_widget_types: [],
  stats: [],
  achievements: [],
};

function contextFor(overrides: Partial<Profile> = {}): PageLayoutWidgetContext {
  return { subjectType: 'user_profile', profile: { ...profile, ...overrides } };
}

describe('ProfileAvatarWidget', () => {
  it('shows the picture when there is one', () => {
    render(
      <ProfileAvatarWidget
        context={contextFor({ avatar_url: 'https://example.test/me.png' })}
        config={profileAvatarWidgetDefaultConfig}
      />
    );

    expect(screen.getByAltText('Rose')).toHaveAttribute('src', 'https://example.test/me.png');
  });

  /** Every brand-new account starts here, and an empty box on the default
   *  layout would read as a broken page rather than an empty one. */
  it('falls back to a monogram when there is no picture', () => {
    render(<ProfileAvatarWidget context={contextFor()} config={profileAvatarWidgetDefaultConfig} />);

    expect(screen.getByText('R')).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('can be told not to repeat the name', () => {
    const { rerender } = render(
      <ProfileAvatarWidget context={contextFor()} config={{ shape: 'circle', show_name: true }} />
    );
    expect(screen.getAllByText('Rose').length).toBeGreaterThan(0);

    rerender(<ProfileAvatarWidget context={contextFor()} config={{ shape: 'circle', show_name: false }} />);
    expect(screen.queryByText('Rose')).not.toBeInTheDocument();
  });
});

describe('ProfileBioWidget', () => {
  it('renders the stored markdown', () => {
    render(<ProfileBioWidget context={contextFor({ bio: 'Hello **there**' })} />);

    expect(screen.getByText('there')).toBeInTheDocument();
  });

  /** Two different empty states: the owner gets told what to do about it,
   *  a visitor gets told there is nothing to see. */
  it('nudges the owner and simply informs everybody else', () => {
    const { rerender } = render(<ProfileBioWidget context={contextFor({ can_edit: true })} />);
    expect(screen.getByText(/edit your profile/i)).toBeInTheDocument();

    rerender(<ProfileBioWidget context={contextFor({ can_edit: false })} />);
    expect(screen.getByText(/has not written a bio/i)).toBeInTheDocument();
  });
});

describe('ProfileStatsWidget', () => {
  it('says so honestly when nothing has been recorded', () => {
    render(<ProfileStatsWidget context={contextFor()} config={profileStatsWidgetDefaultConfig} />);

    expect(screen.getByText('Nothing recorded yet.')).toBeInTheDocument();
  });

  it('lists stats with their subject and trims pointless decimals', () => {
    render(
      <ProfileStatsWidget
        context={contextFor({
          stats: [
            { source: 'pelican', subject_type: 'server', subject_id: 5, key: 'hours', value: 47, metadata: null },
            { source: 'core', subject_type: null, subject_id: null, key: 'sessions', value: 12.5, metadata: null },
          ],
        })}
        config={profileStatsWidgetDefaultConfig}
      />
    );

    expect(screen.getByText('Hours (server 5)')).toBeInTheDocument();
    expect(screen.getByText('47')).toBeInTheDocument();
    expect(screen.getByText('Sessions')).toBeInTheDocument();
    expect(screen.getByText('12.5')).toBeInTheDocument();
  });

  it('can be narrowed to one source, which is what lets it be placed twice', () => {
    render(
      <ProfileStatsWidget
        context={contextFor({
          stats: [
            { source: 'pelican', subject_type: null, subject_id: null, key: 'hours', value: 47, metadata: null },
            { source: 'core', subject_type: null, subject_id: null, key: 'sessions', value: 12, metadata: null },
          ],
        })}
        config={{ source: 'pelican', title: 'Play time' }}
      />
    );

    expect(screen.getByText('Play time')).toBeInTheDocument();
    expect(screen.getByText('Hours')).toBeInTheDocument();
    expect(screen.queryByText('Sessions')).not.toBeInTheDocument();
  });
});

describe('TextWidget', () => {
  it('renders its markdown', () => {
    render(<TextWidget config={{ markdown: 'Welcome to the server' }} />);

    expect(screen.getByText('Welcome to the server')).toBeInTheDocument();
  });

  it('tells an admin how to fill it rather than rendering nothing', () => {
    render(<TextWidget config={{ markdown: '' }} />);

    expect(screen.getByText(/open the settings/i)).toBeInTheDocument();
  });
});
