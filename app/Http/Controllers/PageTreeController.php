<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Permissions\ScopedPermissionChecker;
use Illuminate\Http\Response;

/**
 * Resolves a Web Tree path like "games/ark/ragnarok" by walking it segment
 * by segment through parent_id, rather than looking up a stored path
 * string — see Page::pathSegments() for why. Placeholder rendering only
 * (plain text) per the brief; a real themed frontend is later work.
 */
class PageTreeController extends Controller
{
    public function show(string $path): Response
    {
        $segments = explode('/', trim($path, '/'));

        $page = null;

        foreach ($segments as $slug) {
            $page = Page::query()
                ->where('parent_id', $page?->id)
                ->where('slug', $slug)
                ->first();

            if (! $page) {
                abort(404);
            }
        }

        if ($page->isFolder()) {
            abort(404);
        }

        if (! $page->isPublished() && ! $this->canSeeDraft($page)) {
            abort(404);
        }

        return response("Published: {$page->title}");
    }

    /**
     * page.scope is now one grant covering both edit access and draft
     * visibility together (see PageResource::applyVisibilityScope) — a
     * visitor sees a draft here under the same rule an admin would in the
     * Page list. A page with no game (game_id null) has no entity to grant
     * page.scope against, so its drafts are Admin-only, same as the admin
     * panel's own handling of global pages.
     */
    protected function canSeeDraft(Page $page): bool
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
}
