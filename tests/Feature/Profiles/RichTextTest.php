<?php

namespace Tests\Feature\Profiles;

use App\Profiles\RichText;
use Tests\TestCase;

class RichTextTest extends TestCase
{
    public function test_it_keeps_the_formatting_the_editor_can_produce(): void
    {
        $html = '<h2>Title</h2><p><strong>Bold</strong> and <em>italic</em> and <code>code</code></p><ul><li>One</li></ul><blockquote>Quoted</blockquote>';

        $this->assertSame($html, RichText::sanitize($html));
    }

    public function test_it_drops_a_script_and_everything_in_it(): void
    {
        $clean = RichText::sanitize('<p>Hello</p><script>alert(document.cookie)</script>');

        $this->assertSame('<p>Hello</p>', $clean);
        $this->assertStringNotContainsString('alert', (string) $clean);
    }

    public function test_it_strips_event_handlers_and_inline_styles(): void
    {
        $clean = RichText::sanitize('<p onclick="steal()" style="position:fixed;inset:0">Text</p>');

        $this->assertSame('<p>Text</p>', $clean);
    }

    public function test_it_refuses_a_javascript_link_while_keeping_its_text(): void
    {
        $clean = RichText::sanitize('<a href="javascript:alert(1)">Click me</a>');

        $this->assertStringNotContainsString('javascript', (string) $clean);
        $this->assertStringContainsString('Click me', (string) $clean);
    }

    public function test_it_forces_rel_and_target_onto_every_link(): void
    {
        $clean = RichText::sanitize('<a href="https://example.com">Example</a>');

        $this->assertStringContainsString('rel="noreferrer noopener"', (string) $clean);
        $this->assertStringContainsString('target="_blank"', (string) $clean);
    }

    public function test_it_drops_images_rather_than_storing_them(): void
    {
        $clean = RichText::sanitize('<p>Look <img src="https://example.com/x.png"></p>');

        $this->assertStringNotContainsString('<img', (string) $clean);
    }

    /**
     * The failure this guards against is quiet: somebody pastes formatted
     * text out of a document, every wrapper div is unlisted, and their
     * words vanish on save rather than the wrappers doing.
     */
    public function test_an_unlisted_wrapper_loses_its_tag_and_keeps_its_words(): void
    {
        $this->assertSame('Words that matter', RichText::sanitize('<div><span>Words that matter</span></div>'));
        $this->assertSame('A heading', RichText::sanitize('<h1>A heading</h1>'));
    }

    public function test_an_emptied_editor_stores_nothing_rather_than_an_empty_paragraph(): void
    {
        $this->assertNull(RichText::sanitize('<p></p>'));
        $this->assertNull(RichText::sanitize('<p><br></p>'));
        $this->assertNull(RichText::sanitize(''));
        $this->assertNull(RichText::sanitize(null));
    }

    /**
     * The two halves of the rich-text contract live in different
     * languages, so nothing but this test notices when they stop agreeing
     * — and the symptom in production is an editor that silently loses
     * formatting the moment somebody saves.
     */
    public function test_the_php_allowlist_matches_the_editors(): void
    {
        $schema = base_path('spa/src/components/RichText/schema.ts');
        $this->assertFileExists($schema);

        preg_match('/export const RICH_TEXT_ELEMENTS = \[(.*?)\] as const;/s', file_get_contents($schema), $matches);
        $this->assertNotEmpty($matches, 'Could not find RICH_TEXT_ELEMENTS in the editor schema.');

        preg_match_all("/'([a-z0-9]+)'/", $matches[1], $found);
        $editor = $found[1];
        sort($editor);

        $php = array_keys(RichText::ELEMENTS);
        sort($php);

        $this->assertSame($php, $editor, 'The editor and the sanitiser no longer allow the same tags.');
    }
}
