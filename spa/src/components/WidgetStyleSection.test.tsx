import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
// Registers the real page-layout widget types (side effect) — the
// selfScaling check reads the real registry, not a mock.
import '../widgets/pageLayout';
import { WidgetStyleSection } from './WidgetStyleSection';

describe('WidgetStyleSection', () => {
  it('starts with all three groups un-overridden (synced to global) for a fresh widget', () => {
    render(<WidgetStyleSection widgetType="picture" config={{}} onChange={() => {}} />);

    expect(screen.getByLabelText(/Override border/)).not.toBeChecked();
    expect(screen.getByLabelText(/Override text style/)).not.toBeChecked();
    expect(screen.getByLabelText(/Override background/)).not.toBeChecked();
    // Sub-fields only appear once a group is overridden.
    expect(screen.queryByLabelText(/Thickness/)).not.toBeInTheDocument();
  });

  it('reveals border sub-fields and writes border_enabled/border_thickness into config.style when toggled on', () => {
    let latest: Record<string, unknown> = {};
    render(<WidgetStyleSection widgetType="picture" config={{}} onChange={(next) => (latest = next)} />);

    screen.getByLabelText(/Override border/).click();

    expect((latest.style as any).border_enabled).toBe(true);
    expect((latest.style as any).border_thickness).toBe(1);
  });

  it('clears border_enabled/border_thickness back to null when toggled off', () => {
    let latest: Record<string, unknown> = { style: { border_enabled: true, border_thickness: 3 } };
    const { rerender } = render(<WidgetStyleSection widgetType="picture" config={latest} onChange={(next) => (latest = next)} />);

    screen.getByLabelText(/Override border/).click();
    rerender(<WidgetStyleSection widgetType="picture" config={latest} onChange={(next) => (latest = next)} />);

    expect((latest.style as any).border_enabled).toBeNull();
    expect((latest.style as any).border_thickness).toBeNull();
  });

  it('disables the Text override entirely for a self-scaling widget type (e.g. game-card)', () => {
    render(<WidgetStyleSection widgetType="game-card" config={{}} onChange={() => {}} />);

    expect(screen.getByLabelText(/Override text style/)).toBeDisabled();
    expect(screen.getByText(/scales its own text automatically/)).toBeInTheDocument();
  });

  it('leaves the Text override enabled for a non-self-scaling widget type', () => {
    render(<WidgetStyleSection widgetType="server-name" config={{}} onChange={() => {}} />);

    expect(screen.getByLabelText(/Override text style/)).not.toBeDisabled();
  });

  it('writes background_color/background_opacity into config.style when overridden', () => {
    let latest: Record<string, unknown> = {};
    render(<WidgetStyleSection widgetType="picture" config={{}} onChange={(next) => (latest = next)} />);

    screen.getByLabelText(/Override background/).click();

    expect((latest.style as any).background_color).toBe('#ffffff');
    expect((latest.style as any).background_opacity).toBe(1);
  });
});
