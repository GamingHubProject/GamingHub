<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // The raw column, not the resolved profileName() that
            // ProfileResource serves: this payload is what the owner's own
            // edit form fills itself from, and it has to be able to tell
            // "I haven't set one" from "it happens to match my account
            // name".
            'display_name' => $this->display_name,
            'avatar' => $this->avatar,
            'avatar_asset_id' => $this->avatar_asset_id,
            'avatar_url' => $this->avatarUrl(),
            'bio' => $this->bio,
            'profile_public' => (bool) $this->profile_public,
            'preferences' => $this->preferences,
            'is_admin' => $this->hasRole('Admin'),
        ];
    }
}
