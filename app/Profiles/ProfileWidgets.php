<?php

namespace App\Profiles;

use App\Models\SiteOption;

/**
 * Which widget types an admin has permitted on user profiles.
 *
 * Two questions that must not be conflated:
 *
 *   Capability — can this widget render on a profile at all? That lives in
 *   code, in the SPA's widget registry, as `validFor: ['user_profile']`.
 *   A widget declares it only if it can actually work there, and the
 *   renderer independently refuses anything that doesn't (the containment
 *   guard from v0.1.021.01 stays in force regardless of configuration).
 *
 *   Policy — of the capable ones, which does this site allow? That is the
 *   admin setting below, and it is the only half an admin can change.
 *
 * The list a person picks from when editing their profile is the
 * intersection. Collapsing the two into one admin-editable list would let
 * an admin enable server-status on profiles and crash every profile page,
 * which is precisely the failure that release fixed.
 *
 * CAPABLE mirrors the SPA registry and exists only so the admin form has
 * checkboxes to render — it is not the enforcement point, which is why a
 * stale entry here is harmless: the SPA still filters by its own registry
 * and refuses to render what it cannot. Adding a profile-capable widget
 * means adding it in both places, and the frontend test
 * `profileCapableWidgetsMatchTheAdminOptions` fails if they drift.
 */
class ProfileWidgets
{
    /** @var array<string, string> widget type => label shown to an admin */
    public const CAPABLE = [
        'text' => 'Text',
        'profile-avatar' => 'Avatar',
        'profile-bio' => 'Bio',
        'profile-stats' => 'Stats',
    ];

    /**
     * The starter set — what a site that has never touched the setting
     * allows. Deliberately everything capable today: the setting exists so
     * an admin can *narrow* the list, and starting it empty would ship a
     * profile editor with nothing in it.
     *
     * @var list<string>
     */
    public const DEFAULT_ENABLED = ['text', 'profile-avatar', 'profile-bio', 'profile-stats'];

    public const OPTION_KEY = 'profile_widget_types';

    /** @return list<string> */
    public static function enabled(): array
    {
        $configured = SiteOption::value(self::OPTION_KEY);

        if (! is_array($configured)) {
            return self::DEFAULT_ENABLED;
        }

        // Intersected with CAPABLE on read as well as on write: a stored
        // list can outlive the widget it names (a package uninstalled, a
        // type renamed), and serving a type nothing can render would put
        // an unusable entry in somebody's Add Widget picker.
        return array_values(array_intersect($configured, array_keys(self::CAPABLE)));
    }
}
