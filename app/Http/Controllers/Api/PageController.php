<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PageResource;
use App\Services\PageTreeResolver;

class PageController extends Controller
{
    public function show(string $path, PageTreeResolver $resolver): PageResource
    {
        $page = $resolver->findByPath($path);

        abort_unless($page && $resolver->isVisible($page), 404);

        return new PageResource($page);
    }
}
