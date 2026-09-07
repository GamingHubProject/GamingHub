<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
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
     *
     * What the merge does *not* carry forward is a key outside
     * User::PREFERENCES. Such a key can only exist on a row written before
     * the allowlist, or through Filament's raw key/value editor, which this
     * release removed — leaving it in place would keep exactly the data the
     * allowlist exists to prevent.
     */
    public function updatePreferences(Request $request): UserResource
    {
        $rules = [];
        foreach (User::PREFERENCES as $key => $allowed) {
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

        // Validation above already rejects an unlisted key or value; the
        // sanitiser is what makes that guarantee a property of the column
        // rather than of this one endpoint — Filament's form goes through
        // the same call.
        $user->forceFill(['preferences' => User::sanitizePreferences($preferences)])->save();

        return new UserResource($user->refresh());
    }
}
