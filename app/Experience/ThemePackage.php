<?php

namespace App\Experience;

use App\Models\Theme;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * A theme, as a file you can move between installs.
 *
 * The folder model (see ThemeStorage) already did the hard part: a theme
 * is self-contained, references its files by folder-relative path, and
 * carries nothing site-specific — no logo, no links, no scope. So an
 * export really is just "zip the folder", and this class is mostly about
 * the other direction, where an archive arrives from somewhere untrusted.
 *
 * The security posture is worth stating plainly, because it's a choice
 * rather than a precaution: import NEVER calls ZipArchive::extractTo. Each
 * entry is read by name and written to a path this class builds itself
 * from the theme's slug and a validated relative path. Zip-slip isn't
 * guarded against here; it's unreachable, because no path out of the
 * archive is ever used as a path on disk.
 *
 * Everything else an archive can lie about is bounded rather than trusted:
 * entry count, uncompressed size (checked against the header AND counted
 * while streaming, since the header is attacker-controlled), where a file
 * may live, and what extension it may have.
 */
class ThemePackage
{
    public const MANIFEST = 'theme.json';

    /** Generous for a theme (a handful of images and one font), tiny for a bomb. */
    public const MAX_ENTRIES = 200;

    public const MAX_BYTES = 32 * 1024 * 1024;

    /**
     * What may live where. Deliberately the same policy the Asset Library
     * enforces on upload (config/assets.php) rather than a looser one:
     * an imported file lands on the same public disk as an uploaded one
     * and is served the same way, so it has no business being more
     * permissive. That's why TTF and OTF are absent — see the note in
     * config/assets.php.
     */
    public const ALLOWED_EXTENSIONS = [
        'font' => ['woff', 'woff2'],
        'favicon' => ['png', 'svg', 'ico'],
        'backgrounds' => ['png', 'jpg', 'jpeg', 'webp', 'svg'],
    ];

    public function __construct(private readonly ThemeStorage $storage) {}

    private function disk()
    {
        return Storage::disk(config('assets.disk'));
    }

    // --- Export ------------------------------------------------------

    /**
     * Zip the theme's folder to a local path and return it.
     *
     * theme.json sits at the root of the archive rather than inside a
     * folder named for the theme. That's what makes the same zip usable
     * as a registry package in Phase D without a second layout: the
     * manifest is findable without first guessing the folder name.
     */
    public function export(Theme $theme, ?string $destination = null): string
    {
        $destination ??= tempnam(sys_get_temp_dir(), 'theme_').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new ThemePackageException("Could not create an archive at [{$destination}].");
        }

        $root = $this->storage->themePath($theme->slug);

        foreach ($this->disk()->allFiles($root) as $path) {
            $relative = ltrim(substr($path, strlen($root)), '/');
            $zip->addFromString($relative, (string) $this->disk()->get($path));
        }

        $zip->close();

        return $destination;
    }

    /** What an exported file should be called. */
    public function filename(Theme $theme): string
    {
        $version = $theme->bundle()->version ?: '1.0.0';

        return "{$theme->slug}-{$version}.zip";
    }

    // --- Reading an archive ------------------------------------------

    /**
     * Everything an admin should be told about an archive before anything
     * is written. Throws for an archive that could never be imported, so
     * a caller can use this as the validation step.
     *
     * @return array{name: string, version: string, slug: string, files: int, bundle: ThemeBundle}
     */
    public function inspect(string $zipPath): array
    {
        $zip = $this->open($zipPath);

        try {
            $prefix = $this->manifestPrefix($zip);
            $bundle = $this->readManifest($zip, $prefix);
            $files = $this->entries($zip, $prefix);

            return [
                'name' => $bundle->name,
                'version' => $bundle->version,
                'slug' => Str::slug($bundle->id) ?: Str::slug($bundle->name),
                'files' => count($files),
                'bundle' => $bundle,
            ];
        } finally {
            $zip->close();
        }
    }

    // --- Import ------------------------------------------------------

    /**
     * Unpack an archive into a theme.
     *
     * `$onConflict` decides what happens when the name already belongs to
     * a theme on this install:
     *
     *  - 'copy' takes a new slug (nebula-2) and leaves the original alone.
     *  - 'replace' overwrites the existing theme's folder while keeping
     *    its row, its slug and — the part that matters — its assignments.
     *    Replacing the live theme has to leave the site themed, otherwise
     *    "update my theme to v2" would quietly un-theme every page. Scope
     *    was split out of the theme (ThemeAssignment) precisely so this
     *    could be true.
     *
     * Keeping the slug on replace also keeps every URL to the theme's
     * files valid, which a re-slugged copy would not.
     *
     * `$into` overrides the whole conflict question by naming the theme to
     * overwrite outright — see the note at its use below.
     */
    public function import(string $zipPath, ?string $name = null, string $onConflict = 'copy', ?Theme $into = null): Theme
    {
        $zip = $this->open($zipPath);

        try {
            $prefix = $this->manifestPrefix($zip);
            $bundle = $this->readManifest($zip, $prefix);
            $entries = $this->entries($zip, $prefix);

            $name = trim((string) ($name ?: $bundle->name)) ?: 'Imported theme';

            // `$into` names the exact theme to overwrite, which a registry
            // update needs: it knows which theme it created last time and
            // must land on that one even if an admin has since renamed it.
            // Matching by name would quietly install a second copy instead
            // of updating, and leave the old one applied.
            $existing = $into ?? ($onConflict === 'replace' ? $this->conflictFor($name) : null);

            $theme = $existing ?? $this->storage->createTheme($name);

            if ($existing) {
                // Replacing means the archive's files ARE the theme's
                // files — leftovers from the previous version would be
                // referenced by nothing and shipped by the next export.
                $this->storage->clearFiles($theme);
            }

            foreach ($entries as $relative => $index) {
                $this->writeEntry($zip, $index, $theme, $relative);
            }

            // The id in an archive is advisory: it's the theme's identity
            // on the install it came from. Here the destination slug is
            // the identity, the same rewrite duplicateTheme does.
            $bundle->id = $theme->slug;
            $bundle->name = $name;

            return $this->storage->writeBundle($theme, $bundle);
        } finally {
            $zip->close();
        }
    }

    /**
     * The theme an import under this name would collide with, if any.
     *
     * Name first, slug second, and the order matters. The admin picks a
     * name, and the name is what they see in the themes list — so "replace
     * the one called Nebula" has to find the theme called Nebula even
     * when its folder ended up at nebula-2 because something else had
     * claimed the obvious slug first. Falling back to the slug then
     * catches the reverse case, where the folder matches but somebody has
     * since renamed the theme.
     */
    public function conflictFor(string $name): ?Theme
    {
        return Theme::where('name', $name)->first()
            ?? Theme::where('slug', Str::slug($name))->first();
    }

    // --- Internals ---------------------------------------------------

    private function open(string $zipPath): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new ThemePackageException('That file could not be read as a zip archive.');
        }

        if ($zip->numFiles > self::MAX_ENTRIES) {
            $zip->close();
            throw new ThemePackageException(
                'That archive holds '.$zip->numFiles.' entries; a theme package may hold at most '.self::MAX_ENTRIES.'.'
            );
        }

        return $zip;
    }

    /**
     * Where theme.json lives inside the archive.
     *
     * Normally the root, which is what export writes. But a zip produced
     * by GitHub's "Download source" or by `git archive` wraps everything
     * in one directory, and refusing those would make the obvious way of
     * sharing a theme the wrong one — so a single wrapping folder is
     * tolerated. Anything deeper is not: at that point the archive is a
     * collection of things rather than one theme.
     */
    private function manifestPrefix(ZipArchive $zip): string
    {
        $candidates = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (basename($name) === self::MANIFEST) {
                $candidates[] = $name;
            }
        }

        if ($candidates === []) {
            throw new ThemePackageException(
                'That archive has no theme.json, so it isn\'t a theme package. A theme package has theme.json at its root.'
            );
        }

        // The shallowest wins, so a stray theme.json in a backgrounds
        // folder can't shadow the real manifest.
        usort($candidates, fn ($a, $b) => substr_count($a, '/') <=> substr_count($b, '/'));
        $manifest = $candidates[0];
        $depth = substr_count($manifest, '/');

        if ($depth > 1) {
            throw new ThemePackageException(
                "That archive's theme.json is nested at [{$manifest}]. It belongs at the root of the archive, or one folder in."
            );
        }

        return $depth === 0 ? '' : dirname($manifest).'/';
    }

    private function readManifest(ZipArchive $zip, string $prefix): ThemeBundle
    {
        $raw = $zip->getFromName($prefix.self::MANIFEST);

        if ($raw === false || strlen($raw) > 1024 * 1024) {
            throw new ThemePackageException('That archive\'s theme.json could not be read.');
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            throw new ThemePackageException('That archive\'s theme.json is not valid JSON.');
        }

        // A hand-written theme.json with no schema line is taken at its
        // word — ThemeBundle::fromArray is total and fills in defaults for
        // everything absent. A schema that is present and unrecognised is
        // refused, because it's a positive claim to be something this
        // version can't read.
        $schema = $data['schema'] ?? null;

        if ($schema !== null && $schema !== ThemeBundle::SCHEMA) {
            throw new ThemePackageException(
                "That theme declares schema [{$schema}], which this version doesn't understand. It reads ".ThemeBundle::SCHEMA.'.'
            );
        }

        return ThemeBundle::fromArray($data);
    }

    /**
     * The archive's importable files, as relative path => entry index.
     *
     * Anything outside the theme's known subfolders is refused rather
     * than skipped. Silently dropping a file means the theme that comes
     * out of an import isn't the theme that went into the export, and the
     * admin finds out later, from a missing background.
     *
     * @return array<string, int>
     */
    private function entries(ZipArchive $zip, string $prefix): array
    {
        $files = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) $stat['name'];

            if (! str_starts_with($name, $prefix)) {
                throw new ThemePackageException("That archive holds [{$name}], which sits outside the theme it contains.");
            }

            $relative = substr($name, strlen($prefix));

            // Directory entries carry no content; the folders are created
            // by ThemeStorage, not by the archive.
            if ($relative === '' || str_ends_with($relative, '/')) {
                continue;
            }

            if ($relative === self::MANIFEST) {
                continue; // read separately, written by writeBundle
            }

            $total += (int) $stat['size'];

            if ($total > self::MAX_BYTES) {
                throw new ThemePackageException(
                    'That archive unpacks to more than '.(self::MAX_BYTES / 1024 / 1024).'MB, which is far more than a theme needs.'
                );
            }

            $this->assertAllowed($relative);
            $files[$relative] = $i;
        }

        return $files;
    }

    private function assertAllowed(string $relative): void
    {
        $parts = explode('/', $relative);

        if (count($parts) !== 2) {
            throw new ThemePackageException(
                "That archive holds [{$relative}]. A theme's files live in font/, favicon/ or backgrounds/, one level deep."
            );
        }

        [$subfolder, $filename] = $parts;

        if (! isset(self::ALLOWED_EXTENSIONS[$subfolder])) {
            throw new ThemePackageException(
                "That archive holds [{$relative}], and [{$subfolder}] is not one of a theme's folders (font, favicon, backgrounds)."
            );
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS[$subfolder], true)) {
            $allowed = implode(', ', self::ALLOWED_EXTENSIONS[$subfolder]);
            throw new ThemePackageException(
                "That archive holds [{$relative}]. Files in {$subfolder}/ must be one of: {$allowed}."
            );
        }
    }

    /**
     * Stream one entry onto the disk, at a path built here rather than
     * taken from the archive.
     *
     * The size is checked again while copying: statIndex reports what the
     * archive's header CLAIMS, and a header is exactly the sort of thing a
     * hostile archive lies about.
     */
    private function writeEntry(ZipArchive $zip, int $index, Theme $theme, string $relative): void
    {
        $stream = $zip->getStreamIndex($index);

        if ($stream === false) {
            throw new ThemePackageException("Could not read [{$relative}] from that archive.");
        }

        try {
            $written = 0;
            $target = $this->storage->themePath($theme->slug, $relative);
            $temp = fopen('php://temp', 'w+b');

            while (! feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false) {
                    break;
                }
                $written += strlen($chunk);

                if ($written > self::MAX_BYTES) {
                    throw new ThemePackageException(
                        "[{$relative}] unpacks to more than its archive claimed, so the archive isn't trustworthy."
                    );
                }

                fwrite($temp, $chunk);
            }

            rewind($temp);
            $this->disk()->put($target, $temp);
            fclose($temp);
        } finally {
            fclose($stream);
        }

        $this->storage->registerFile($theme, $relative);
    }
}
