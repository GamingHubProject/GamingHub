{{-- Real Filament badge component — Placeholder::content() only accepts a
     string/Htmlable, not a table-column-style ->badge() modifier, so the
     colored-background look needs the actual <x-filament::badge> markup
     rather than plain text. --}}
<x-filament::badge :color="$color" size="sm">
    {{ $label }}
</x-filament::badge>
