<?php

namespace App\Http\Controllers;

use App\Experience\BlockRegistry;
use App\Experience\ThemeResolver;
use App\Models\Page;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function show(string $slug, BlockRegistry $registry, ThemeResolver $themeResolver): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('game')
            ->firstOrFail();

        $renderedBlocks = collect($page->blocks ?? [])
            ->map(function (array $block) use ($registry) {
                $class = $registry->get($block['type'] ?? '');

                return $class ? (new $class)->render($block['config'] ?? []) : null;
            })
            ->filter();

        return view('experience.page', [
            'page' => $page,
            'renderedBlocks' => $renderedBlocks,
            'tokens' => $themeResolver->resolve($page->game),
        ]);
    }
}
