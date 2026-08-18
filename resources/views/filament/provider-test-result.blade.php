@php
    $typeLabel = match (true) {
        $result->provider->type === 'manual' => 'Manual',
        $result->connectorInstance?->type === 'pelican' => 'Pelican',
        $result->connectorInstance?->type === 'rest' => 'REST',
        default => $result->connectorInstance?->type ?? 'Unknown',
    };

    $endpoint = match (true) {
        $result->provider->type === 'manual' => null,
        $result->connectorInstance && ($result->provider->config['call']['endpoint'] ?? null) => rtrim($result->connectorInstance->base_url, '/').'/'.ltrim($result->provider->config['call']['endpoint'], '/'),
        $result->connectorInstance && ($result->provider->config['server_identifier'] ?? null) => rtrim($result->connectorInstance->base_url, '/')."/api/client/servers/{$result->provider->config['server_identifier']}/resources",
        default => null,
    };

    $capabilityLabels = [
        'status' => 'Status',
        'current_players' => 'Current Players',
        'max_players' => 'Max Players',
        'cpu_usage_percent' => 'CPU Usage %',
        'memory_usage_bytes' => 'Memory Usage (bytes)',
    ];

    // Built as a plain string (not inline Blade directives inside <pre>)
    // so the exact whitespace/newlines are fully under our control —
    // <pre> preserves everything literally, and nested @if/@foreach
    // directives packed onto dense lines are easy to get wrong (they were,
    // the first time this was written).
    if (empty($result->serverPreview)) {
        $serverReceivedText = 'No fields would be written to the Server row.';
    } else {
        $lines = [];
        foreach ($result->serverPreview as $column => $value) {
            $label = $capabilityLabels[$column] ?? $column;
            if (is_bool($value)) {
                $display = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $display = json_encode($value);
            } else {
                $display = (string) $value;
            }
            $lines[] = "{$label}: {$display}";
        }
        $lines[] = '';
        $lines[] = 'Available Capabilities:';
        foreach ($result->serverPreview as $column => $value) {
            $label = $capabilityLabels[$column] ?? $column;
            $lines[] = "- {$label} (from: {$typeLabel})";
        }
        $serverReceivedText = implode("\n", $lines);
    }
@endphp

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-sm">
        <div>
            <span class="text-gray-500 dark:text-gray-400">Provider type</span>
            <div class="font-medium">{{ $typeLabel }}</div>
        </div>
        <div class="min-w-0">
            <span class="text-gray-500 dark:text-gray-400">Endpoint</span>
            <div class="font-medium font-mono truncate">{{ $endpoint ?? '— (no external call)' }}</div>
        </div>
        <div class="ml-auto">
            @if ($result->ok)
                <span class="inline-flex items-center rounded-full bg-success-100 dark:bg-success-900 px-3 py-1 text-xs font-medium text-success-700 dark:text-success-300">
                    Success
                </span>
            @else
                <span class="inline-flex items-center rounded-full bg-danger-100 dark:bg-danger-900 px-3 py-1 text-xs font-medium text-danger-700 dark:text-danger-300">
                    Failed
                </span>
            @endif
        </div>
    </div>

    {{-- 1. Raw Connector Output --}}
    <div>
        <div class="flex items-center justify-between mb-1">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Raw Connector Output</h4>
            <x-provider-test-copy-button target="debug-raw-{{ $result->provider->id }}" />
        </div>
        <pre id="debug-raw-{{ $result->provider->id }}" class="max-h-56 overflow-auto rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-xs font-mono whitespace-pre-wrap break-words">{{ $result->raw !== null ? json_encode($result->raw, JSON_PRETTY_PRINT) : 'Not attempted — see Error below.' }}</pre>
    </div>

    {{-- 2. Normalized Output --}}
    <div>
        <div class="flex items-center justify-between mb-1">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Normalized Output (Core Processing)</h4>
            <x-provider-test-copy-button target="debug-normalized-{{ $result->provider->id }}" />
        </div>
        <pre id="debug-normalized-{{ $result->provider->id }}" class="max-h-56 overflow-auto rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-xs font-mono whitespace-pre-wrap break-words">{{ $result->normalized !== null ? json_encode($result->normalized, JSON_PRETTY_PRINT) : 'Not attempted — see Error below.' }}</pre>
    </div>

    {{-- 3. Server Received + Available Capabilities --}}
    <div>
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1">Server Received + Available Capabilities</h4>
        <pre class="max-h-56 overflow-auto rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-xs font-mono whitespace-pre">{{ $serverReceivedText }}</pre>
    </div>

    {{-- Error section --}}
    @unless ($result->ok)
        <div>
            <h4 class="text-sm font-semibold text-danger-700 dark:text-danger-300 mb-1">Error</h4>
            <pre class="max-h-40 overflow-auto rounded-lg bg-danger-50 dark:bg-danger-900/30 p-3 text-xs font-mono whitespace-pre-wrap text-danger-700 dark:text-danger-300">{{ $result->error }}</pre>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Application Logs From This Test</h4>
                <x-provider-test-copy-button target="debug-logs-{{ $result->provider->id }}" />
            </div>
            <pre id="debug-logs-{{ $result->provider->id }}" class="max-h-40 overflow-auto rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-xs font-mono whitespace-pre-wrap break-words">{{ empty($result->logs) ? 'No log entries were emitted during this test.' : implode("\n", $result->logs) }}</pre>
        </div>
    @endunless
</div>
