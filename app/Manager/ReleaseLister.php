<?php

namespace App\Manager;

use RuntimeException;
use Throwable;

/**
 * Lists the real published release versions for an extension's GitHub
 * repository, so the "Install" form can offer a dropdown instead of asking
 * an admin to guess an exact tag blind. Only feeds the UI — PackageDownloader
 * still does its own independent fetch of the actual release assets at
 * install time, so a stale/cached list here can never cause an install of
 * something that doesn't really exist.
 */
final class ReleaseLister
{
    public function __construct(protected HttpClientContract $http) {}

    /**
     * @return array<int, string> version strings without the leading "v",
     *                            newest first (GitHub returns releases in
     *                            reverse-chronological order already).
     *                            Empty on any failure — the caller falls
     *                            back to free-text entry rather than
     *                            blocking install on GitHub being reachable.
     */
    public function listVersions(ExtensionDefinition $extension): array
    {
        try {
            $body = $this->http->get($this->releasesApiUrl($extension->repository));
            $releases = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($releases)) {
            return [];
        }

        return collect($releases)
            ->reject(fn (array $release) => $release['draft'] ?? false)
            ->map(fn (array $release) => ltrim($release['tag_name'] ?? '', 'v'))
            ->filter()
            ->values()
            ->all();
    }

    private function releasesApiUrl(string $repository): string
    {
        $path = parse_url(rtrim($repository, '/'), PHP_URL_PATH);

        if (! $path || substr_count(trim($path, '/'), '/') !== 1) {
            throw new RuntimeException("Could not determine owner/repo from repository URL [{$repository}].");
        }

        return 'https://api.github.com/repos'.$path.'/releases';
    }
}
