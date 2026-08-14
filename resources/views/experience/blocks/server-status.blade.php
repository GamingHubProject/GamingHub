<div class="rounded-lg border border-gray-200 p-4">
    @if ($server)
        <div class="flex items-center justify-between">
            <span class="font-semibold">{{ $server->name }}</span>
            <span class="text-xs uppercase tracking-wide text-gray-500">{{ $server->status }}</span>
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
