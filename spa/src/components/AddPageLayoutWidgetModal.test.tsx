import { describe, expect, it } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
// Registers the real page-layout widget types (side effect).
import '../widgets/pageLayout';
import { AddPageLayoutWidgetModal } from './AddPageLayoutWidgetModal';

describe('AddPageLayoutWidgetModal', () => {
  it('lists every server-valid widget grouped under its category for a Server page', () => {
    render(<AddPageLayoutWidgetModal subjectType="server" onClose={() => {}} onAdd={() => {}} />);

    expect(screen.getByText('Server')).toBeInTheDocument();
    expect(screen.getByText('Picture')).toBeInTheDocument();
    expect(screen.getByText('Status')).toBeInTheDocument();
    expect(screen.getByText('Server Name')).toBeInTheDocument();
    // Cross-linking widgets, validFor includes 'server' too.
    expect(screen.getByText('Game Card')).toBeInTheDocument();
  });

  it('offers every widget except the genuinely Server-only ones on a Home page', () => {
    render(<AddPageLayoutWidgetModal subjectType="home" onClose={() => {}} onAdd={() => {}} />);

    expect(screen.getByText('Game Card')).toBeInTheDocument();
    expect(screen.getByText('Server Card')).toBeInTheDocument();
    expect(screen.getByText('Server Group Card')).toBeInTheDocument();
    // Picture and Server Name are now valid everywhere, not just 'server'.
    expect(screen.getByText('Picture')).toBeInTheDocument();
    expect(screen.getByText('Server Name')).toBeInTheDocument();
    // Status/Metrics/Player Count/Allocations read live data off
    // context.server, which only a Server page's context ever has.
    expect(screen.queryByText('Status')).not.toBeInTheDocument();
    expect(screen.queryByText('Metrics')).not.toBeInTheDocument();
    expect(screen.queryByText('Player Count')).not.toBeInTheDocument();
    expect(screen.queryByText('Allocations')).not.toBeInTheDocument();
    expect(screen.queryByText('No widget types are available on this page yet.')).not.toBeInTheDocument();
  });

  it('offers a profile only the widgets built for one, plus Text', () => {
    render(<AddPageLayoutWidgetModal subjectType="user_profile" onClose={() => {}} onAdd={() => {}} />);

    expect(screen.getByText('Avatar')).toBeInTheDocument();
    expect(screen.getByText('Bio')).toBeInTheDocument();
    expect(screen.getByText('Stats')).toBeInTheDocument();
    expect(screen.getByText('Text')).toBeInTheDocument();
    // Everything built to read a Server or a Game has no subject here.
    expect(screen.queryByText('Picture')).not.toBeInTheDocument();
    expect(screen.queryByText('Game Card')).not.toBeInTheDocument();
    expect(screen.queryByText('Server Card')).not.toBeInTheDocument();
  });

  it("narrows the profile list to what the site's admin has permitted", () => {
    render(
      <AddPageLayoutWidgetModal
        subjectType="user_profile"
        allowedTypes={['profile-avatar', 'profile-bio']}
        onClose={() => {}}
        onAdd={() => {}}
      />
    );

    expect(screen.getByText('Avatar')).toBeInTheDocument();
    expect(screen.getByText('Bio')).toBeInTheDocument();
    expect(screen.queryByText('Stats')).not.toBeInTheDocument();
    expect(screen.queryByText('Text')).not.toBeInTheDocument();
  });

  /**
   * The two questions are not the same one: an admin's list is policy, and
   * policy can never grant a capability. Enabling a Server widget on
   * profiles has to stay impossible, because that placement is exactly the
   * crash the containment guard exists to catch.
   */
  it("cannot be made to offer a widget the page can't render, however the allowlist is set", () => {
    render(
      <AddPageLayoutWidgetModal
        subjectType="user_profile"
        allowedTypes={['server-status', 'game-card', 'profile-bio']}
        onClose={() => {}}
        onAdd={() => {}}
      />
    );

    expect(screen.getByText('Bio')).toBeInTheDocument();
    expect(screen.queryByText('Status')).not.toBeInTheDocument();
    expect(screen.queryByText('Game Card')).not.toBeInTheDocument();
  });

  it('leaves every other page type untouched when no allowlist is given', () => {
    render(<AddPageLayoutWidgetModal subjectType="home" allowedTypes={undefined} onClose={() => {}} onAdd={() => {}} />);

    expect(screen.getByText('Game Card')).toBeInTheDocument();
    expect(screen.getByText('Picture')).toBeInTheDocument();
  });

  it('shows nothing to add on a page type with no valid widgets at all', () => {
    render(<AddPageLayoutWidgetModal subjectType={'unknown-page-type' as any} onClose={() => {}} onAdd={() => {}} />);

    expect(screen.getByText('No widget types are available on this page yet.')).toBeInTheDocument();
  });

  it('filters by search text against the label', () => {
    render(<AddPageLayoutWidgetModal subjectType="server" onClose={() => {}} onAdd={() => {}} />);

    fireEvent.change(screen.getByPlaceholderText('Search widgets…'), { target: { value: 'status' } });

    expect(screen.getByText('Status')).toBeInTheDocument();
    expect(screen.queryByText('Picture')).not.toBeInTheDocument();
  });

  it('calls onAdd with the widget type when a card is clicked', () => {
    let added: string | null = null;

    render(<AddPageLayoutWidgetModal subjectType="server" onClose={() => {}} onAdd={(type) => (added = type)} />);
    screen.getByText('Picture').click();

    expect(added).toBe('picture');
  });
});
