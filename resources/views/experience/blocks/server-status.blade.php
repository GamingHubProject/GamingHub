<div class="rounded-lg border border-gray-200 p-4">
    @if ($server)
        <div class="flex items-center justify-between">
            <span class="font-semibold">{{ $server->name }}</span>
            <span
                class="text-xs uppercase tracking-wide font-semibold px-2 py-0.5 rounded-full"
                style="color: var(--color-primary, #6b7280); background-color: color-mix(in srgb, var(--color-primary, #6b7280) 15%, transparent)"
            >{{ $server->status }}</span>
        </div>
        @if ($server->max_players)
            <p class="text-sm text-gray-600 mt-1">
                {{ $server->current_players ?? 0 }} / {{ $server->max_players }} players
            </p>
        @endif
    @else
        <p class="text-sm text-gray-500">Server not found.</p>
    @endif
</div>
