<?php

namespace App\Services;

use App\Models\Page;
use App\Permissions\ScopedPermissionChecker;

/**
 * Shared by the Blade Web Tree route (PageTreeController) and the API's
 * Pages controller so path-walking and draft-visibility rules can't drift
 * between the two — see Page::pathSegments() for why a stored path column
 * isn't used instead.
 */
class PageTreeResolver
{
    public function findByPath(string $path): ?Page
    {
        $segments = explode('/', trim($path, '/'));

        $page = null;

        foreach ($segments as $slug) {
            $page = Page::query()
                ->where('parent_id', $page?->id)
                ->where('slug', $slug)
                ->first();

            if (! $page) {
                return null;
            }
        }

        return $page;
    }

    /**
     * page.scope is one grant covering both edit access and draft
     * visibility together (see PageResource::applyVisibilityScope) — a
     * visitor sees a draft here under the same rule an admin would in the
     * Page list. A page with no game (game_id null) has no entity to grant
     * page.scope against, so its drafts are Admin-only, same as the admin
     * panel's own handling of global pages.
     */
    public function canSeeDraft(Page $page): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($page->game_id === null) {
            return false;
        }

        return app(ScopedPermissionChecker::class)->can($user, 'page', $page->game);
    }

    public function isVisible(Page $page): bool
    {
        if ($page->isFolder()) {
            return false;
        }

        return $page->isPublished() || $this->canSeeDraft($page);
    }
}
