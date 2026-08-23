import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DashboardWidgetContainer } from './DashboardWidgetContainer';
import { registerWidget } from '../widgets/registry';
import type { DashboardWidget } from '../api/types';

describe('DashboardWidgetContainer', () => {
  it('renders a graceful fallback for an unregistered widget type', () => {
    const widget: DashboardWidget = { id: 1, dashboard_page_id: 1, widget_type: 'some-future-widget', config: null, order: 0, position_x: 0, position_y: 0, width: 6, height: 4 };

    render(<DashboardWidgetContainer widget={widget} onEdit={() => {}} />);

    expect(screen.getByText(/unsupported widget type: some-future-widget/i)).toBeInTheDocument();
  });

  it('renders a registered widget type via its component', () => {
    registerWidget({
      type: 'test-widget',
      label: 'Test Widget',
      component: ({ config }) => <span>rendered with {JSON.stringify(config)}</span>,
      defaultConfig: { foo: 'bar' },
    });

    const widget: DashboardWidget = { id: 2, dashboard_page_id: 1, widget_type: 'test-widget', config: { foo: 'baz' }, order: 0, position_x: 0, position_y: 0, width: 6, height: 4 };

    render(<DashboardWidgetContainer widget={widget} onEdit={() => {}} />);

    expect(screen.getByText('rendered with {"foo":"baz"}')).toBeInTheDocument();
  });

  it('calls onEdit when the settings button is clicked', async () => {
    const widget: DashboardWidget = { id: 3, dashboard_page_id: 1, widget_type: 'test-widget', config: { foo: 'baz' }, order: 0, position_x: 0, position_y: 0, width: 6, height: 4 };
    let edited = false;

    render(<DashboardWidgetContainer widget={widget} onEdit={() => (edited = true)} />);
    screen.getByLabelText('Widget settings').click();

    expect(edited).toBe(true);
  });

  it('excludes the settings button from the drag handle, so a click never starts a drag', () => {
    // Regression guard: the settings button lives inside .widget-drag-handle
    // for layout purposes, but must carry .widget-no-drag — that's the
    // class Dashboard.tsx's grid passes as draggableCancel, which is what
    // stops react-grid-layout's own mousedown listener from winning the
    // race against this button's onClick.
    const widget: DashboardWidget = { id: 4, dashboard_page_id: 1, widget_type: 'test-widget', config: { foo: 'baz' }, order: 0, position_x: 0, position_y: 0, width: 6, height: 4 };

    render(<DashboardWidgetContainer widget={widget} onEdit={() => {}} />);

    expect(screen.getByLabelText('Widget settings')).toHaveClass('widget-no-drag');
  });
});
