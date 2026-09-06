import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroWidget, HeroWidgetConfigForm, heroWidgetDefaultConfig } from './HeroWidget';
import type { HeroWidgetConfig } from './HeroWidget';
import type { ResolvedWidgetStyle } from '../shared/widgetStyle';

const ART = 'https://x.test/art.png';

function renderHero(overrides: Partial<HeroWidgetConfig> = {}, resolvedStyle?: Partial<ResolvedWidgetStyle>) {
  const config = { ...heroWidgetDefaultConfig, ...overrides };
  return render(
    <MemoryRouter>
      <HeroWidget config={config} resolvedStyle={resolvedStyle as ResolvedWidgetStyle | undefined} />
    </MemoryRouter>
  );
}

describe('HeroWidget', () => {
  it('renders its headline', () => {
    renderHero({ title: 'Join the fight' });

    expect(screen.getByRole('heading', { name: 'Join the fight' })).toBeInTheDocument();
  });

  it('leaves out the subtitle when there is none, rather than an empty line', () => {
    const { container } = renderHero({ title: 'Only a headline', subtitle: '' });

    expect(container.querySelector('p')).toBeNull();
  });

  it('renders the artwork as a cover background', () => {
    const { container } = renderHero({ background_url: ART });
    const root = container.firstElementChild as HTMLElement;

    expect(root.style.backgroundImage).toContain(ART);
    expect(root.style.backgroundSize).toBe('cover');
  });

  it.each([
    ['cover', 'cover'],
    ['contain', 'contain'],
    ['fill', '100% 100%'],
  ] as const)('maps fit=%s to background-size %s, the same as a Picture does', (fit, expectedSize) => {
    const { container } = renderHero({ background_url: ART, fit });

    expect((container.firstElementChild as HTMLElement).style.backgroundSize).toBe(expectedSize);
  });

  it('applies the configured artwork position so the headline can clear the subject', () => {
    const { container } = renderHero({ background_url: ART, position: 'right bottom' });

    expect((container.firstElementChild as HTMLElement).style.backgroundPosition).toBe('right bottom');
  });

  it('falls back to cover/centre for a hero saved before those controls existed', () => {
    // The container hands a widget its stored config untouched, with no
    // merge against defaultConfig — so a config missing both keys has to
    // resolve to what that hero was already doing.
    const { fit, position, ...legacy } = { ...heroWidgetDefaultConfig, background_url: ART };
    const { container } = render(
      <MemoryRouter>
        <HeroWidget config={legacy as HeroWidgetConfig} />
      </MemoryRouter>
    );
    const root = container.firstElementChild as HTMLElement;

    expect(root.style.backgroundSize).toBe('cover');
    expect(root.style.backgroundPosition).toBe('center');
  });

  it('applies the universal Text size to the headline', () => {
    // Regression: the component used to take only `config`, so the size
    // and colour the container passed it went nowhere at all.
    renderHero({ title: 'Sized' }, { textSize: 48 });

    expect(screen.getByRole('heading', { name: 'Sized' })).toHaveStyle({ fontSize: '48px' });
  });

  it('scales the subtitle with the headline rather than flattening the pair', () => {
    const { container } = renderHero({ title: 'Sized', subtitle: 'Beneath it' }, { textSize: 48 });

    expect(container.querySelector('p')).toHaveStyle({ fontSize: '21.6px' });
  });

  it('leaves the button at its own size when a Text size is set', () => {
    // The size lands on the text elements, not their wrapper — a control
    // blown up to headline size is not what "bigger headline" means.
    renderHero({ title: 'Sized', cta_label: 'Play', cta_url: '/games' }, { textSize: 48 });

    expect(screen.getByRole('link', { name: 'Play' }).style.fontSize).toBeFalsy();
  });

  it('applies the universal Text colour whether or not the hero is layered', () => {
    // Unlike ServerNameWidget's layered-only rule: a hero's text sits over
    // artwork by definition, so a colour chosen against that art is the
    // normal case.
    const { container } = renderHero({ title: 'Coloured', background_url: ART }, { textColor: '#ff0000' });

    expect(container.querySelector('h2')?.closest('div')).toHaveStyle({ color: 'rgb(255, 0, 0)' });
  });

  it('leaves the default clamped sizes alone when no Text size is set', () => {
    renderHero({ title: 'Default' });

    expect(screen.getByRole('heading', { name: 'Default' }).style.fontSize).toContain('clamp');
  });

  it('accepts layered children by default, so a hero can hold Status or Metrics', () => {
    expect(heroWidgetDefaultConfig.allow_layering).toBe(true);
  });

  it('links an internal button through the router', () => {
    renderHero({ cta_label: 'Browse games', cta_url: '/games' });

    expect(screen.getByRole('link', { name: 'Browse games' })).toHaveAttribute('href', '/games');
  });

  it('opens an off-site button in a new tab rather than routing to it', () => {
    // Handing an absolute URL to <Link> would resolve it against the app's
    // own routes.
    renderHero({ cta_label: 'Discord', cta_url: 'https://discord.gg/x' });
    const link = screen.getByRole('link', { name: 'Discord' });

    expect(link).toHaveAttribute('href', 'https://discord.gg/x');
    expect(link).toHaveAttribute('target', '_blank');
  });

  it('needs both halves before the button appears', () => {
    // A button with no destination looks like a setting that failed to save.
    renderHero({ cta_label: 'Nowhere', cta_url: '' });
    expect(screen.queryByRole('link')).not.toBeInTheDocument();

    renderHero({ cta_label: '', cta_url: '/games' });
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('scrims the artwork by default so text over it stays readable', () => {
    expect(heroWidgetDefaultConfig.overlay_opacity).toBeGreaterThan(0);
  });

  it('draws no scrim when there is no artwork to darken', () => {
    const { container } = renderHero({ background_url: null, overlay_opacity: 0.8 });

    expect(container.querySelector('[aria-hidden="true"]')).toBeNull();
  });

  it('centres its content when asked to', () => {
    const { container } = renderHero({ align: 'center' });

    expect((container.firstElementChild as HTMLElement).style.textAlign).toBe('center');
  });
});

describe('HeroWidgetConfigForm', () => {
  function renderForm(overrides: Partial<HeroWidgetConfig> = {}) {
    const onChange = vi.fn();
    render(<HeroWidgetConfigForm config={{ ...heroWidgetDefaultConfig, ...overrides }} onChange={onChange} />);
    return onChange;
  }

  it('offers the same three fit modes a Picture does', () => {
    renderForm();
    fireEvent.click(screen.getByRole('button', { name: 'Fit' }));

    expect(screen.getAllByRole('option').map((o) => o.textContent)).toEqual([
      'Cover (crop to fill)',
      'Contain (fit whole image)',
      'Fill (stretch)',
    ]);
  });

  it('writes a chosen position into the config', () => {
    const onChange = renderForm();
    fireEvent.click(screen.getByRole('button', { name: 'Position' }));
    fireEvent.pointerDown(screen.getByRole('option', { name: 'Top right' }));

    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ position: 'right top' }));
  });

  it('shows layering as on for a hero whose config predates the toggle', () => {
    // Unchecked here would misreport what the editor actually allows —
    // isLayerTargetWidget treats a missing key as permission granted.
    const { allow_layering, ...legacy } = heroWidgetDefaultConfig;
    render(<HeroWidgetConfigForm config={legacy as HeroWidgetConfig} onChange={vi.fn()} />);

    expect(screen.getByRole('checkbox', { name: /Allow layering/ })).toBeChecked();
  });

  it('turns layering off through the config the editor reads', () => {
    const onChange = renderForm();
    fireEvent.click(screen.getByRole('checkbox', { name: /Allow layering/ }));

    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ allow_layering: false }));
  });
});
