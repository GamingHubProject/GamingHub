@props(['target'])

<button
    type="button"
    x-data="{ copied: false }"
    x-on:click="navigator.clipboard.writeText(document.getElementById('{{ $target }}').innerText); copied = true; setTimeout(() => copied = false, 1500)"
    class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
>
    <span x-show="!copied">Copy</span>
    <span x-show="copied" x-cloak>Copied!</span>
</button>
