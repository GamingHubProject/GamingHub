<?php

use App\Profiles\RichText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * v0.1.024.00 stored rich text as sanitised HTML; from v0.1.024.01 it is
 * Markdown (see App\Profiles\RichText for why). Two places hold it: a
 * person's bio, and the Text widget's config.
 *
 * Converted rather than dropped, and converted rather than left for the
 * renderer to guess at: a stored document has to be in exactly one format,
 * or every consumer needs a heuristic and they will not all agree.
 * league/html-to-markdown handles the whole allowlist this app ever
 * stored (headings, emphasis, lists, quotes, code, links), so the round
 * trip is faithful for real content.
 *
 * Only values that actually look like HTML are touched. A bio typed as
 * plain text is already valid Markdown and passing it through a converter
 * would only risk escaping characters nobody meant as syntax.
 *
 * down() converts back through RichText::toHtml(), which is the same
 * renderer any server-side consumer uses. That direction is lossier —
 * Markdown the old allowlist had no element for degrades to plain text —
 * but it is a real rollback rather than a data loss, which is what
 * matters if this release has to be backed out.
 */
return new class extends Migration
{
    public function up(): void
    {
        $converter = new HtmlConverter([
            'header_style' => 'atx',
            'strip_tags' => true,
            'hard_break' => true,
            'remove_nodes' => 'script style',
        ]);

        foreach (DB::table('users')->whereNotNull('bio')->get(['id', 'bio']) as $user) {
            if (! self::looksLikeHtml($user->bio)) {
                continue;
            }

            DB::table('users')->where('id', $user->id)->update([
                'bio' => RichText::normalize($converter->convert($user->bio)),
            ]);
        }

        foreach (self::textWidgets() as $widget) {
            $config = json_decode($widget->config, true) ?: [];

            if (! array_key_exists('html', $config)) {
                continue;
            }

            $html = (string) $config['html'];
            unset($config['html']);
            $config['markdown'] = $html === '' ? '' : (string) RichText::normalize($converter->convert($html));

            DB::table('page_layout_widgets')->where('id', $widget->id)->update(['config' => json_encode($config)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('users')->whereNotNull('bio')->get(['id', 'bio']) as $user) {
            DB::table('users')->where('id', $user->id)->update(['bio' => RichText::toHtml($user->bio)]);
        }

        foreach (self::textWidgets() as $widget) {
            $config = json_decode($widget->config, true) ?: [];

            if (! array_key_exists('markdown', $config)) {
                continue;
            }

            $markdown = (string) $config['markdown'];
            unset($config['markdown']);
            $config['html'] = $markdown === '' ? '' : (string) RichText::toHtml($markdown);

            DB::table('page_layout_widgets')->where('id', $widget->id)->update(['config' => json_encode($config)]);
        }
    }

    /**
     * A tag from the allowlist the old store actually used. Deliberately
     * not "contains a < or a &" — a bio reading "5 < 6" is plain text, and
     * running it through an HTML-to-Markdown converter would mangle it.
     */
    private static function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('~</?(p|br|strong|em|s|code|pre|blockquote|h[1-6]|ul|ol|li|a)\b[^>]*>~i', $value);
    }

    private static function textWidgets(): \Illuminate\Support\Collection
    {
        return DB::table('page_layout_widgets')
            ->where('widget_type', 'text')
            ->whereNotNull('config')
            ->get(['id', 'config']);
    }
};
