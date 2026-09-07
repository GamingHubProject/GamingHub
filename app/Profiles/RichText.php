<?php

namespace App\Profiles;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The one definition of what rich text may contain, and the only way it
 * ever gets stored.
 *
 * ELEMENTS below is the single allowlist: it configures the sanitiser
 * here, and the SPA's Tiptap editor is built from the same list
 * (spa/src/components/RichText/schema.ts) so the editor cannot emit a tag
 * the server then strips. A test asserts the two agree — without it they
 * drift, and the symptom is an editor that silently loses formatting on
 * save.
 *
 * Sanitising happens on write, not on render: the stored value is always
 * safe, so rendering is a plain insertion and nothing downstream has to
 * remember to escape. That means every write path has to come through
 * here — the API, and Filament's UserResource, which is why this is a
 * standalone class rather than a method on a controller.
 *
 * No images. Images in a bio mean upload quotas, moderation and
 * hotlinking policy, which is a release of its own.
 */
class RichText
{
    /**
     * Tag => attributes it may carry. Order is meaningless; the list is
     * the contract.
     *
     * @var array<string, list<string>>
     */
    public const ELEMENTS = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        's' => [],
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

    /**
     * Tags that are removed while their text is kept.
     *
     * Symfony's sanitiser drops an unlisted element *together with its
     * children*, which is the right default for a <script> and badly wrong
     * for a <div>: somebody pasting formatted text out of a document
     * would watch their whole bio disappear on save. These are the
     * wrappers that carry no meaning we store, unwrapped rather than
     * dropped so the words survive.
     *
     * Not part of ELEMENTS on purpose — that list is the exact contract
     * with the editor, "what may be stored and therefore what Tiptap may
     * emit". This one is only about not destroying text on the way in, so
     * b/i/u lose their emphasis here rather than widening the stored
     * vocabulary; Tiptap already normalises those to strong/em when it
     * handles the paste itself.
     *
     * @var list<string>
     */
    public const UNWRAPPED = [
        'div', 'span', 'section', 'article', 'main', 'header', 'footer', 'aside',
        'h1', 'h4', 'h5', 'h6', 'b', 'i', 'u', 'small', 'font', 'label',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'figure', 'figcaption',
    ];

    /** How many characters of HTML a single rich-text field may hold. */
    public const MAX_LENGTH = 20000;

    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $clean = trim(self::sanitizer()->sanitize($html));

        // An editor that has been emptied still emits its container tags,
        // and which ones depends on how it was emptied — "<p></p>",
        // "<p><br /></p>", an empty list. Matching those as strings misses
        // the next variant, so ask the real question instead: is there any
        // text left? Nothing this allowlist keeps carries meaning without
        // text (images are dropped outright), so no text means no bio, and
        // storing markup for it would make every "has a bio" check true
        // forever.
        return trim(strip_tags($clean)) === '' ? null : $clean;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig())
            // Everything not named below is dropped along with its
            // content — see UNWRAPPED for the tags that get their text
            // kept instead.
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            // Forced on every link this stores, so a bio cannot hand a
            // reader's referrer or window handle to whatever it links to.
            ->forceAttribute('a', 'rel', 'noreferrer noopener')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(self::MAX_LENGTH);

        foreach (self::ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        foreach (self::UNWRAPPED as $element) {
            $config = $config->blockElement($element);
        }

        return new HtmlSanitizer($config);
    }
}
