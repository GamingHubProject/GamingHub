<?php

namespace App\Http\Resources\Api;

use App\Profiles\ProfileWidgets;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public view of a person. Deliberately narrower than
 * Api\UserResource, which serves the signed-in visitor their *own* row:
 * no email and no roles appear here, because this payload is served to
 * anonymous visitors.
 *
 * `display_name` is the resolved one (falling back to the account name),
 * not the raw column — a reader wants what to call this person, and the
 * owner's edit form reads the raw value from /api/v1/user instead.
 *
 * @mixin \App\Models\User
 */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'display_name' => $this->profileName(),
            'avatar_url' => $this->avatarUrl(),
            // Markdown, rendered by the client — no HTML string produced
            // from somebody's bio is ever inserted into a page.
            'bio' => $this->bio,
            'profile_public' => (bool) $this->profile_public,
            'can_edit' => $this->profileEditableBy($viewer),
            // Page-scoped policy, served with the page it governs: the Add
            // Widget picker on a profile is the only thing that needs it,
            // and it is already fetching this.
            'allowed_widget_types' => ProfileWidgets::enabled(),
            'stats' => $this->whenLoaded('stats', fn () => $this->stats->map(fn ($stat) => [
                'source' => $stat->source,
                'subject_type' => $stat->subject_type,
                'subject_id' => $stat->subject_id,
                'key' => $stat->key,
                'value' => $stat->value,
                'metadata' => $stat->metadata,
            ])->values()),
            'achievements' => $this->whenLoaded('achievements', fn () => $this->achievements->map(fn ($achievement) => [
                'source' => $achievement->source,
                'achievement_key' => $achievement->achievement_key,
                'earned_at' => $achievement->earned_at?->toIso8601String(),
                'metadata' => $achievement->metadata,
            ])->values()),
        ];
    }
}
