import { describe, expect, it } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
// Registers the real page-layout widget types (side effect) — every one
// today is validFor: ['server'], category: 'Server'.
import '../widgets/pageLayout';
import { AddPageLayoutWidgetModal } from './AddPageLayoutWidgetModal';

describe('AddPageLayoutWidgetModal', () => {
  it('lists every server-valid widget grouped under its category for a Server page', () => {
    render(<AddPageLayoutWidgetModal subjectType="server" onClose={() => {}} onAdd={() => {}} />);

    expect(screen.getByText('Server')).toBeInTheDocument();
    expect(screen.getByText('Banner')).toBeInTheDocument();
    expect(screen.getByText('Status')).toBeInTheDocument();
    expect(screen.getByText('Server Name')).toBeInTheDocument();
  });

  it('shows nothing to add on a Home page, since no widget type is validFor home today', () => {
    render(<AddPageLayoutWidgetModal subjectType="home" onClose={() => {}} onAdd={() => {}} />);

    expect(screen.getByText('No widget types are available on this page yet.')).toBeInTheDocument();
    expect(screen.queryByText('Banner')).not.toBeInTheDocument();
  });

  it('filters by search text against the label', () => {
    render(<AddPageLayoutWidgetModal subjectType="server" onClose={() => {}} onAdd={() => {}} />);

    fireEvent.change(screen.getByPlaceholderText('Search widgets…'), { target: { value: 'status' } });

    expect(screen.getByText('Status')).toBeInTheDocument();
    expect(screen.queryByText('Banner')).not.toBeInTheDocument();
  });

  it('calls onAdd with the widget type when a card is clicked', () => {
    let added: string | null = null;

    render(<AddPageLayoutWidgetModal subjectType="server" onClose={() => {}} onAdd={(type) => (added = type)} />);
    screen.getByText('Banner').click();

    expect(added).toBe('server-banner');
  });
});
