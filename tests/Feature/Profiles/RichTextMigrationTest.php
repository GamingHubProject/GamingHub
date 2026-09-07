<?php

namespace Tests\Feature\Profiles;

use App\Models\PageLayout;
use App\Models\PageLayoutWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * v0.1.024.00 shipped rich text as sanitised HTML for one release, so any
 * site that ran it has bios and Text widgets in the old format. The
 * migration converts them; these run it against real rows rather than
 * trusting that it does.
 *
 * The migration object is invoked directly rather than through `artisan
 * migrate`: RefreshDatabase has already run it by the time a test boots,
 * so the command would find it recorded and skip it — and skipping is
 * indistinguishable from converting nothing, which is exactly the bug
 * these tests exist to catch.
 */
class RichTextMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_08_090000_convert_rich_text_from_html_to_markdown.php');
    }

    private function convert(): void
    {
        $this->migration()->up();
    }

    private function bioAfterConverting(string $html): ?string
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['bio' => $html]);

        $this->convert();

        return DB::table('users')->where('id', $user->id)->value('bio');
    }

    public function test_a_stored_bio_becomes_the_markdown_that_produced_it(): void
    {
        $markdown = $this->bioAfterConverting('<h2>Heading</h2><p>Hello <strong>there</strong></p><ul><li>one</li><li>two</li></ul>');

        $this->assertStringContainsString('## Heading', (string) $markdown);
        $this->assertStringContainsString('**there**', (string) $markdown);
        $this->assertStringContainsString('- one', (string) $markdown);
        $this->assertStringNotContainsString('<', (string) $markdown);
    }

    public function test_links_and_quotes_survive_the_conversion(): void
    {
        $markdown = $this->bioAfterConverting('<p><a href="https://example.com">Example</a></p><blockquote><p>Quoted</p></blockquote>');

        $this->assertStringContainsString('[Example](https://example.com)', (string) $markdown);
        $this->assertStringContainsString('> Quoted', (string) $markdown);
    }

    /**
     * A bio that was already plain text is already valid Markdown. Running
     * it through a converter anyway would only risk escaping characters
     * nobody meant as syntax.
     */
    public function test_plain_text_is_left_exactly_alone(): void
    {
        $this->assertSame('I play on Tuesdays. 5 < 6 & that is fine.', $this->bioAfterConverting('I play on Tuesdays. 5 < 6 & that is fine.'));
    }

    public function test_an_empty_bio_is_left_null(): void
    {
        $user = User::factory()->create();

        $this->convert();

        $this->assertNull(DB::table('users')->where('id', $user->id)->value('bio'));
    }

    public function test_a_text_widgets_config_moves_from_html_to_markdown(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $widget = PageLayoutWidget::create([
            'page_layout_id' => $layout->id,
            'widget_type' => 'text',
            'config' => ['html' => '<p>Welcome to <strong>the server</strong></p>'],
        ]);

        $this->convert();

        $config = json_decode(DB::table('page_layout_widgets')->where('id', $widget->id)->value('config'), true);

        $this->assertArrayNotHasKey('html', $config);
        $this->assertStringContainsString('**the server**', $config['markdown']);
    }

    public function test_other_widget_types_are_not_touched(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $widget = PageLayoutWidget::create([
            'page_layout_id' => $layout->id,
            'widget_type' => 'picture',
            'config' => ['background_url' => 'https://example.com/x.png'],
        ]);

        $this->convert();

        $config = json_decode(DB::table('page_layout_widgets')->where('id', $widget->id)->value('config'), true);

        $this->assertSame(['background_url' => 'https://example.com/x.png'], $config);
    }

    /**
     * Rolling back has to give a working site in the old format, not an
     * empty column — which is the whole reason down() renders rather than
     * dropping.
     */
    public function test_rolling_back_returns_bios_to_html(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['bio' => "## Heading\n\nHello **there**"]);

        $this->migration()->down();

        $bio = (string) DB::table('users')->where('id', $user->id)->value('bio');

        $this->assertStringContainsString('<h2>Heading</h2>', $bio);
        $this->assertStringContainsString('<strong>there</strong>', $bio);
    }
}
