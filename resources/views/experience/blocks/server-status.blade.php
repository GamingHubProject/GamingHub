<div class="rounded-lg border border-gray-200 p-4">
    @if ($server)
        <div class="flex items-center justify-between">
            <span class="font-semibold">{{ $server->name }}</span>

            @if ($capability?->isOk())
                <span
                    class="text-xs uppercase tracking-wide font-semibold px-2 py-0.5 rounded-full"
                    style="color: var(--color-primary, #6b7280); background-color: color-mix(in srgb, var(--color-primary, #6b7280) 15%, transparent)"
                >{{ $capability->data['online'] ?? false ? 'Online' : 'Offline' }}</span>
            @else
                <span class="text-xs uppercase tracking-wide text-gray-400">
                    {{ match ($capability?->status) {
                        'unsupported' => 'No status source configured',
                        'stale' => 'Status data is stale',
                        default => 'Status unavailable',
                    } }}
                </span>
            @endif
        </div>

        @if ($capability?->isOk() && isset($capability->data['players']))
            <p class="text-sm text-gray-600 mt-1">
                {{ $capability->data['players'] }}{{ isset($capability->data['max_players']) ? ' / '.$capability->data['max_players'] : '' }} players
            </p>
        @endif
    @else
        <p class="text-sm text-gray-500">Server not found.</p>
    @endif
</div>
