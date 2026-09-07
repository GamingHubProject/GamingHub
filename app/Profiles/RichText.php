<?php

namespace App\Profiles;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Rich text is **Markdown**, stored exactly as its author typed it.
 *
 * It was sanitised HTML in v0.1.024.00, and that was the wrong storage
 * format for the editing behaviour people expect. A WYSIWYG editor over an
 * HTML document model toggles a list by rewriting whole blocks, so
 * switching a list off reflows text that was already written — the
 * complaint that produced this release. A Markdown source editor cannot do
 * that: a list is a line prefix, and toggling one edits the selected lines
 * and nothing else.
 *
 * Storing the source rather than the rendered output also means the round
 * trip is lossless: what somebody typed is what comes back to the editor,
 * with no sanitiser standing between the two rewriting their document.
 *
 * **Nothing renders raw HTML.** The SPA renders Markdown to React elements
 * (see spa/src/components/RichText), which never inserts an HTML string at
 * all, and toHtml() below — the path any *server-side* consumer takes —
 * strips HTML at the parser and then runs the result through the same
 * element allowlist as before. Both refuse it; neither depends on the
 * other having done so.
 */
class RichText
{
    /**
     * The elements a rendered document may contain.
     *
     * Mirrored by the SPA renderer's own allowlist
     * (spa/src/components/RichText/schema.ts), and `RichTextAllowlistTest`
     * fails when the two stop matching. The list is unchanged from when
     * this was an HTML store: Markdown that produces anything outside it
     * (a table, an image) renders as nothing rather than as something the
     * other half of the app would refuse.
     *
     * @var array<string, list<string>>
     */
    public const ELEMENTS = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        // GFM strikethrough renders as <del>, not <s> — the allowlist
        // describes rendered output, so it has to name the element the
        // renderers actually produce or the toolbar's strikethrough button
        // would silently do nothing.
        'del' => [],
        'code' => [],
        'pre' => [],
        'blockquote' => [],
        'h2' => [],
        'h3' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href'],
    ];

    /** How many characters of Markdown a single rich-text field may hold. */
    public const MAX_LENGTH = 20000;

    /**
     * What gets stored: the author's own Markdown, trimmed, or null when
     * there is nothing left.
     *
     * Deliberately not a sanitiser. Markdown is inert text — it becomes
     * dangerous only when something renders it, and both renderers refuse
     * raw HTML on their own. Stripping tags here instead would corrupt the
     * legitimate case of a fenced code block containing markup, which is
     * exactly the kind of thing people put in a bio on a game server site.
     */
    public static function normalize(?string $markdown): ?string
    {
        if ($markdown === null) {
            return null;
        }

        $clean = trim(str_replace("\r\n", "\n", $markdown));

        return $clean === '' ? null : $clean;
    }

    /**
     * Markdown to safe HTML, for a server-side consumer.
     *
     * The SPA does not use this — it renders Markdown to React elements
     * directly, so no HTML string is ever inserted into a page. This is
     * here for everything that cannot do that (a future feed, a
     * notification email, a server-rendered page), and for the down()
     * path of the migration that converted stored HTML into Markdown.
     *
     * Two independent guards: the parser is told to strip embedded HTML
     * rather than pass it through, and the result then goes through the
     * element allowlist anyway.
     */
    public static function toHtml(?string $markdown): ?string
    {
        if ($markdown === null) {
            return null;
        }

        $html = self::foldHeadings(Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));

        $clean = trim(self::sanitizer()->sanitize($html));

        return trim(strip_tags($clean)) === '' ? null : $clean;
    }

    /**
     * Every heading becomes an h2 or an h3.
     *
     * The allowlist has only ever had those two, because rich text sits
     * inside a page that already owns the h1 and letting people mint their
     * own breaks the outline of every page their bio appears on. Under the
     * old HTML store that was fine — the editor offered exactly two
     * heading buttons. In Markdown, '#' is the obvious thing to type, and
     * an unlisted element is dropped *with its text*: somebody's heading
     * would silently disappear. Folding instead keeps every word and still
     * keeps the outline. The SPA renderer maps the same levels the same
     * way.
     *
     * A regex over our own generator's output rather than a parse — these
     * tags come from CommonMark, not from a person, and the sanitiser runs
     * over the result afterwards either way.
     */
    private static function foldHeadings(string $html): string
    {
        return preg_replace(
            ['~<(/?)h1>~i', '~<(/?)h[456]>~i'],
            ['<$1h2>', '<$1h3>'],
            $html
        );
    }

    private static function sanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            // Forced onto every link, so rendered rich text cannot hand a
            // reader's referrer or window handle to whatever it links to.
            ->forceAttribute('a', 'rel', 'noreferrer noopener')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(self::MAX_LENGTH * 4);

        foreach (self::ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        return new HtmlSanitizer($config);
    }
}
