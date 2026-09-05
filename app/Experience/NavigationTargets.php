<?php

namespace App\Experience;

use App\Models\NavigationLink;
use App\Models\Page;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;

/**
 * Every destination inside the site a navigation link can point at, and
 * the resolution of a stored target back into a URL.
 *
 * The list is generated rather than typed. An admin picking "Phantom
 * Galaxies" from a dropdown of real games can't produce a link to a page
 * that doesn't exist, and can't be left holding a dead path after someone
 * renames a slug — which is the whole reason a link stores
 * target_type + target_id instead of a URL.
 */
class NavigationTargets
{
    /**
     * The fixed routes the SPA always has (see spa/src/router/routes.tsx).
     * Keyed by target_type; these carry no target_id.
     */
    private const STATIC_TARGETS = [
        'home' => ['label' => 'Home', 'url' => '/'],
        'games' => ['label' => 'Games', 'url' => '/games'],
        'dashboard' => ['label' => 'Dashboard', 'url' => '/dashboard'],
    ];

    /**
     * Everything pickable, grouped for the admin's dropdown.
     *
     * @return list<array{group: string, options: list<array{target_type: string, target_id: int|null, label: string, url: string}>}>
     */
    public function grouped(): array
    {
        $groups = [[
            'group' => 'Pages',
            'options' => collect(self::STATIC_TARGETS)
                ->map(fn (array $t, string $type) => [
                    'target_type' => $type, 'target_id' => null, 'label' => $t['label'], 'url' => $t['url'],
                ])
                ->values()
                ->all(),
        ]];

        $webTree = Page::query()->orderBy('title')->get()
            ->map(fn (Page $page) => [
                'target_type' => 'page', 'target_id' => $page->id,
                'label' => $page->title, 'url' => $this->pageUrl($page),
            ])
            ->all();

        if ($webTree !== []) {
            $groups[] = ['group' => 'Site pages', 'options' => $webTree];
        }

        $games = Game::query()->orderBy('name')->get()
            ->map(fn (Game $game) => [
                'target_type' => 'game', 'target_id' => $game->id,
                'label' => $game->name, 'url' => "/games/{$game->slug}",
            ])
            ->all();

        if ($games !== []) {
            $groups[] = ['group' => 'Games', 'options' => $games];
        }

        $servers = Server::query()->with('game')->orderBy('name')->get()
            ->filter(fn (Server $server) => $server->game !== null)
            ->map(fn (Server $server) => [
                'target_type' => 'server', 'target_id' => $server->id,
                'label' => "{$server->game->name} — {$server->name}",
                'url' => "/games/{$server->game->slug}/servers/{$server->id}",
            ])
            ->values()
            ->all();

        if ($servers !== []) {
            $groups[] = ['group' => 'Servers', 'options' => $servers];
        }

        return $groups;
    }

    /**
     * The URL a link actually points at, resolved fresh every time.
     *
     * Returns null when the target is gone — a game that was deleted, a
     * page that was removed. Callers drop those links rather than render a
     * dead one, which is why this is nullable rather than falling back to
     * "/".
     */
    public function resolve(NavigationLink $link): ?string
    {
        if ($link->type === NavigationLink::TYPE_LINK) {
            return $link->url;
        }

        if ($link->type === NavigationLink::TYPE_FOLDER) {
            // A folder is a container, not a destination. Both renderers
            // treat a null href as "expand me" rather than "navigate".
            return null;
        }

        if (isset(self::STATIC_TARGETS[$link->target_type])) {
            return self::STATIC_TARGETS[$link->target_type]['url'];
        }

        return match ($link->target_type) {
            'page' => ($page = Page::find($link->target_id)) ? $this->pageUrl($page) : null,
            'game' => ($game = Game::find($link->target_id)) ? "/games/{$game->slug}" : null,
            'server' => ($server = Server::with('game')->find($link->target_id)) && $server->game
                ? "/games/{$server->game->slug}/servers/{$server->id}"
                : null,
            default => null,
        };
    }

    /**
     * Web Tree pages live at an arbitrary top-level path built from their
     * ancestors' slugs — see Page::pathSegments, which recomputes it
     * rather than storing it so a rename or move can't leave a stale path.
     */
    private function pageUrl(Page $page): string
    {
        return '/'.implode('/', $page->pathSegments());
    }
}
