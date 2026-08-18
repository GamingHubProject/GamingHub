{{--
    Shows the last real poll's raw connector payload — distinct from the
    "Test" action's raw output (a live, on-demand call): this is what the
    background scheduler actually last saw, persisted on the Provider row
    itself. Deliberately unsanitized (see PelicanServerStatusNormalizer's
    docblock on why 'invocation' never reaches normalized output) — this
    view is only ever reachable from the already-authenticated admin panel.
--}}
<div class="space-y-2">
    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500 dark:text-gray-400">
            @if ($provider->last_check)
                As of last check: {{ $provider->last_check->diffForHumans() }}
            @else
                This provider hasn't been checked yet.
            @endif
        </span>
        <x-provider-test-copy-button target="raw-response-{{ $provider->id }}" />
    </div>
    <pre id="raw-response-{{ $provider->id }}" class="max-h-96 overflow-auto rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-xs font-mono whitespace-pre-wrap break-words">{{ $provider->last_raw_response !== null ? json_encode($provider->last_raw_response, JSON_PRETTY_PRINT) : 'No raw response recorded yet — this provider hasn\'t completed a real poll or test.' }}</pre>
</div>
