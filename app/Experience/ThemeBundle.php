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

        /*
         * A real four-step scale rather than one number everything
         * multiplies. A single base forces every consumer to invent its
         * own multiplier — `calc(var(--spacing) * 2)` scattered around is
         * a scale, just an undocumented one nobody can change coherently.
         *
         * Named by the job rather than by size (no xs/sm/md/lg), because
         * these are admin-facing: "how much room between sections" is a
         * question an admin can answer, "what is space-lg" isn't. Each
         * step has one clear use, which is what keeps a site consistent
         * when different widgets pick different steps.
         */
        'space-tight' => ['label' => 'Tight spacing (inside buttons and rows)', 'unit' => 'px', 'default' => 6],
        'space-normal' => ['label' => 'Normal spacing (inside cards)', 'unit' => 'px', 'default' => 12],
        'space-loose' => ['label' => 'Loose spacing (between cards)', 'unit' => 'px', 'default' => 20],
        'space-section' => ['label' => 'Section spacing (page margins)', 'unit' => 'px', 'default' => 32],
    ];

    /**
     * A region's own styling. Header and sidebar share this shape, which
     * is what makes them symmetrical — anything one can do the other can.
     *
     * `border` carries no side: the sidebar's border is its right edge and
     * the header's is its bottom, always. A "which side" control whose
     * only sensible value is its default is worse than no control (same
     * reasoning as hiding the angle field on a radial gradient), so the
     * region knows its own edge.
     *
     * @var array<string, mixed>
     */
    public const REGION_DEFAULTS = [
        'transparent' => false,
        'background' => [],
        'text_color' => null,
        // The current item. Falls back to the --accent token when unset,
        // so a region that wants the site's accent needn't restate it.
        'accent_color' => null,
        'border' => ['color' => null, 'thickness' => 1],
        // Named presets, not a box-shadow string: an admin can judge
        // "soft" against "strong", not a blur radius.
        'shadow' => 'none',
        // On by default. Branding is new and neither surface shows
        // anything today, so defaulting it off would ship a feature nobody
        // discovers — and every reference design puts the site's identity
        // on both surfaces. The theme only controls whether it shows; the
        // logo, name and tagline are the site's (see
        // ThemeResolver::branding).
        'show_branding' => true,
    ];

    public const HEADER_DEFAULTS = self::REGION_DEFAULTS + [
        // Whether the header spans the whole window or sits beside the
        // sidebar. Beside is the default because it's what both reference
        // designs do — a full-height sidebar with its own branding at the
        // top, and the header only over the content.
        'spans_full_width' => false,
        // Off by default: the header is the tighter of the two surfaces,
        // and a tagline there competes with the navigation for room.
        'show_tagline' => false,
    ];

    public const SIDEBAR_DEFAULTS = self::REGION_DEFAULTS + [
        'width' => 'standard',
        'behavior' => 'always',

        /*
         * Containment: whether the sidebar is an edge-flush panel or a
         * rounded block floating clear of the viewport edges.
         *
         * `margin` is the switch. At 0 the sidebar is flush and draws a
         * right edge only, which is what a panel wants. Above 0 it becomes
         * a contained card — and its border becomes a full outline, because
         * a rounded card with a single curved line down one side reads as
         * broken rather than as a choice (see Sidebar/regionStyle).
         */
        'radius' => null,
        'margin' => 0,

        // auto: exactly as tall as its contents (the original behaviour).
        // full: fills the viewport, less the margins.
        // fixed: whatever height_px says.
        'height' => 'auto',
        'height_px' => null,

        /*
         * Where the links sit in whatever height the sidebar has. Only
         * does anything when there IS spare height — with `auto` the
         * sidebar is exactly its contents and there is nothing to anchor
         * within. Deliberately not forced: quietly changing `height`
         * because of this setting would be a worse surprise than a control
         * that does nothing until its companion is set.
         */
        'nav_align' => 'top',
    ];

    public const SIDEBAR_HEIGHTS = ['auto', 'full', 'fixed'];

    public const NAV_ALIGNMENTS = ['top', 'center', 'bottom'];

    /** @var array<string, int> Named widths — an admin can judge these; a raw pixel value they can't. */
    public const SIDEBAR_WIDTHS = ['compact' => 200, 'standard' => 240, 'wide' => 300];

    public const SHADOWS = ['none', 'soft', 'strong'];

    public const MIRROR_MODES = ['none', 'sidebar_follows_header', 'header_follows_sidebar'];

    /**
     * A region on its way to disk. `background` is cast so an unset one
     * serializes as {} rather than [] — theme.json is a published
     * contract, and a field that changes JSON type when empty is a trap
     * for whatever reads an export.
     */
    private static function serializeRegion(array $region): array
    {
        $region['background'] = (object) ($region['background'] ?? []);

        return $region;
    }

    /**
     * Merge a stored region over its defaults, one level deep so `border`
     * doesn't lose its unset half when only one is set.
     */
    private static function region(mixed $stored, array $defaults): array
    {
        if (! is_array($stored)) {
            return $defaults;
        }

        $merged = array_merge($defaults, $stored);
        $merged['border'] = array_merge($defaults['border'], is_array($stored['border'] ?? null) ? $stored['border'] : []);
        $merged['background'] = is_array($stored['background'] ?? null) ? $stored['background'] : [];

        return $merged;
    }

    /** The spacing steps, in order, for anything that needs the scale as a scale. */
    public const SPACING_STEPS = ['space-tight', 'space-normal', 'space-loose', 'space-section'];

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
        /**
         * The page background behind everything. Same shape as a widget's
         * background (see widgets/shared/background.ts, which draws both)
         * — type plus per-type fields — so "pattern" and "gradient" can't
         * come to mean different things at different scales.
         *
         * An image here is a folder-relative path like every other theme
         * asset, resolved to a URL at sync time.
         *
         * @var array<string, mixed>
         */
        public array $siteBackground = [],
        /**
         * The header and sidebar as symmetrical, independently styled
         * regions. Each is a REGION_DEFAULTS-shaped array: its own
         * background (the same four types the page and widgets use), text
         * and accent colours, border, shadow.
         *
         * Independent on purpose — one can be transparent while the other
         * is solid. Sharing one style block would make that impossible,
         * and "the sidebar looks like the header" is a choice a theme
         * makes, not a constraint the schema should impose.
         *
         * @var array<string, mixed>
         */
        public array $header = [],
        /** @var array<string, mixed> */
        public array $sidebar = [],
        public bool $navEnabled = true,
        /** @var 'top'|'sidebar'|'both' */
        public string $navPosition = 'top',
        /**
         * Whether one surface renders the other's navigation.
         *
         * A pointer rather than a copy: while a surface is following, it
         * has no rows of its own and the API serves the leader's tree for
         * both. That makes "editing one updates the other" the only thing
         * that *can* happen, rather than a sync step to build and keep
         * correct. Turning it off is the one moment anything is copied.
         *
         * @var 'none'|'sidebar_follows_header'|'header_follows_sidebar'
         */
        public string $navMirror = 'sidebar_follows_header',
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
            siteBackground: is_array($data['site']['background'] ?? null) ? $data['site']['background'] : [],
            header: self::region($data['site']['header'] ?? [], self::HEADER_DEFAULTS),
            sidebar: self::region($data['site']['sidebar'] ?? [], self::SIDEBAR_DEFAULTS),
            // Defaults keep an existing install exactly as it is: top nav,
            // no sidebar.
            navPosition: in_array($data['site']['nav_position'] ?? null, ['top', 'sidebar', 'both'], true)
                ? $data['site']['nav_position']
                : 'top',
            navMirror: in_array($data['site']['nav_mirror'] ?? null, self::MIRROR_MODES, true)
                ? $data['site']['nav_mirror']
                : 'sidebar_follows_header',
            navEnabled: (bool) ($data['site']['nav_enabled'] ?? true),
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
            'site' => [
                'background' => (object) $this->siteBackground,
                'nav_enabled' => $this->navEnabled,
                'nav_position' => $this->navPosition,
                'nav_mirror' => $this->navMirror,
                'header' => (object) self::serializeRegion($this->header),
                'sidebar' => (object) self::serializeRegion($this->sidebar),
            ],
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
