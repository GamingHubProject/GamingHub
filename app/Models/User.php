<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Experience\ThemeBundle;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The preference keys that may ever be stored on a user, and the
     * values each may hold.
     *
     * An allowlist rather than "merge whatever was sent", because
     * `preferences` is a free-form JSON column: without this, one endpoint
     * would let any signed-in visitor store unbounded arbitrary data on
     * their own row. Adding a preference means adding it here, which is
     * the intended cost.
     *
     * It lives on the model rather than in UserController because it has
     * two enforcement points, not one: the API a user writes through, and
     * Filament's UserResource, where an admin edits somebody else's row.
     * The Filament form used to expose this column as a raw key/value
     * editor, which sidestepped the list entirely — a single definition is
     * what stops the two from drifting apart again.
     *
     * @var array<string, list<string>>
     */
    public const PREFERENCES = [
        'account_placement' => ThemeBundle::ACCOUNT_PLACEMENTS,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'bio',
        'preferences',
    ];

    /**
     * Reduces an arbitrary preferences payload to what PREFERENCES allows:
     * unknown keys and unlisted values are dropped, and a key set to null
     * is removed rather than stored as a null — "absent" is how a user goes
     * back to following the theme, so a null left behind would be a third
     * state meaning the same thing.
     *
     * Exists because the allowlist has more than one write path. The API
     * validates its input and could stop there; Filament's form is a second
     * door, and passing both through one function is what makes "an
     * unlisted key cannot be stored" true of the column rather than true of
     * one endpoint.
     *
     * @return array<string, string>
     */
    public static function sanitizePreferences(mixed $preferences): array
    {
        if (! is_array($preferences)) {
            return [];
        }

        $clean = [];

        foreach (self::PREFERENCES as $key => $allowed) {
            $value = $preferences[$key] ?? null;

            if (is_string($value) && in_array($value, $allowed, true)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('Admin');
    }

    public function dashboardPages(): HasMany
    {
        return $this->hasMany(DashboardPage::class)->orderBy('order');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
        ];
    }
}
