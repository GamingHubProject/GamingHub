<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PlayerStatsContract;
use App\Http\Controllers\Controller;
use App\Models\PlayerGameIdentity;
use App\Profiles\PlayerIdentityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The user's own game identity links. Lists which games support linking
 * (driven by installed extensions, not the games table) and lets the user
 * link/unlink their player ID for each.
 *
 * The raw player ID is never stored or returned — only an HMAC hash.
 */
class GameIdentityController extends Controller
{
    /**
     * Every game slug that has a registered identity-capable extension,
     * plus whether the current user has a linked identity for it.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $extensions = app()->tagged('player-stats-extensions');
        $entries = [];

        foreach ($extensions as $extension) {
            if (! $extension instanceof PlayerStatsContract) {
                continue;
            }

            foreach ($extension->supportsPlayerIdentity() as $slug) {
                $entries[$slug] = [
                    'game_slug' => $slug,
                    'label' => $extension->playerIdLabel($slug),
                    'linked' => false,
                ];
            }
        }

        if ($entries !== []) {
            $linked = PlayerGameIdentity::query()
                ->where('user_id', $user->id)
                ->whereIn('game_slug', array_keys($entries))
                ->pluck('game_slug');

            foreach ($linked as $slug) {
                $entries[$slug]['linked'] = true;
            }
        }

        return response()->json(['data' => array_values($entries)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'game_slug' => ['required', 'string', 'max:64'],
            'player_id' => ['required', 'string', 'max:255'],
        ]);

        $extension = $this->findExtension($data['game_slug']);

        if (! $extension) {
            abort(422, 'No extension supports player identity for this game.');
        }

        $validationError = $extension->validatePlayerId($data['game_slug'], $data['player_id']);

        if ($validationError !== null) {
            return response()->json([
                'message' => $validationError,
                'errors' => ['player_id' => [$validationError]],
            ], 422);
        }

        $hash = PlayerIdentityResolver::hash($data['player_id']);

        PlayerGameIdentity::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'game_slug' => $data['game_slug'],
            ],
            [
                'hashed_player_id' => $hash,
            ],
        );

        return response()->json(['linked' => true]);
    }

    public function destroy(Request $request, string $gameSlug): JsonResponse
    {
        PlayerGameIdentity::query()
            ->where('user_id', $request->user()->id)
            ->where('game_slug', $gameSlug)
            ->delete();

        return response()->json(['linked' => false]);
    }

    private function findExtension(string $gameSlug): ?PlayerStatsContract
    {
        foreach (app()->tagged('player-stats-extensions') as $extension) {
            if ($extension instanceof PlayerStatsContract
                && in_array($gameSlug, $extension->supportsPlayerIdentity(), true)) {
                return $extension;
            }
        }

        return null;
    }
}
