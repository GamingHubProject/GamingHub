<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Experience\ThemeBundle;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * The preference keys a user is allowed to set on themselves, and what
     * each may hold.
     *
     * An allowlist rather than "merge whatever was sent", because
     * `users.preferences` is a free-form JSON column: without this, one
     * endpoint would let any signed-in visitor store unbounded arbitrary
     * data on their own row. Adding a preference means adding it here,
     * which is the intended cost.
     */
    private const PREFERENCES = [
        'account_placement' => ThemeBundle::ACCOUNT_PLACEMENTS,
    ];

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Update the signed-in user's own preferences.
     *
     * Merges rather than replaces, so a client that knows about one
     * preference can set it without silently dropping the ones it has
     * never heard of — the same reason a PATCH exists at all. Sending null
     * for a key clears it, which is how a user goes back to following the
     * theme rather than overriding it.
     */
    public function updatePreferences(Request $request): UserResource
    {
        $rules = [];
        foreach (self::PREFERENCES as $key => $allowed) {
            $rules[$key] = ['sometimes', 'nullable', Rule::in($allowed)];
        }

        $validated = $request->validate($rules);

        $user = $request->user();
        $preferences = $user->preferences ?? [];

        foreach ($validated as $key => $value) {
            if ($value === null) {
                unset($preferences[$key]);
            } else {
                $preferences[$key] = $value;
            }
        }

        $user->forceFill(['preferences' => $preferences])->save();

        return new UserResource($user->refresh());
    }
}
