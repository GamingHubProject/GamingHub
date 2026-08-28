import { describe, expect, it } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
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

  it('reveals border sub-fields and writes the full border override into config.style when toggled on', () => {
    let latest: Record<string, unknown> = {};
    const { rerender } = render(<WidgetStyleSection widgetType="picture" config={{}} onChange={(next) => (latest = next)} />);

    screen.getByLabelText(/Override border/).click();
    rerender(<WidgetStyleSection widgetType="picture" config={latest} onChange={(next) => (latest = next)} />);

    const style = latest.style as any;
    expect(style.border_enabled).toBe(true);
    expect(style.border_thickness).toBe(1);
    expect(style.border_color).toBe('#dddddd');
    expect(style.border_radius).toBe(8);
    expect(screen.getByLabelText(/Roundness/)).toBeInTheDocument();
  });

  it('clears the entire border override back to null when toggled off', () => {
    let latest: Record<string, unknown> = {
      style: { border_enabled: true, border_thickness: 3, border_color: '#ff0000', border_radius: 4 },
    };
    const { rerender } = render(<WidgetStyleSection widgetType="picture" config={latest} onChange={(next) => (latest = next)} />);

    screen.getByLabelText(/Override border/).click();
    rerender(<WidgetStyleSection widgetType="picture" config={latest} onChange={(next) => (latest = next)} />);

    const style = latest.style as any;
    expect(style.border_enabled).toBeNull();
    expect(style.border_thickness).toBeNull();
    expect(style.border_color).toBeNull();
    expect(style.border_radius).toBeNull();
  });

  it('writes an edited border_radius value into config.style', () => {
    let latest: Record<string, unknown> = { style: { border_enabled: true, border_thickness: 1, border_color: '#dddddd', border_radius: 8 } };
    render(<WidgetStyleSection widgetType="picture" config={latest} onChange={(next) => (latest = next)} />);

    fireEvent.change(screen.getByLabelText(/Roundness/), { target: { value: '20' } });

    expect((latest.style as any).border_radius).toBe(20);
  });

  it('shows a percentage size-adjustment control (not a fixed px field) for a self-scaling widget type (e.g. game-card), with Text still enabled', () => {
    render(<WidgetStyleSection widgetType="game-card" config={{}} onChange={() => {}} />);

    expect(screen.getByLabelText(/Override text style/)).not.toBeDisabled();
    expect(screen.getByText(/scales its own text proportionally/)).toBeInTheDocument();

    screen.getByLabelText(/Override text style/).click();
  });

  it('writes text_scale (not text_size) into config.style for a self-scaling widget when Text is overridden', () => {
    let latest: Record<string, unknown> = {};
    render(<WidgetStyleSection widgetType="game-card" config={{}} onChange={(next) => (latest = next)} />);

    screen.getByLabelText(/Override text style/).click();

    const style = latest.style as any;
    expect(style.text_scale).toBe(1);
    expect(style.text_size).toBeNull();
    expect(style.text_color).toBe('#000000');
  });

  it('writes text_size (not text_scale) into config.style for a non-self-scaling widget when Text is overridden', () => {
    let latest: Record<string, unknown> = {};
    render(<WidgetStyleSection widgetType="server-name" config={{}} onChange={(next) => (latest = next)} />);

    screen.getByLabelText(/Override text style/).click();

    const style = latest.style as any;
    expect(style.text_size).toBe(16);
    expect(style.text_scale).toBeNull();
  });

  it('shows the percentage adjustment label reflecting the current text_scale', () => {
    const config = { style: { text_scale: 1.5, text_color: '#000000' } };
    render(<WidgetStyleSection widgetType="game-card" config={config} onChange={() => {}} />);

    expect(screen.getByText(/Size adjustment \(150%\)/)).toBeInTheDocument();
  });

  it('writes background_color/background_opacity into config.style when overridden', () => {
    let latest: Record<string, unknown> = {};
    render(<WidgetStyleSection widgetType="picture" config={{}} onChange={(next) => (latest = next)} />);

    screen.getByLabelText(/Override background/).click();

    expect((latest.style as any).background_color).toBe('#ffffff');
    expect((latest.style as any).background_opacity).toBe(1);
  });

  it('shows a low-contrast warning when the resolved text and background colors are hard to tell apart', () => {
    const config = { style: { text_color: '#111111', background_color: '#000000', background_opacity: 1 } };
    render(<WidgetStyleSection widgetType="picture" config={config} onChange={() => {}} />);

    expect(screen.getByRole('alert')).toHaveTextContent(/hard to read/);
  });

  it('does not show a contrast warning for a clearly legible combination', () => {
    const config = { style: { text_color: '#ffffff', background_color: '#000000', background_opacity: 1 } };
    render(<WidgetStyleSection widgetType="picture" config={config} onChange={() => {}} />);

    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('does not show a contrast warning when no background color resolves at all', () => {
    const config = { style: { text_color: '#111111' } };
    render(<WidgetStyleSection widgetType="picture" config={config} onChange={() => {}} />);

    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});
