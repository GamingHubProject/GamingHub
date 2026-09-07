<?php

namespace Tests\Feature\Profiles;

use App\Profiles\RichText;
use Tests\TestCase;

/**
 * Rich text is Markdown from v0.1.024.01 onward. These cover the two
 * things that are still this class's job: what gets stored (as typed),
 * and what a server-side render turns it into.
 *
 * The renderer people actually see is the SPA's, which builds React
 * elements and never inserts an HTML string at all — see
 * spa/src/components/RichText. toHtml() is the path for anything that
 * cannot do that.
 */
class RichTextTest extends TestCase
{
    // --- What gets stored ---

    public function test_markdown_is_stored_exactly_as_it_was_typed(): void
    {
        $markdown = "## Heading\n\n- one\n- two\n\n**bold** and [a link](https://example.com)";

        $this->assertSame($markdown, RichText::normalize($markdown));
    }

    /**
     * The old HTML store sanitised on write, which meant an editor could
     * hand back something different from what was typed. Storing the
     * source is what makes the round trip lossless — including the markup
     * somebody deliberately quoted in a code fence.
     */
    public function test_markup_inside_a_code_fence_survives_being_stored(): void
    {
        $markdown = "Here is my config:\n\n```\n<script>alert(1)</script>\n```";

        $this->assertSame($markdown, RichText::normalize($markdown));
    }

    public function test_an_emptied_editor_stores_nothing(): void
    {
        $this->assertNull(RichText::normalize(''));
        $this->assertNull(RichText::normalize("   \n  "));
        $this->assertNull(RichText::normalize(null));
    }

    public function test_windows_line_endings_are_normalised(): void
    {
        $this->assertSame("one\ntwo", RichText::normalize("one\r\ntwo"));
    }

    // --- What a server-side render produces ---

    public function test_it_renders_every_piece_of_formatting_the_toolbar_offers(): void
    {
        $html = (string) RichText::toHtml("## Heading\n\n**bold** *italic* ~~struck~~ `code`\n\n- one\n- two\n\n1. first\n\n> quoted\n\n[link](https://example.com)");

        foreach ([
            '<h2>Heading</h2>',
            '<strong>bold</strong>',
            '<em>italic</em>',
            '<del>struck</del>',
            '<code>code</code>',
            '<li>one</li>',
            '<ol>',
            '<blockquote>',
            'href="https://example.com"',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $html, "The toolbar offers this but the renderer drops it: {$fragment}");
        }
    }

    /**
     * An HTML *block* goes entirely, content included; an inline tag loses
     * the tag and keeps its words. That is deliberately the same split the
     * SPA renderer makes with `skipHtml`, so a document cannot mean two
     * different things depending on which renderer got it.
     */
    public function test_an_html_block_is_dropped_whole(): void
    {
        $this->assertNull(RichText::toHtml('<script>alert(document.cookie)</script>'));
        $this->assertNull(RichText::toHtml('<div>hidden</div>'));
        $this->assertSame("<p>before</p>\n\n<p>after</p>", RichText::toHtml("before\n\n<script>alert(1)</script>\n\nafter"));
    }

    public function test_an_inline_tag_loses_the_tag_and_keeps_the_words(): void
    {
        $html = (string) RichText::toHtml('Hello <b>there</b> <img src=x onerror=y>');

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('Hello there', $html);
    }

    public function test_a_javascript_link_never_makes_it_into_an_href(): void
    {
        $html = RichText::toHtml('[Click me](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript', (string) $html);
        $this->assertStringContainsString('Click me', (string) $html);
    }

    public function test_every_rendered_link_is_treated_as_hostile(): void
    {
        $html = RichText::toHtml('[Example](https://example.com)');

        $this->assertStringContainsString('rel="noreferrer noopener"', (string) $html);
        $this->assertStringContainsString('target="_blank"', (string) $html);
    }

    /**
     * '#' is the obvious thing to type in Markdown, and the allowlist has
     * only ever kept h2/h3 — dropping the element would drop the words
     * with it, so every level folds onto one that survives.
     */
    public function test_headings_fold_onto_the_two_levels_the_allowlist_keeps(): void
    {
        $this->assertStringContainsString('<h2>Top</h2>', (string) RichText::toHtml('# Top'));
        $this->assertStringContainsString('<h3>Deep</h3>', (string) RichText::toHtml('###### Deep'));
    }

    public function test_an_image_renders_as_nothing_rather_than_as_an_image(): void
    {
        $html = RichText::toHtml('![alt](https://example.com/x.png)');

        $this->assertStringNotContainsString('<img', (string) $html);
    }

    public function test_nothing_worth_rendering_comes_back_as_null(): void
    {
        $this->assertNull(RichText::toHtml(''));
        $this->assertNull(RichText::toHtml(null));
    }

    /**
     * The two halves of the rendering contract live in different
     * languages, so nothing but this test notices when they stop agreeing.
     */
    public function test_the_php_allowlist_matches_the_renderers(): void
    {
        $schema = base_path('spa/src/components/RichText/schema.ts');
        $this->assertFileExists($schema);

        preg_match('/export const RICH_TEXT_ELEMENTS = \[(.*?)\] as const;/s', file_get_contents($schema), $matches);
        $this->assertNotEmpty($matches, 'Could not find RICH_TEXT_ELEMENTS in the renderer schema.');

        preg_match_all("/'([a-z0-9]+)'/", $matches[1], $found);
        $renderer = $found[1];
        sort($renderer);

        $php = array_keys(RichText::ELEMENTS);
        sort($php);

        $this->assertSame($php, $renderer, 'The two renderers no longer allow the same elements.');
    }
}
