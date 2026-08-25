import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { GameCard } from './GameCard';
import type { Game } from '../api/types';

const baseGame: Game = {
  id: 1,
  name: 'Ark',
  slug: 'ark',
  description: null,
  icon_url: null,
  status: 'enabled',
  has_servers: true,
  servers_count: 3,
  metadata: null,
};

function renderCard(game: Game) {
  return render(
    <MemoryRouter>
      <GameCard game={game} />
    </MemoryRouter>
  );
}

describe('GameCard', () => {
  it('shows the server count when has_servers is true', () => {
    renderCard(baseGame);

    expect(screen.getByText('3 Servers')).toBeInTheDocument();
  });

  it('falls back to 0 Servers when servers_count is missing but has_servers is true', () => {
    renderCard({ ...baseGame, servers_count: undefined });

    expect(screen.getByText('0 Servers')).toBeInTheDocument();
  });

  it('shows "External" instead of a count when has_servers is false', () => {
    renderCard({ ...baseGame, has_servers: false, servers_count: 5 });

    expect(screen.getByText('External')).toBeInTheDocument();
    expect(screen.queryByText('5 Servers')).not.toBeInTheDocument();
  });
});
