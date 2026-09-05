import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroWidget, heroWidgetDefaultConfig } from './HeroWidget';
import type { HeroWidgetConfig } from './HeroWidget';

function renderHero(overrides: Partial<HeroWidgetConfig> = {}) {
  const config = { ...heroWidgetDefaultConfig, ...overrides };
  return render(
    <MemoryRouter>
      <HeroWidget config={config} />
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
    const { container } = renderHero({ background_url: 'https://x.test/art.png' });
    const root = container.firstElementChild as HTMLElement;

    expect(root.style.backgroundImage).toContain('https://x.test/art.png');
    expect(root.style.backgroundSize).toBe('cover');
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
