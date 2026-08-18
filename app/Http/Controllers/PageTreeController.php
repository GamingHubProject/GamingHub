<?php

namespace App\Http\Controllers;

use App\Services\PageTreeResolver;
use Illuminate\Http\Response;

/**
 * Resolves a Web Tree path like "games/ark/ragnarok". Path-walking and
 * draft-visibility logic live in PageTreeResolver, shared with the API's
 * Pages controller. Placeholder rendering only (plain text) per the brief;
 * a real themed frontend is later work.
 */
class PageTreeController extends Controller
{
    public function __construct(protected PageTreeResolver $resolver) {}

    public function show(string $path): Response
    {
        $page = $this->resolver->findByPath($path);

        if (! $page || ! $this->resolver->isVisible($page)) {
            abort(404);
        }

        return response("Published: {$page->title}");
    }
}
