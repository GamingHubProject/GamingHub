<?php

namespace App\Http\Controllers;

use App\Models\Page;
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

        if (! $page->isPublished() && ! (auth()->check() && auth()->user()->can('see_drafts'))) {
            abort(404);
        }

        return response("Published: {$page->title}");
    }
}
