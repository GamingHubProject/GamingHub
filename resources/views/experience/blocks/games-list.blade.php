<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
    @forelse ($games as $game)
        <div class="rounded-lg border border-gray-200 p-4">
            <h3 class="font-semibold">{{ $game->name }}</h3>
            @if ($game->description)
                <p class="text-sm text-gray-600 mt-1">{{ $game->description }}</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">No games to show.</p>
    @endforelse
</div>
