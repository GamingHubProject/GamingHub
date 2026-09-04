<?php

namespace App\Experience;

/**
 * The theme.json contract — what a theme *is*, independent of where it's
 * stored. Everything a site's appearance is made of, in one shape that can
 * be written to a folder, zipped, handed to another install and still mean
 * the same thing there.
 *
 * That portability requirement is what drives the two decisions below that
 * would otherwise look arbitrary:
 *
 * - Tokens are a FIXED, named set (see TOKENS) rather than the free-form
 *   key/value map themes used to carry. A free-form key can't be
 *   validated, can't be shown as a labelled swatch in the admin, and — the
 *   real problem — can't be relied on to mean anything on the install that
 *   receives an exported theme. `extra_tokens` stays as an escape hatch
 *   for a design that genuinely needs a token outside the contract.
 *
 * - Asset references are paths RELATIVE to the theme's own folder
 *   ("font/Inter.woff2"), never asset ids or absolute URLs. An id is
 *   meaningless on another install and a URL is meaningless off this
 *   domain; a relative path stays true wherever the folder is unpacked.
 *   Resolving those to real URLs is ThemeStorage's job, done once at sync
 *   time so a page load never pays for it.
 */
class ThemeBundle
{
    public const SCHEMA = 'gaming-hub/theme@1';

    /**
     * The colour half of the token contract. Key => admin-facing label.
     * These are the CSS custom properties the SPA sets on :root (as
     * --surface, --border and so on), so adding one here is the only thing
     * needed to make it themeable — ThemeProvider applies whatever it's
     * given.
     *
     * @var array<string, string>
     */
    public const COLOR_TOKENS = [
        'background' => 'Page background',
        'surface' => 'Surface (cards, header)',
        'surface-muted' => 'Muted surface',
        'text' => 'Text',
        'muted' => 'Muted text',
        'border' => 'Borders',
        'accent' => 'Accent',
        'accent-contrast' => 'Text on accent',
    ];

    /**
     * The non-colour half: shape and rhythm. Same mechanism (they're CSS
     * variables on :root like any other token) but they carry a unit, so
     * the admin form renders them as numbers rather than swatches.
     *
     * These exist because "rounded corners throughout" and consistent
     * spacing are theme-level decisions, not per-widget ones — a widget's
     * own border_radius override still wins, but with nothing set every
     * card, modal, dropdown and input should round by the same amount, and
     * before this each of those hardcoded its own number.
     *
     * @var array<string, array{label: string, unit: string, default: int}>
     */
    public const SCALE_TOKENS = [
        'radius' => ['label' => 'Corner radius', 'unit' => 'px', 'default' => 8],
        'spacing' => ['label' => 'Base spacing', 'unit' => 'px', 'default' => 12],
    ];

    /** Every token the contract knows about, colour and scale together. */
    public static function contractTokens(): array
    {
        return array_merge(
            self::COLOR_TOKENS,
            array_map(fn (array $t) => $t['label'], self::SCALE_TOKENS)
        );
    }

    public function __construct(
        public string $id,
        public string $name,
        public string $version = '1.0.0',
        /** @var array<string, string> */
        public array $tokens = [],
        /** @var array<string, string> */
        public array $extraTokens = [],
        public ?string $fontFile = null,
        public ?string $fontFamily = null,
        public ?string $faviconFile = null,
        /** @var array<string, mixed> */
        public array $widgetStyle = [],
        public bool $headerTransparent = false,
    ) {
    }

    /**
     * Tolerant on purpose: an unknown key is dropped and a missing one
     * falls back, rather than throwing. A theme.json can arrive from an
     * older build of this app, a newer one, or a hand edit — none of which
     * should be able to take the whole site's styling down. Import
     * validation (Phase C) is a separate, stricter gate on untrusted files.
     */
    public static function fromArray(array $data): self
    {
        $tokens = is_array($data['tokens'] ?? null) ? $data['tokens'] : [];

        return new self(
            id: (string) ($data['id'] ?? 'theme'),
            name: (string) ($data['name'] ?? 'Untitled theme'),
            version: (string) ($data['version'] ?? '1.0.0'),
            tokens: array_intersect_key($tokens, self::contractTokens()),
            extraTokens: array_diff_key($tokens, self::contractTokens()),
            fontFile: $data['font']['file'] ?? null,
            fontFamily: $data['font']['family'] ?? null,
            faviconFile: $data['favicon']['file'] ?? null,
            widgetStyle: is_array($data['widgetStyle'] ?? null) ? $data['widgetStyle'] : [],
            headerTransparent: (bool) ($data['site']['header_transparent'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            // Contract tokens and escape-hatch tokens are one flat map on
            // disk — the split only exists so the admin form knows which
            // ones to render as labelled swatches.
            // Cast so an empty set serializes as {} rather than [] — this
            // file is a published contract (it's what an export and a
            // registry package ship), and a type that changes shape when
            // empty is a trap for anything consuming it.
            'tokens' => (object) array_merge($this->tokens, $this->extraTokens),
            'font' => $this->fontFile ? ['file' => $this->fontFile, 'family' => $this->fontFamily] : null,
            'favicon' => $this->faviconFile ? ['file' => $this->faviconFile] : null,
            'widgetStyle' => (object) $this->widgetStyle,
            'site' => ['header_transparent' => $this->headerTransparent],
        ];
    }

    /**
     * Every token, contract and extra, as the SPA consumes them — which
     * means CSS-ready.
     *
     * theme.json stores a scale token as a bare number (`"radius": 8`),
     * because that's the honest value for a published contract: a registry
     * package or an importing site can reason about 8, not about the
     * string "8px". But `border-radius: var(--radius)` needs a unit, so it
     * is appended here, at the boundary where a token stops being data and
     * becomes CSS. A value that already carries a unit is passed through,
     * so a hand-edited "0.5rem" still works.
     */
    public function allTokens(): array
    {
        $tokens = array_merge($this->tokens, $this->extraTokens);

        foreach (self::SCALE_TOKENS as $key => $spec) {
            if (isset($tokens[$key]) && is_numeric($tokens[$key])) {
                $tokens[$key] = $tokens[$key].$spec['unit'];
            }
        }

        return $tokens;
    }
}
