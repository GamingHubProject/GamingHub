<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * server-name used to be the only widget type with its own bespoke
 * text-styling config (font_size/text_color) — now that every widget
 * gets Border/Text/Background via the universal `style` key (see
 * WidgetStyleSection/resolveWidgetStyle on the frontend), those two
 * fields are folded into `style.text_size`/`style.text_color` and
 * removed from ServerNameWidgetConfig so there's only one text-styling
 * UI per widget, not two overlapping ones. Existing rows are migrated in
 * place rather than left to silently stop working — the old keys are
 * dropped once moved, since ServerNameWidgetConfigForm no longer writes
 * or reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_layout_widgets')
            ->where('widget_type', 'server-name')
            ->whereNotNull('config')
            ->orderBy('id')
            ->get()
            ->each(function (object $row) {
                $config = json_decode($row->config, true) ?? [];
                if (!array_key_exists('font_size', $config) && !array_key_exists('text_color', $config)) {
                    return;
                }

                $style = $config['style'] ?? [];
                if (array_key_exists('font_size', $config)) {
                    $style['text_size'] = $config['font_size'];
                    unset($config['font_size']);
                }
                if (array_key_exists('text_color', $config)) {
                    $style['text_color'] = $config['text_color'];
                    unset($config['text_color']);
                }
                $config['style'] = $style;

                DB::table('page_layout_widgets')->where('id', $row->id)->update([
                    'config' => json_encode($config),
                ]);
            });
    }

    /**
     * Best-effort, not exact — same caveat as the server-banner->picture
     * rename migration: a `style` key genuinely added after this ran
     * (rather than migrated by it) would incorrectly get split back out
     * too. Fine for a rollback immediately after a bad deploy, not safe
     * once real new style overrides exist.
     */
    public function down(): void
    {
        DB::table('page_layout_widgets')
            ->where('widget_type', 'server-name')
            ->whereNotNull('config')
            ->orderBy('id')
            ->get()
            ->each(function (object $row) {
                $config = json_decode($row->config, true) ?? [];
                $style = $config['style'] ?? null;
                if (!$style || (!array_key_exists('text_size', $style) && !array_key_exists('text_color', $style))) {
                    return;
                }

                if (array_key_exists('text_size', $style)) {
                    $config['font_size'] = $style['text_size'];
                    unset($style['text_size']);
                }
                if (array_key_exists('text_color', $style)) {
                    $config['text_color'] = $style['text_color'];
                    unset($style['text_color']);
                }
                $config['style'] = $style;

                DB::table('page_layout_widgets')->where('id', $row->id)->update([
                    'config' => json_encode($config),
                ]);
            });
    }
};
