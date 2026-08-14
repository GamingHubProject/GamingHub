<x-filament-panels::page>
    <div class="flex items-end gap-3">
        <div class="flex-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Registry URL</label>
            <input
                type="text"
                wire:model="registryUrl"
                class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm"
            />
        </div>
        <x-filament::button wire:click="refresh" wire:loading.attr="disabled" color="gray">
            <span wire:loading.remove wire:target="refresh">Refresh</span>
            <span wire:loading wire:target="refresh">Loading…</span>
        </x-filament::button>
    </div>

    @if ($error)
        <div class="mt-4 rounded-lg bg-danger-50 dark:bg-danger-900/30 p-4 text-sm text-danger-700 dark:text-danger-300">
            Could not load this registry: {{ $error }}
        </div>
    @elseif (empty($packages))
        <div class="mt-4 rounded-lg bg-gray-50 dark:bg-gray-800 p-4 text-sm text-gray-600 dark:text-gray-400">
            Nothing published in this registry yet.
        </div>
    @else
        <div class="mt-4 space-y-3">
            @foreach ($packages as $package)
                <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ $package['name'] }}</span>
                            <span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-gray-600 dark:text-gray-300">{{ $package['category'] }}</span>
                            @if ($package['official'])
                                <span class="text-xs rounded-full bg-primary-100 dark:bg-primary-900 px-2 py-0.5 text-primary-700 dark:text-primary-300">Official</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $package['description'] }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">id: {{ $package['id'] }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        @if ($package['installedVersion'])
                            <span class="text-xs rounded-full bg-success-100 dark:bg-success-900 px-2 py-1 text-success-700 dark:text-success-300">
                                Installed v{{ $package['installedVersion'] }}
                            </span>
                        @endif

                        <x-filament::button
                            size="sm"
                            wire:click="mountAction('install', { packageId: '{{ $package['id'] }}' })"
                        >
                            {{ $package['installedVersion'] ? 'Reinstall / Update' : 'Install' }}
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
