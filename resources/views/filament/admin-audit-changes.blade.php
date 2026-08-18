{{--
    Shape of $record->changes varies by action: {field: {old, new}} for
    'updated', a flat attribute snapshot for 'created'/'deleted', or
    {added: [...], removed: [...]} for 'role_changed'/'permissions_changed'.
    Pretty-printed JSON renders all three shapes correctly without needing
    action-specific templates.
--}}
<pre class="max-h-96 overflow-auto rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-xs font-mono whitespace-pre-wrap break-words">{{ json_encode($record->changes, JSON_PRETTY_PRINT) }}</pre>
