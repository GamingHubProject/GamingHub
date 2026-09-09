<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ProfileResource;
use App\Http\Resources\Api\UserResource;
use App\Models\Asset;
use App\Models\SiteOption;
use App\Models\User;
use App\Profiles\RichText;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public profile reads, and the owner's own writes.
 *
 * Two read routes for one page: /users/{id} is canonical and survives
 * every rename, /@{name} resolves the display name and renders the same
 * page rather than redirecting — a redirect either leaks the rename
 * through the URL bar or gets cached against a name that has since moved.
 * The '@' sigil is not decoration: the SPA's router ends in a catch-all
 * serving admin-authored Web Tree pages from arbitrary slugs, so a bare
 * /{name} would compete with that namespace.
 *
 * A private profile answers 403, not 404. The account demonstrably exists
 * — its id is in the URL the visitor just typed — and pretending
 * otherwise buys nothing while making "no such account" and "not for you"
 * indistinguishable to the person's own friends.
 */
class ProfileController extends Controller
{
    public function show(Request $request, User $user): ProfileResource
    {
        return $this->respond($request, $user);
    }

    /**
     * Case-insensitively, because display_name's uniqueness index is
     * case-insensitive: /@rose and /@Rose are the same person, and only
     * one of them can exist.
     */
    public function showByName(Request $request, string $name): ProfileResource
    {
        $user = User::query()->whereRaw('lower(display_name) = lower(?)', [$name])->first()
            ?? User::query()->whereRaw('lower(name) = lower(?)', [$name])->firstOrFail();

        return $this->respond($request, $user);
    }

    /**
     * The signed-in visitor editing their own profile. An admin editing
     * somebody else's goes through Filament rather than a second API path
     * — there is no admin-facing SPA surface for it yet, and inventing an
     * "edit anyone" endpoint before anything calls it would be a wider
     * write surface than this release needs.
     */
    public function update(Request $request): UserResource
    {
        $user = $request->user();

        $data = $request->validate([
            'display_name' => [
                'sometimes',
                'nullable',
                'string',
                'min:2',
                'max:50',
                // A character allowlist, not just uniqueness: a name is
                // rendered next to other people's names, so it may not
                // carry whitespace tricks or lookalike punctuation. Case
                // is preserved as typed; only uniqueness ignores it.
                'regex:/^[A-Za-z0-9][A-Za-z0-9 _.-]*[A-Za-z0-9]$/',
                Rule::unique('users', 'display_name')->ignore($user->id),
            ],
            'avatar_asset_id' => ['sometimes', 'nullable', 'integer', Rule::exists('assets', 'id')],
            'bio' => ['sometimes', 'nullable', 'string', 'max:'.RichText::MAX_LENGTH],
            'profile_public' => ['sometimes', 'boolean'],
            'profile_theme' => ['sometimes', 'nullable', 'array'],
            'profile_theme.*' => ['string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if (array_key_exists('profile_theme', $data) && is_array($data['profile_theme'])) {
            $data['profile_theme'] = array_intersect_key(
                $data['profile_theme'],
                array_flip(User::PROFILE_THEME_TOKENS)
            ) ?: null;
        }

        // Uniqueness above is case-sensitive (Rule::unique compares
        // exactly); the index is not. Checking here as well turns what
        // would otherwise be a 500 from the database into the validation
        // error the form knows how to show.
        if (array_key_exists('display_name', $data) && $data['display_name'] !== null) {
            $taken = User::query()
                ->whereRaw('lower(display_name) = lower(?)', [$data['display_name']])
                ->where('id', '!=', $user->id)
                ->exists();

            abort_if($taken, 422, 'That display name is already taken.');
        }

        // Stored as typed. Markdown is inert text — see RichText — so
        // this normalises rather than sanitises, and what comes back to
        // the editor is what the person wrote.
        if (array_key_exists('bio', $data)) {
            $data['bio'] = RichText::normalize($data['bio']);
        }

        // An avatar has to be an image somebody could actually render, and
        // it becomes publicly readable the moment it is on a public
        // profile — so a person cannot point it at, say, a font in the
        // admin-only Fonts folder and have it served from their page.
        if (! empty($data['avatar_asset_id'])) {
            $asset = Asset::find($data['avatar_asset_id']);
            abort_unless($asset && str_starts_with((string) $asset->mime_type, 'image/'), 422, 'An avatar has to be an image.');
        }

        $user->fill($data)->save();

        return new UserResource($user->refresh());
    }

    private function respond(Request $request, User $user): ProfileResource
    {
        abort_unless($user->profileVisibleTo($request->user()), 403, 'This profile is private.');

        $user->loadMissing(['avatarAsset', 'stats', 'achievements']);

        return new ProfileResource($user);
    }
}
